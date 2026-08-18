<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Konekt\Spoke\Support\JsonlFile;

/**
 * Turns `metrics-*.jsonl` samples into bucketed series for the history charts.
 */
class MetricsReader
{
    /**
     * Gauge values, averaged per bucket.
     *
     * @var array<string, array{label: string, unit: string}>
     */
    private const GAUGES = [
        'load_pct' => ['label' => 'CPU load', 'unit' => '%'],
        'mem_used_pct' => ['label' => 'Memory used', 'unit' => '%'],
        'disk_used_pct' => ['label' => 'Disk used', 'unit' => '%'],
        'db_conn' => ['label' => 'DB connections', 'unit' => ''],
        'db_cache_hit_pct' => ['label' => 'DB cache hit', 'unit' => '%'],
        'redis_mem_mb' => ['label' => 'Redis memory', 'unit' => 'MB'],
        'queue_pending' => ['label' => 'Queue pending', 'unit' => ''],
        'queue_failed' => ['label' => 'Failed jobs', 'unit' => ''],
        'opcache_used_mb' => ['label' => 'OPcache memory', 'unit' => 'MB'],
    ];

    /**
     * Cumulative counters, meaningful only as a delta between two samples.
     *
     * @var array<string, array{label: string, hits: string, misses: string}>
     */
    private const RATES = [
        'redis_hit_rate_pct' => [
            'label' => 'Redis hit rate',
            'hits' => 'redis_hits',
            'misses' => 'redis_misses',
        ],
        'opcache_hit_rate_pct' => [
            'label' => 'OPcache hit rate',
            'hits' => 'opcache_hits',
            'misses' => 'opcache_misses',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function series(?string $range = null): array
    {
        [$range, $hours, $bucket] = $this->resolveRange($range);

        $to = time();
        $from = $to - ($hours * 3600);
        $rows = $this->rows($from);
        $buckets = (int) ceil(($hours * 3600) / $bucket);

        $gauges = [];
        $counters = [];

        foreach ($rows as $row) {
            $index = min($buckets - 1, intdiv($row['_ts'] - $from, $bucket));

            foreach (self::GAUGES as $key => $meta) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $gauges[$key][$index][] = (float) $row[$key];
                }
            }

            foreach (self::RATES as $rate) {
                foreach ([$rate['hits'], $rate['misses']] as $key) {
                    if (isset($row[$key]) && is_numeric($row[$key])) {
                        $counters[$key][$index]['first'] ??= (float) $row[$key];
                        $counters[$key][$index]['last'] = (float) $row[$key];
                    }
                }
            }
        }

        $labels = [];

        for ($i = 0; $i < $buckets; $i++) {
            $labels[$i] = date($hours > 24 ? 'd.m H:i' : 'H:i', $from + ($i * $bucket));
        }

        $series = [];

        foreach (self::GAUGES as $key => $meta) {
            $points = [];

            for ($i = 0; $i < $buckets; $i++) {
                $values = $gauges[$key][$i] ?? [];
                $points[] = [
                    't' => $labels[$i],
                    'v' => $values === [] ? null : round(array_sum($values) / count($values), 2),
                ];
            }

            $series[] = $this->describe($key, $meta['label'], $meta['unit'], $points);
        }

        foreach (self::RATES as $key => $rate) {
            $points = $this->ratePoints($counters, $rate['hits'], $rate['misses'], $buckets, $labels);
            $series[] = $this->describe($key, $rate['label'], '%', $points);
        }

        return [
            'series' => array_values(array_filter($series, static fn (array $s): bool => $s['samples'] > 0)),
            'meta' => [
                'enabled' => (bool) config('spoke.metrics.enabled', false),
                'range' => $range,
                'bucket_seconds' => $bucket,
                'samples' => count($rows),
                'from' => date('Y-m-d H:i', $from),
                'to' => date('Y-m-d H:i', $to),
                'command' => 'spoke:sample',
            ],
        ];
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function resolveRange(?string $range): array
    {
        return match ($range) {
            '1h' => ['1h', 1, 60],
            '7d' => ['7d', 168, 3600],
            default => ['24h', 24, 300],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(int $from): array
    {
        $rows = [];
        $day = strtotime(date('Y-m-d', $from));
        $today = strtotime(date('Y-m-d'));

        for ($cursor = $day; $cursor <= $today; $cursor += 86400) {
            foreach (JsonlFile::rows(JsonlFile::path('metrics', date('Y-m-d', $cursor))) as $row) {
                $ts = strtotime((string) ($row['t'] ?? ''));

                if ($ts === false || $ts < $from) {
                    continue;
                }

                $row['_ts'] = $ts;
                $rows[] = $row;
            }
        }

        usort($rows, static fn (array $a, array $b): int => $a['_ts'] <=> $b['_ts']);

        return $rows;
    }

    /**
     * Hit rate derived from counter deltas. The baseline is the last sample of the
     * previous bucket; a negative delta means the process restarted and is skipped.
     *
     * @param  array<string, array<int, array{first?: float, last?: float}>>  $counters
     * @param  array<int, string>  $labels
     * @return list<array{t: string, v: float|null}>
     */
    private function ratePoints(array $counters, string $hitsKey, string $missesKey, int $buckets, array $labels): array
    {
        $points = [];
        $prevHits = null;
        $prevMisses = null;

        for ($i = 0; $i < $buckets; $i++) {
            $hits = $counters[$hitsKey][$i] ?? null;
            $misses = $counters[$missesKey][$i] ?? null;
            $value = null;

            if ($hits !== null && $misses !== null) {
                $hitBase = $prevHits ?? $hits['first'];
                $missBase = $prevMisses ?? $misses['first'];
                $hitDelta = $hits['last'] - $hitBase;
                $missDelta = $misses['last'] - $missBase;
                $lookups = $hitDelta + $missDelta;

                if ($hitDelta >= 0 && $missDelta >= 0 && $lookups > 0) {
                    $value = round($hitDelta / $lookups * 100, 2);
                }

                $prevHits = $hits['last'];
                $prevMisses = $misses['last'];
            }

            $points[] = ['t' => $labels[$i], 'v' => $value];
        }

        return $points;
    }

    /**
     * @param  list<array{t: string, v: float|null}>  $points
     * @return array<string, mixed>
     */
    private function describe(string $key, string $label, string $unit, array $points): array
    {
        $values = array_values(array_filter(
            array_column($points, 'v'),
            static fn ($v): bool => $v !== null
        ));

        return [
            'key' => $key,
            'label' => $label,
            'unit' => $unit,
            'samples' => count($values),
            'latest' => $values === [] ? null : $values[count($values) - 1],
            'min' => $values === [] ? null : min($values),
            'max' => $values === [] ? null : max($values),
            'avg' => $values === [] ? null : round(array_sum($values) / count($values), 2),
            'points' => $points,
        ];
    }
}

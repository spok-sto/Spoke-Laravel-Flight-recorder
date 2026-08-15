<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Konekt\Spoke\Support\QueryNormalizer;

/**
 * Ranking i regresija SQL upita iz dnevnih queries-*.jsonl fajlova.
 */
class QueryStatsReader
{
    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    public function ranking(?string $date = null, string $sort = 'total_ms', ?string $search = null): array
    {
        $date = $this->normalizeDate($date);
        $sort = in_array($sort, ['total_ms', 'count', 'avg_ms', 'max_ms'], true) ? $sort : 'total_ms';
        $today = $this->aggregateFile($this->fileFor($date));
        $baseline = $this->aggregatePreviousDays($date);

        $minSamples = max(2, (int) config('spoke.query_stats.min_samples', 5));
        $factor = (float) config('spoke.query_stats.regression_factor', 2.0);

        $rows = [];

        foreach ($today as $fingerprint => $stats) {
            $base = $baseline[$fingerprint] ?? null;
            $regressionPct = null;

            if (
                $base !== null
                && $stats['count'] >= $minSamples
                && $base['count'] >= $minSamples
                && $base['avg_ms'] > 0
                && ($stats['avg_ms'] / $base['avg_ms']) >= $factor
            ) {
                $regressionPct = (int) round((($stats['avg_ms'] / $base['avg_ms']) - 1) * 100);
            }

            $row = $stats + [
                'fingerprint' => $fingerprint,
                'baseline_avg_ms' => $base['avg_ms'] ?? null,
                'regression_pct' => $regressionPct,
            ];

            if ($search !== null && $search !== '') {
                $hay = mb_strtolower($row['sql'] . ' ' . implode(' ', $row['uris']));
                if (! str_contains($hay, mb_strtolower($search))) {
                    continue;
                }
            }

            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b) use ($sort): int {
            return ($b[$sort] <=> $a[$sort]) ?: ($b['count'] <=> $a['count']);
        });

        $perPage = (int) config('spoke.per_page', 50);

        return [
            'data' => array_slice($rows, 0, $perPage),
            'meta' => [
                'date' => $date,
                'sort' => $sort,
                'total' => count($rows),
                'dates' => $this->availableDates(),
            ],
        ];
    }

    /**
     * @return array<string, array{count: int, total_ms: float, avg_ms: float, max_ms: float, sql: string, uris: list<string>}>
     */
    private function aggregateFile(string $file): array
    {
        $groups = [];

        foreach ($this->readRows($file) as $row) {
            $sql = (string) ($row['sql'] ?? '');

            if ($sql === '') {
                continue;
            }

            $fingerprint = (string) ($row['fingerprint'] ?? QueryNormalizer::fingerprint($sql));
            $ms = (float) ($row['ms'] ?? 0);

            if (! isset($groups[$fingerprint])) {
                $groups[$fingerprint] = [
                    'count' => 0,
                    'total_ms' => 0.0,
                    'max_ms' => 0.0,
                    'sql' => $sql,
                    'uris' => [],
                ];
            }

            $groups[$fingerprint]['count']++;
            $groups[$fingerprint]['total_ms'] += $ms;
            $groups[$fingerprint]['max_ms'] = max($groups[$fingerprint]['max_ms'], $ms);

            $uri = (string) ($row['uri'] ?? '');
            if ($uri !== '' && ! in_array($uri, $groups[$fingerprint]['uris'], true) && count($groups[$fingerprint]['uris']) < 8) {
                $groups[$fingerprint]['uris'][] = $uri;
            }
        }

        $out = [];

        foreach ($groups as $fingerprint => $stats) {
            $out[$fingerprint] = [
                'count' => $stats['count'],
                'total_ms' => round($stats['total_ms'], 2),
                'avg_ms' => round($stats['total_ms'] / $stats['count'], 2),
                'max_ms' => round($stats['max_ms'], 2),
                'sql' => $stats['sql'],
                'uris' => $stats['uris'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{count: int, total_ms: float, avg_ms: float, max_ms: float, sql: string, uris: list<string>}>
     */
    private function aggregatePreviousDays(string $date): array
    {
        $days = max(1, (int) config('spoke.retention_days', 7));
        $merged = [];
        $cursor = strtotime($date . ' 00:00:00') ?: time();

        for ($i = 1; $i < $days; $i++) {
            $prev = date('Y-m-d', $cursor - ($i * 86400));
            foreach ($this->aggregateFile($this->fileFor($prev)) as $fp => $stats) {
                if (! isset($merged[$fp])) {
                    $merged[$fp] = $stats;

                    continue;
                }

                $merged[$fp]['count'] += $stats['count'];
                $merged[$fp]['total_ms'] += $stats['total_ms'];
                $merged[$fp]['max_ms'] = max($merged[$fp]['max_ms'], $stats['max_ms']);
            }
        }

        foreach ($merged as &$stats) {
            $stats['avg_ms'] = $stats['count'] > 0
                ? round($stats['total_ms'] / $stats['count'], 2)
                : 0.0;
        }
        unset($stats);

        return $merged;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readRows(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $maxBytes = (int) config('spoke.max_read_bytes');
        $size = filesize($file);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return [];
        }

        $truncated = false;

        if ($size > $maxBytes) {
            fseek($handle, $size - $maxBytes);
            $truncated = true;
        }

        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        $lines = explode("\n", trim($content));

        if ($truncated && $lines !== []) {
            array_shift($lines);
        }

        $rows = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function availableDates(): array
    {
        $dir = config('spoke.storage_path');
        $dates = [];

        foreach (glob($dir . '/queries-*.jsonl') ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        rsort($dates);

        return $dates;
    }

    private function fileFor(string $date): string
    {
        return config('spoke.storage_path') . '/queries-' . $date . '.jsonl';
    }

    private function normalizeDate(?string $date): string
    {
        $date = $date ?: date('Y-m-d');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return date('Y-m-d');
        }

        return $date;
    }
}

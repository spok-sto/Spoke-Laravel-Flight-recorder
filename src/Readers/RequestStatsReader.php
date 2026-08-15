<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Konekt\Spoke\Support\JsonlFile;
use Konekt\Spoke\Support\UriNormalizer;

/**
 * P95 regresija HTTP requesta po normalizovanom URI-ju.
 */
class RequestStatsReader
{
    /**
     * @return array<string, mixed>
     */
    public function regressions(?string $date = null): array
    {
        $date = JsonlFile::normalizeDate($date);
        $today = $this->aggregateFile(JsonlFile::path('requests', $date));
        $baseline = $this->aggregatePreviousDays($date);

        $minSamples = max(2, (int) config('spoke.query_stats.min_samples', 5));
        $factor = (float) config('spoke.query_stats.regression_factor', 2.0);

        $rows = [];
        $p95Values = [];

        foreach ($today as $uri => $stats) {
            $p95Values[] = $stats['p95_ms'];
            $base = $baseline[$uri] ?? null;
            $regressionPct = null;

            if (
                $base !== null
                && $stats['count'] >= $minSamples
                && $base['count'] >= $minSamples
                && $base['p95_ms'] > 0
                && ($stats['p95_ms'] / $base['p95_ms']) >= $factor
            ) {
                $regressionPct = (int) round((($stats['p95_ms'] / $base['p95_ms']) - 1) * 100);
            }

            if ($regressionPct === null) {
                continue;
            }

            $rows[] = $stats + [
                'uri' => $uri,
                'baseline_p95_ms' => $base['p95_ms'],
                'regression_pct' => $regressionPct,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return ($b['regression_pct'] <=> $a['regression_pct']) ?: ($b['p95_ms'] <=> $a['p95_ms']);
        });

        return [
            'data' => array_slice($rows, 0, 20),
            'meta' => [
                'date' => $date,
                'today_p95_ms' => $this->percentile($p95Values, 95),
                'today_count' => array_sum(array_column($today, 'count')),
                'dates' => JsonlFile::dates('requests'),
            ],
        ];
    }

    /**
     * @return array<string, array{count: int, avg_ms: float, p95_ms: float}>
     */
    private function aggregateFile(string $file): array
    {
        $groups = [];

        foreach (JsonlFile::rows($file) as $row) {
            $uri = UriNormalizer::normalize($row['uri'] ?? null);
            $ms = (float) ($row['ms'] ?? 0);

            if (! isset($groups[$uri])) {
                $groups[$uri] = [
                    'count' => 0,
                    'total_ms' => 0.0,
                    'values' => [],
                ];
            }

            $groups[$uri]['count']++;
            $groups[$uri]['total_ms'] += $ms;
            $groups[$uri]['values'][] = $ms;
        }

        $out = [];

        foreach ($groups as $uri => $stats) {
            $out[$uri] = [
                'count' => $stats['count'],
                'avg_ms' => round($stats['total_ms'] / $stats['count'], 2),
                'p95_ms' => $this->percentile($stats['values'], 95) ?? 0.0,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{count: int, avg_ms: float, p95_ms: float}>
     */
    private function aggregatePreviousDays(string $date): array
    {
        $days = max(1, (int) config('spoke.retention_days', 7));
        $merged = [];
        $cursor = strtotime($date . ' 00:00:00') ?: time();

        for ($i = 1; $i < $days; $i++) {
            $prev = date('Y-m-d', $cursor - ($i * 86400));
            foreach ($this->aggregateFile(JsonlFile::path('requests', $prev)) as $uri => $stats) {
                if (! isset($merged[$uri])) {
                    $merged[$uri] = [
                        'count' => 0,
                        'weighted' => 0.0,
                        'p95_acc' => 0.0,
                        'p95_n' => 0,
                    ];
                }
                $merged[$uri]['count'] += $stats['count'];
                $merged[$uri]['weighted'] += $stats['avg_ms'] * $stats['count'];
                $merged[$uri]['p95_acc'] += $stats['p95_ms'];
                $merged[$uri]['p95_n']++;
            }
        }

        $out = [];

        foreach ($merged as $uri => $stats) {
            $out[$uri] = [
                'count' => $stats['count'],
                'avg_ms' => $stats['count'] > 0 ? round($stats['weighted'] / $stats['count'], 2) : 0.0,
                'p95_ms' => round($stats['p95_acc'] / $stats['p95_n'], 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<float>  $values
     */
    private function percentile(array $values, float $p): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $idx = (int) ceil(($p / 100) * count($values)) - 1;

        return round($values[max(0, min($idx, count($values) - 1))], 2);
    }
}

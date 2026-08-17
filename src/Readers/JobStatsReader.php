<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

class JobStatsReader
{
    /**
     * @return array<string, mixed>
     */
    public function stats(?string $date = null): array
    {
        $date = $this->normalizeDate($date);
        $rows = $this->readRows($this->fileFor($date));

        $processedMs = [];
        $waitMs = [];
        $byClass = [];
        $processedCount = 0;
        $failedCount = 0;
        $queuedCount = 0;
        $firstProcessed = null;
        $lastProcessed = null;

        foreach ($rows as $row) {
            $event = (string) ($row['event'] ?? '');
            $name = (string) ($row['name'] ?? 'unknown');

            if (! isset($byClass[$name])) {
                $byClass[$name] = [
                    'name' => $name,
                    'queued' => 0,
                    'processed' => 0,
                    'failed' => 0,
                    'total_ms' => 0.0,
                    'max_ms' => 0.0,
                    'wait_total_ms' => 0.0,
                    'wait_count' => 0,
                ];
            }

            if ($event === 'queued') {
                $queuedCount++;
                $byClass[$name]['queued']++;

                continue;
            }

            $ms = isset($row['ms']) ? (float) $row['ms'] : null;
            $wait = isset($row['wait_ms']) ? (float) $row['wait_ms'] : null;
            $t = (string) ($row['t'] ?? '');

            if ($event === 'processed') {
                $processedCount++;
                $byClass[$name]['processed']++;
                if ($firstProcessed === null || ($t !== '' && $t < $firstProcessed)) {
                    $firstProcessed = $t;
                }
                if ($lastProcessed === null || ($t !== '' && $t > $lastProcessed)) {
                    $lastProcessed = $t;
                }
            } elseif ($event === 'failed') {
                $failedCount++;
                $byClass[$name]['failed']++;
            } else {
                continue;
            }

            if ($ms !== null) {
                $processedMs[] = $ms;
                $byClass[$name]['total_ms'] += $ms;
                $byClass[$name]['max_ms'] = max($byClass[$name]['max_ms'], $ms);
            }

            if ($wait !== null) {
                $waitMs[] = $wait;
                $byClass[$name]['wait_total_ms'] += $wait;
                $byClass[$name]['wait_count']++;
            }
        }

        $classes = [];
        foreach ($byClass as $stats) {
            $done = $stats['processed'] + $stats['failed'];
            $stats['avg_ms'] = $done > 0 && $stats['total_ms'] > 0
                ? round($stats['total_ms'] / $done, 2)
                : 0.0;
            $stats['avg_wait_ms'] = $stats['wait_count'] > 0
                ? round($stats['wait_total_ms'] / $stats['wait_count'], 2)
                : null;
            $stats['max_ms'] = round((float) $stats['max_ms'], 2);
            unset($stats['wait_total_ms'], $stats['wait_count'], $stats['total_ms']);
            $classes[] = $stats;
        }

        usort($classes, static function (array $a, array $b): int {
            return ($b['failed'] <=> $a['failed'])
                ?: ($b['processed'] <=> $a['processed'])
                ?: ($b['avg_ms'] <=> $a['avg_ms']);
        });

        $spanSeconds = $this->spanSeconds($firstProcessed, $lastProcessed);
        $jobsPerMinute = null;
        if ($processedCount > 0 && $spanSeconds !== null && $spanSeconds > 0) {
            $jobsPerMinute = round($processedCount / ($spanSeconds / 60), 2);
        } elseif ($processedCount > 0) {
            $jobsPerMinute = (float) $processedCount;
        }

        return [
            'throughput' => [
                'queued' => $queuedCount,
                'processed' => $processedCount,
                'failed' => $failedCount,
                'jobs_per_minute' => $jobsPerMinute,
                'first_processed_at' => $firstProcessed,
                'last_processed_at' => $lastProcessed,
            ],
            'runtime' => [
                'avg_ms' => $this->average($processedMs),
                'p95_ms' => $this->percentile($processedMs, 95),
                'max_ms' => $processedMs === [] ? null : round(max($processedMs), 2),
            ],
            'wait' => [
                'avg_ms' => $this->average($waitMs),
                'p95_ms' => $this->percentile($waitMs, 95),
            ],
            'by_class' => array_slice($classes, 0, 50),
            'failed_analytics' => array_values(array_filter(
                $classes,
                static fn (array $row): bool => $row['failed'] > 0
            )),
            'meta' => [
                'date' => $date,
                'total_events' => count($rows),
                'dates' => $this->availableDates(),
            ],
        ];
    }

    /**
     * @param  list<float>  $values
     */
    private function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
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

    private function spanSeconds(?string $first, ?string $last): ?float
    {
        if ($first === null || $last === null || $first === $last) {
            return null;
        }

        $a = strtotime($first);
        $b = strtotime($last);

        if ($a === false || $b === false || $b <= $a) {
            return null;
        }

        return (float) ($b - $a);
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

        foreach (glob($dir . '/jobs-*.jsonl') ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        rsort($dates);

        return $dates;
    }

    private function fileFor(string $date): string
    {
        return config('spoke.storage_path') . '/jobs-' . $date . '.jsonl';
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

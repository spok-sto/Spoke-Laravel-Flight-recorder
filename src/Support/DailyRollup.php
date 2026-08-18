<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

/**
 * Daily aggregate for one date, kept so long-term trends survive the raw
 * telemetry retention window.
 */
class DailyRollup
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $date): array
    {
        $row = ['date' => $date, 'generated_at' => now()->format('Y-m-d H:i:s')];

        $row += $this->requests($date);
        $row += $this->queries($date);
        $row += $this->exceptions($date);
        $row += $this->jobs($date);
        $row += $this->metrics($date);

        return $row;
    }

    /**
     * Writing is idempotent: re-running a date replaces the existing row.
     *
     * @param  array<string, mixed>  $row
     */
    public function store(array $row): bool
    {
        try {
            $date = (string) ($row['date'] ?? '');

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return false;
            }

            $dir = JsonlFile::rollupDir();

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
                @chmod($dir, 0775);
            }

            $file = JsonlFile::rollupPath(substr($date, 0, 7));
            $rows = array_values(array_filter(
                JsonlFile::rows($file),
                static fn (array $existing): bool => ($existing['date'] ?? null) !== $date
            ));
            $rows[] = $row;

            usort($rows, static fn (array $a, array $b): int => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

            $lines = '';

            foreach ($rows as $entry) {
                $encoded = json_encode(
                    $entry,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );

                if ($encoded !== false) {
                    $lines .= $encoded . PHP_EOL;
                }
            }

            return file_put_contents($file, $lines, LOCK_EX) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requests(string $date): array
    {
        $durations = [];
        $errors = 0;
        $nPlusOne = 0;

        foreach (JsonlFile::rows(JsonlFile::path('requests', $date)) as $row) {
            $durations[] = (float) ($row['ms'] ?? 0);

            if ((int) ($row['status'] ?? 0) >= 500) {
                $errors++;
            }

            $summary = $row['summary'] ?? null;

            if (is_array($summary) && (int) ($summary['n_plus_one_count'] ?? 0) > 0) {
                $nPlusOne++;
            }
        }

        return [
            'requests_count' => count($durations),
            'requests_avg_ms' => $durations === [] ? null : round(array_sum($durations) / count($durations), 2),
            'requests_p95_ms' => $this->percentile($durations, 95),
            'requests_error_count' => $errors,
            'requests_n_plus_one_count' => $nPlusOne,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queries(string $date): array
    {
        $slowMs = (float) config('spoke.recorders.queries.slow_only_ms', 50);
        $count = 0;
        $slow = 0;
        $totalMs = 0.0;

        foreach (JsonlFile::rows(JsonlFile::path('queries', $date)) as $row) {
            $ms = (float) ($row['ms'] ?? 0);
            $count++;
            $totalMs += $ms;

            if ($ms >= $slowMs) {
                $slow++;
            }
        }

        return [
            'queries_count' => $count,
            'queries_slow_count' => $slow,
            'queries_total_ms' => round($totalMs, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptions(string $date): array
    {
        $count = 0;
        $fingerprints = [];

        foreach (JsonlFile::rows(JsonlFile::path('exceptions', $date)) as $row) {
            $count++;
            $fingerprint = $row['fingerprint'] ?? null;

            if (is_string($fingerprint) && $fingerprint !== '') {
                $fingerprints[$fingerprint] = true;
            }
        }

        return [
            'exceptions_count' => $count,
            'exceptions_groups' => count($fingerprints),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobs(string $date): array
    {
        $processed = 0;
        $failed = 0;

        foreach (JsonlFile::rows(JsonlFile::path('jobs', $date)) as $row) {
            $event = (string) ($row['event'] ?? '');

            if ($event === 'processed') {
                $processed++;
            } elseif ($event === 'failed') {
                $failed++;
            }
        }

        return [
            'jobs_processed_count' => $processed,
            'jobs_failed_count' => $failed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(string $date): array
    {
        $keys = ['load_pct', 'mem_used_pct', 'disk_used_pct', 'queue_pending'];
        $values = [];

        foreach (JsonlFile::rows(JsonlFile::path('metrics', $date)) as $row) {
            foreach ($keys as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $values[$key][] = (float) $row[$key];
                }
            }
        }

        $out = ['metrics_samples' => isset($values['load_pct']) ? count($values['load_pct']) : 0];

        foreach ($keys as $key) {
            $series = $values[$key] ?? [];
            $out[$key . '_avg'] = $series === [] ? null : round(array_sum($series) / count($series), 2);
            $out[$key . '_max'] = $series === [] ? null : round(max($series), 2);
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

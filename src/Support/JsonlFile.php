<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

class JsonlFile
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $maxBytes = (int) config('spoke.max_read_bytes', 20 * 1024 * 1024);
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
     * @return array<string, mixed>|null
     */
    public static function lastRow(string $file): ?array
    {
        $rows = self::rows($file);

        if ($rows === []) {
            return null;
        }

        return $rows[array_key_last($rows)];
    }

    /**
     * @return list<string>
     */
    public static function dates(string $type): array
    {
        $dir = (string) config('spoke.storage_path');
        $dates = [];

        foreach (glob($dir . '/' . $type . '-*.jsonl') ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        rsort($dates);

        return $dates;
    }

    public static function path(string $type, string $date): string
    {
        return (string) config('spoke.storage_path') . '/' . $type . '-' . $date . '.jsonl';
    }

    /**
     * Daily rollups live in a subdirectory so the raw telemetry retention does not
     * delete them together with the `*-YYYY-MM-DD.jsonl` files.
     */
    public static function rollupDir(): string
    {
        return (string) config('spoke.storage_path') . '/rollups';
    }

    public static function rollupPath(string $month): string
    {
        return self::rollupDir() . '/daily-' . $month . '.jsonl';
    }

    /**
     * @return list<string>
     */
    public static function rollupMonths(): array
    {
        $months = [];

        foreach (glob(self::rollupDir() . '/daily-*.jsonl') ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2})\.jsonl$/', $file, $m)) {
                $months[] = $m[1];
            }
        }

        rsort($months);

        return $months;
    }

    public static function normalizeDate(?string $date): string
    {
        $date = $date ?: date('Y-m-d');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return date('Y-m-d');
        }

        return $date;
    }
}

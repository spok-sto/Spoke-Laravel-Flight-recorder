<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

class JsonlWriter
{
    private static bool $retentionDone = false;

    public function write(string $type, array $record): void
    {
        try {
            $dir = config('spoke.storage_path');

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
                @chmod($dir, 0775);
            }

            $this->pruneOldFiles();

            $file = $dir . '/' . $type . '-' . date('Y-m-d') . '.jsonl';

            $line = json_encode(
                $record,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if ($line !== false) {
                file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (Throwable $e) {
        }
    }

    public function prune(?int $days = null): int
    {
        $days ??= (int) config('spoke.retention_days', 7);
        $cutoff = time() - $days * 86400;
        $metricsCutoff = time() - max(1, (int) config('spoke.metrics.retention_days', $days)) * 86400;
        $deleted = 0;

        $dir = config('spoke.storage_path');

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.jsonl') ?: [] as $file) {
                $limit = str_starts_with(basename($file), 'metrics-') ? $metricsCutoff : $cutoff;

                if (filemtime($file) < $limit && @unlink($file)) {
                    $deleted++;
                }
            }
        }

        $deleted += $this->pruneRollups();

        $mailDir = config('spoke.mail_body_dir');

        if (is_dir($mailDir)) {
            foreach (glob($mailDir . '/*.html') ?: [] as $file) {
                if (filemtime($file) < $cutoff && @unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Rollups use their own, longer retention window.
     */
    private function pruneRollups(): int
    {
        $dir = JsonlFile::rollupDir();

        if (! is_dir($dir)) {
            return 0;
        }

        $cutoff = time() - max(1, (int) config('spoke.rollup.retention_days', 90)) * 86400;
        $deleted = 0;

        foreach (glob($dir . '/daily-*.jsonl') ?: [] as $file) {
            if (filemtime($file) < $cutoff && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function pruneOldFiles(): void
    {
        if (self::$retentionDone) {
            return;
        }

        self::$retentionDone = true;

        $this->prune();
    }
}

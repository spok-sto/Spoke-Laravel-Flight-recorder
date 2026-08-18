<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Konekt\Spoke\Support\JsonlFile;

/**
 * Reads daily rollups, which outlive the raw telemetry retention window.
 */
class RollupReader
{
    /**
     * @return array<string, mixed>
     */
    public function daily(?int $days = null): array
    {
        $days = max(1, min(365, $days ?? 30));
        $cutoff = date('Y-m-d', time() - ($days * 86400));
        $rows = [];

        foreach (JsonlFile::rollupMonths() as $month) {
            foreach (JsonlFile::rows(JsonlFile::rollupPath($month)) as $row) {
                $date = (string) ($row['date'] ?? '');

                if ($date !== '' && $date >= $cutoff) {
                    $rows[$date] = $row;
                }
            }
        }

        ksort($rows);

        return [
            'data' => array_values($rows),
            'meta' => [
                'enabled' => (bool) config('spoke.rollup.enabled', false),
                'days' => $days,
                'retention_days' => (int) config('spoke.rollup.retention_days', 90),
                'available_days' => count($rows),
                'command' => 'spoke:rollup',
            ],
        ];
    }
}

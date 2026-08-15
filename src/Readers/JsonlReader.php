<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

class JsonlReader
{
    public function read(string $type, ?string $date = null, ?string $search = null, int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?: (int) config('spoke.per_page', 50);
        $allowedTypes = ['queries', 'requests', 'mails', 'redis', 'http', 'jobs', 'exceptions', 'scheduler', 'commands', 'deploys', 'capture'];
        $type = in_array($type, $allowedTypes, true) ? $type : 'queries';
        $date = $date ?: date('Y-m-d');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $file = config('spoke.storage_path') . '/' . $type . '-' . $date . '.jsonl';

        $lines = $this->tailLines($file);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $lines = array_values(array_filter(
                $lines,
                fn (string $line) => str_contains(mb_strtolower($line), $needle)
            ));
        }

        $total = count($lines);
        $slice = array_slice($lines, ($page - 1) * $perPage, $perPage);

        $data = [];

        foreach ($slice as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $data[] = $decoded;
            }
        }

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'date' => $date,
                'dates' => $this->availableDates($type),
            ],
        ];
    }

    private function tailLines(string $file): array
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

        return array_reverse(array_values(array_filter($lines, fn ($l) => $l !== '')));
    }

    private function availableDates(string $type): array
    {
        $dir = config('spoke.storage_path');
        $dates = [];

        foreach (glob($dir . '/' . $type . '-*.jsonl') ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        rsort($dates);

        return $dates;
    }
}

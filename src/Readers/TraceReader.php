<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

class TraceReader
{
    /**
     * @return array{
     *     found: bool,
     *     date: string,
     *     trace_id: string,
     *     request: array<string, mixed>|null,
     *     timeline: list<array<string, mixed>>,
     *     queries: list<array<string, mixed>>,
     *     redis: list<array<string, mixed>>,
     *     http: list<array<string, mixed>>,
     *     jobs: list<array<string, mixed>>,
     *     exceptions: list<array<string, mixed>>,
     *     payloads: list<array<string, mixed>>
     * }
     */
    public function find(string $traceId, ?string $date = null): array
    {
        $date = $this->normalizeDate($date);
        $request = $this->findByTraceId('requests', $date, $traceId);
        $queries = $this->findAllByTraceId('queries', $date, $traceId);
        $redis = $this->findAllByTraceId('redis', $date, $traceId);
        $http = $this->findAllByTraceId('http', $date, $traceId);
        $jobs = $this->findAllByTraceId('jobs', $date, $traceId);
        $exceptions = $this->findAllByTraceId('exceptions', $date, $traceId);
        $payloads = $this->findAllByTraceId('capture', $date, $traceId);

        [$request, $http] = $this->mergeCapturedPayloads($request, $http, $payloads);

        $timeline = [];

        if (is_array($request)) {
            $timeline[] = array_merge($request, ['type' => 'request']);
        }

        foreach ($queries as $row) {
            $timeline[] = array_merge($row, ['type' => 'query']);
        }

        foreach ($redis as $row) {
            $timeline[] = array_merge($row, ['type' => 'redis']);
        }

        foreach ($http as $row) {
            $timeline[] = array_merge($row, ['type' => 'http']);
        }

        foreach ($jobs as $row) {
            $timeline[] = array_merge($row, ['type' => 'job']);
        }

        foreach ($exceptions as $row) {
            $timeline[] = array_merge($row, ['type' => 'exception']);
        }

        usort($timeline, static function (array $a, array $b): int {
            return strcmp((string) ($a['t'] ?? ''), (string) ($b['t'] ?? ''));
        });

        return [
            'found' => is_array($request) || $queries !== [] || $redis !== [] || $http !== []
                || $jobs !== [] || $exceptions !== [],
            'date' => $date,
            'trace_id' => $traceId,
            'request' => $request,
            'timeline' => $timeline,
            'queries' => $queries,
            'redis' => $redis,
            'http' => $http,
            'jobs' => $jobs,
            'exceptions' => $exceptions,
            'payloads' => $payloads,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @param  list<array<string, mixed>>  $http
     * @param  list<array<string, mixed>>  $payloads
     * @return array{0: array<string, mixed>|null, 1: list<array<string, mixed>>}
     */
    private function mergeCapturedPayloads(?array $request, array $http, array $payloads): array
    {
        $httpCaps = [];

        foreach ($payloads as $cap) {
            $kind = $cap['kind'] ?? '';

            if ($kind === 'request' && is_array($request) && empty($request['body']) && ! empty($cap['body'])) {
                $request['body'] = $cap['body'];
                $request['captured'] = true;
            }

            if ($kind === 'http_out') {
                $httpCaps[] = $cap;
            }
        }

        foreach ($http as $i => $row) {
            foreach ($httpCaps as $j => $cap) {
                if (($cap['url'] ?? '') !== ($row['url'] ?? '') || ($cap['method'] ?? '') !== ($row['method'] ?? '')) {
                    continue;
                }

                $http[$i]['request_body'] = $cap['request_body'] ?? $row['request_body'] ?? null;
                $http[$i]['response_body'] = $cap['response_body'] ?? $row['response_body'] ?? null;
                $http[$i]['captured'] = true;
                unset($httpCaps[$j]);
                break;
            }
        }

        return [$request, $http];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByTraceId(string $type, string $date, string $traceId): ?array
    {
        foreach ($this->readLines($type, $date) as $row) {
            if (($row['trace_id'] ?? null) === $traceId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findAllByTraceId(string $type, string $date, string $traceId): array
    {
        $matches = [];

        foreach ($this->readLines($type, $date) as $row) {
            if (($row['trace_id'] ?? null) === $traceId) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readLines(string $type, string $date): array
    {
        $file = config('spoke.storage_path') . '/' . $type . '-' . $date . '.jsonl';

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

    private function normalizeDate(?string $date): string
    {
        $date = $date ?: date('Y-m-d');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return date('Y-m-d');
        }

        return $date;
    }
}

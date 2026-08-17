<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Database\Events\QueryExecuted;
use Konekt\Spoke\Support\JsonlWriter;
use Konekt\Spoke\Support\QueryNormalizer;
use Konekt\Spoke\Support\TraceContext;
use Throwable;

class QueryRecorder
{
    public function __construct(private JsonlWriter $writer)
    {
    }

    public function record(QueryExecuted $query): void
    {
        try {
            $isCli = app()->runningInConsole();

            if ($isCli && ! config('spoke.recorders.queries.record_cli')) {
                return;
            }

            if ($this->isIgnoredRequest()) {
                return;
            }

            if (preg_match('/^\s*EXPLAIN\b/i', $query->sql) === 1) {
                return;
            }

            $slowOnly = config('spoke.recorders.queries.slow_only_ms');
            $isSlow = $slowOnly === null
                || $slowOnly === ''
                || $query->time >= (float) $slowOnly;

            $record = [
                't' => now()->format('Y-m-d H:i:s.v'),
                'conn' => $query->connectionName,
                'sql' => mb_substr($query->sql, 0, 5000),
                'bindings' => $this->truncateBindings($query->bindings),
                'ms' => round($query->time, 2),
                'origin' => $isCli ? 'cli' : 'http',
                'uri' => $this->currentUri(),
                'fingerprint' => QueryNormalizer::fingerprint($query->sql),
            ];

            if (! $isCli) {

                $trace = app(TraceContext::class);
                $record['trace_id'] = $trace->traceId();
                $buffered = $record;
                $buffered['already_written'] = $isSlow;
                $trace->bufferQuery($buffered);
            }

            if (! $isSlow) {
                return;
            }

            if ($isCli) {
                $this->writer->write('queries', $record);

                return;
            }

            $this->writer->write('queries', $record);
        } catch (Throwable $e) {
        }
    }

    private function truncateBindings(array $bindings): array
    {
        return array_map(function ($value) {
            if (is_string($value) && mb_strlen($value) > 500) {
                return mb_substr($value, 0, 500) . '…';
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            if (is_scalar($value) || $value === null) {
                return $value;
            }

            return '[' . get_debug_type($value) . ']';
        }, array_slice($bindings, 0, 50));
    }

    private function isIgnoredRequest(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        $path = request()->path();

        foreach (config('spoke.ignore_paths', []) as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function currentUri(): ?string
    {
        if (app()->runningInConsole()) {
            return implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 3)) ?: null;
        }

        return '/' . request()->path();
    }
}

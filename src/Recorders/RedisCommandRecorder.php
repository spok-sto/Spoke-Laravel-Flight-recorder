<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Redis\Events\CommandExecuted;
use Konekt\Spoke\Support\JsonlWriter;
use Konekt\Spoke\Support\TraceContext;
use Throwable;

/**
 * Snima Redis komande u redis-*.jsonl (isti pattern kao QueryRecorder).
 */
class RedisCommandRecorder
{
    public function __construct(private JsonlWriter $writer)
    {
    }

    public function record(CommandExecuted $event): void
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }

            if ($this->isIgnoredRequest()) {
                return;
            }

            $ms = round((float) $event->time, 2);
            $slowOnly = config('spoke.recorders.redis.slow_only_ms', 5);
            $isSlow = $slowOnly === null
                || $slowOnly === ''
                || $ms >= (float) $slowOnly;

            /** @var TraceContext $trace */
            $trace = app(TraceContext::class);

            $record = [
                't' => now()->format('Y-m-d H:i:s.v'),
                'trace_id' => $trace->traceId(),
                'conn' => $event->connectionName,
                'command' => strtoupper((string) $event->command),
                'parameters' => $this->truncateParameters($event->parameters),
                'ms' => $ms,
                'uri' => '/' . request()->path(),
                'already_written' => $isSlow,
            ];

            $trace->bufferRedis($record);

            if (! $isSlow) {
                return;
            }

            $this->writer->write('redis', $this->persistable($record));
        } catch (Throwable $e) {
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function persistable(array $record): array
    {
        unset($record['already_written']);

        return $record;
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return list<mixed>
     */
    private function truncateParameters(array $parameters): array
    {
        $out = [];

        foreach (array_slice($parameters, 0, 20) as $value) {
            if (is_string($value) && mb_strlen($value) > 200) {
                $out[] = mb_substr($value, 0, 200) . '…';

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $out[] = $value;

                continue;
            }

            $out[] = '[' . get_debug_type($value) . ']';
        }

        return $out;
    }

    private function isIgnoredRequest(): bool
    {
        $path = request()->path();

        foreach (config('spoke.ignore_paths', []) as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}

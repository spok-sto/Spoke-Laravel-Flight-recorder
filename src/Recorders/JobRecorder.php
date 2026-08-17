<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Closure;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Queue;
use Konekt\Spoke\Support\JsonlWriter;
use Konekt\Spoke\Support\TraceContext;
use Throwable;

class JobRecorder
{
    private array $processingStarted = [];

    public function __construct(private JsonlWriter $writer)
    {
    }

    public static function registerPayloadHooks(): void
    {
        Queue::createPayloadUsing(static function () {
            $extra = [
                'spoke_queued_at' => microtime(true),
            ];

            if (app()->runningInConsole()) {
                return $extra;
            }

            try {
                $path = request()->path();
            } catch (Throwable $e) {
                return $extra;
            }

            foreach (config('spoke.ignore_paths', []) as $pattern) {
                if (fnmatch((string) $pattern, $path)) {
                    return $extra;
                }
            }

            $extra['spoke_trace_id'] = app(TraceContext::class)->traceId();

            return $extra;
        });
    }

    public function recordQueued(JobQueued $event): void
    {
        try {
            if ($this->isIgnoredHttpRequest()) {
                return;
            }

            $name = $this->dispatchedJobName($event->job);
            $record = [
                't' => now()->format('Y-m-d H:i:s.v'),
                'event' => 'queued',
                'name' => $name,
                'connection' => $event->connectionName,
                'queue' => $this->queuedQueueName($event),
                'job_id' => $event->id !== null ? (string) $event->id : null,
            ];

            $this->attachHttpTrace($record);
            $this->writer->write('jobs', $record);
        } catch (Throwable $e) {
        }
    }

    public function recordProcessing(JobProcessing $event): void
    {
        try {
            $key = $this->queueJobKey($event->job);
            $this->processingStarted[$key] = microtime(true);

            if (count($this->processingStarted) > 200) {
                $this->processingStarted = array_slice($this->processingStarted, -100, 100, true);
            }
        } catch (Throwable $e) {
        }
    }

    public function recordProcessed(JobProcessed $event): void
    {
        try {
            $this->persistQueueJob($event->job, $event->connectionName, 'processed');
        } catch (Throwable $e) {
        }
    }

    public function recordFailed(JobFailed $event): void
    {
        try {
            $this->persistQueueJob(
                $event->job,
                $event->connectionName,
                'failed',
                $event->exception
            );
        } catch (Throwable $e) {
        }
    }

    private function persistQueueJob(
        QueueJob $job,
        string $connectionName,
        string $eventName,
        ?Throwable $exception = null
    ): void {
        $key = $this->queueJobKey($job);
        $started = $this->processingStarted[$key] ?? null;
        unset($this->processingStarted[$key]);

        $payload = [];
        try {
            $payload = $job->payload();
        } catch (Throwable $e) {
            $payload = [];
        }
        $payload = is_array($payload) ? $payload : [];

        $ms = $started !== null
            ? round((microtime(true) - $started) * 1000, 2)
            : null;

        $queuedAt = $payload['spoke_queued_at'] ?? null;
        $waitMs = is_numeric($queuedAt)
            ? max(0, round((($started ?? microtime(true)) - (float) $queuedAt) * 1000, 2))
            : null;

        $traceId = isset($payload['spoke_trace_id']) && is_string($payload['spoke_trace_id'])
            ? $payload['spoke_trace_id']
            : null;

        if ($traceId === null && ! app()->runningInConsole()) {
            $traceId = app(TraceContext::class)->traceId();
        }

        $record = [
            't' => now()->format('Y-m-d H:i:s.v'),
            'event' => $eventName,
            'name' => $this->queueJobName($job),
            'connection' => $connectionName,
            'queue' => $job->getQueue() ?: 'default',
            'job_id' => $job->uuid() ?: $job->getJobId(),
            'attempts' => $job->attempts(),
            'ms' => $ms,
            'wait_ms' => $waitMs,
            'trace_id' => $traceId,
        ];

        if ($exception instanceof Throwable) {
            $record['exception_class'] = $exception::class;
            $record['exception'] = mb_substr($exception->getMessage(), 0, 500);
        }

        if ($traceId !== null && ! app()->runningInConsole()) {
            app(TraceContext::class)->bufferJob($record);
        }

        $this->writer->write('jobs', $record);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function attachHttpTrace(array &$record): void
    {
        if (app()->runningInConsole()) {
            $current = app(TraceContext::class)->currentTraceId();
            if ($current !== null) {
                $record['trace_id'] = $current;
            }

            return;
        }

        $trace = app(TraceContext::class);
        $record['trace_id'] = $trace->traceId();
        $trace->bufferJob($record);
    }

    private function dispatchedJobName(mixed $job): string
    {
        if ($job instanceof Closure) {
            return 'Closure';
        }

        if (is_string($job)) {
            return $job;
        }

        if (is_object($job)) {
            return $job::class;
        }

        return 'unknown';
    }

    private function queueJobName(QueueJob $job): string
    {
        try {
            return $job->resolveName();
        } catch (Throwable $e) {
            return $job->getName();
        }
    }

    private function queueJobKey(QueueJob $job): string
    {
        return (string) ($job->uuid() ?: $job->getJobId() ?: spl_object_id($job));
    }

    private function queuedQueueName(JobQueued $event): string
    {
        if (property_exists($event, 'queue') && is_string($event->queue) && $event->queue !== '') {
            return $event->queue;
        }

        $job = $event->job;
        if (is_object($job) && isset($job->queue) && is_string($job->queue) && $job->queue !== '') {
            return $job->queue;
        }

        return (string) config('queue.connections.' . $event->connectionName . '.queue', 'default');
    }

    private function isIgnoredHttpRequest(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        try {
            $path = request()->path();
        } catch (Throwable $e) {
            return false;
        }

        foreach (config('spoke.ignore_paths', []) as $pattern) {
            if (fnmatch((string) $pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Konekt\Spoke\Support\JsonlWriter;
use Throwable;

/**
 * Snima Laravel scheduler taskove u scheduler-*.jsonl.
 */
class SchedulerRecorder
{
    public function __construct(private JsonlWriter $writer)
    {
    }

    /**
     * Uspešno završen scheduled task.
     */
    public function recordFinished(ScheduledTaskFinished $event): void
    {
        try {
            $this->write($event->task, (float) $event->runtime, 'finished');
        } catch (Throwable $e) {
        }
    }

    /**
     * Scheduled task bacio exception.
     */
    public function recordFailed(ScheduledTaskFailed $event): void
    {
        try {
            $this->write($event->task, null, 'failed', $event->exception);
        } catch (Throwable $e) {
        }
    }

    private function write(
        ScheduledEvent $task,
        ?float $runtimeSeconds,
        string $eventName,
        ?Throwable $exception = null
    ): void {
        $record = [
            't' => now()->format('Y-m-d H:i:s.v'),
            'event' => $eventName,
            'command' => mb_substr((string) ($task->command ?? ''), 0, 500),
            'description' => mb_substr($task->getSummaryForDisplay(), 0, 500),
            'expression' => $task->expression,
            'ms' => $runtimeSeconds !== null ? round($runtimeSeconds * 1000, 2) : null,
        ];

        if ($exception instanceof Throwable) {
            $record['exception_class'] = $exception::class;
            $record['exception'] = mb_substr($exception->getMessage(), 0, 500);
        }

        $this->writer->write('scheduler', $record);
    }
}

<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Log\Events\MessageLogged;
use Konekt\Spoke\Support\ExceptionNormalizer;
use Konekt\Spoke\Support\JsonlWriter;
use Konekt\Spoke\Support\TraceContext;
use Throwable;

/**
 * Snima exception iz log konteksta (MessageLogged) u exceptions-*.jsonl.
 */
class ExceptionRecorder
{
    public function __construct(private JsonlWriter $writer)
    {
    }

    /**
     * Uhvati Throwable iz log context-a (Laravel ExceptionHandler).
     */
    public function record(MessageLogged $event): void
    {
        try {
            $exception = $event->context['exception'] ?? null;

            if (! $exception instanceof Throwable) {
                return;
            }

            if ($this->isIgnoredHttpRequest()) {
                return;
            }

            $class = $exception::class;
            $message = mb_substr($exception->getMessage(), 0, 500);
            $fingerprint = ExceptionNormalizer::fingerprint($exception);

            $record = [
                't' => now()->format('Y-m-d H:i:s.v'),
                'level' => $event->level,
                'class' => $class,
                'message' => $message,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'stack' => mb_substr($exception->__toString(), 0, 8000),
                'fingerprint' => $fingerprint,
                'uri' => $this->currentUri(),
            ];

            if (! app()->runningInConsole()) {
                $trace = app(TraceContext::class);
                if (! $trace->rememberExceptionFingerprint($fingerprint)) {
                    return;
                }
                $record['trace_id'] = $trace->traceId();
                $trace->bufferException($record);
            } else {
                $current = app(TraceContext::class)->currentTraceId();
                if ($current !== null) {
                    $record['trace_id'] = $current;
                }
            }

            $this->writer->write('exceptions', $record);
        } catch (Throwable $e) {
        }
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

    private function currentUri(): ?string
    {
        if (app()->runningInConsole()) {
            return implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 3)) ?: null;
        }

        try {
            return '/' . request()->path();
        } catch (Throwable $e) {
            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Response;
use Konekt\Spoke\Support\BodyRedactor;
use Konekt\Spoke\Support\CaptureMode;
use Konekt\Spoke\Support\JsonlWriter;
use Konekt\Spoke\Support\TraceContext;
use Throwable;

class HttpClientRecorder
{
    public function __construct(
        private JsonlWriter $writer,
        private CaptureMode $capture,
    ) {
    }

    public function recordSending(RequestSending $event): void
    {
        try {
            if (app()->runningInConsole() || $this->isIgnoredRequest()) {
                return;
            }

            $trace = app(TraceContext::class);
            $request = $event->request;

            $trace->beginHttp(spl_object_id($request), [
                't' => now()->format('Y-m-d H:i:s.v'),
                'trace_id' => $trace->traceId(),
                'method' => $request->method(),
                'url' => mb_substr($request->url(), 0, 2000),
                'request_headers' => $this->redactHeaders($request->headers()),
                'request_body' => $this->truncateBody($request->body()),
                'uri' => '/' . request()->path(),
                '_started_at' => microtime(true),
            ]);
        } catch (Throwable $e) {
        }
    }

    public function recordReceived(ResponseReceived $event): void
    {
        try {
            if (app()->runningInConsole() || $this->isIgnoredRequest()) {
                return;
            }

            $trace = app(TraceContext::class);
            $objectId = spl_object_id($event->request);
            $pending = $trace->peekPendingHttp($objectId);
            $startedAt = isset($pending['_started_at']) ? (float) $pending['_started_at'] : null;

            $ms = $startedAt !== null
                ? round((microtime(true) - $startedAt) * 1000, 2)
                : $this->transferMs($event->response);

            $slowOnly = config('spoke.recorders.http_client.slow_only_ms', 200);
            $isSlow = $slowOnly === null
                || $slowOnly === ''
                || $ms >= (float) $slowOnly
                || $this->capture->active();

            $record = $trace->completeHttp($objectId, [
                'ms' => $ms,
                'status' => $event->response->status(),
                'response_headers' => $this->redactHeaders($event->response->headers()),
                'response_body' => $this->truncateBody($event->response->body()),
                'failed' => false,
                'already_written' => $isSlow,
            ]);

            if (is_array($record)) {
                if ($this->capture->active()) {
                    $record = $this->splitCapturePayload($record);
                }

                if ($isSlow) {
                    $this->writer->write('http', $this->persistable($record));
                }
            }
        } catch (Throwable $e) {
        }
    }

    public function recordFailed(ConnectionFailed $event): void
    {
        try {
            if (app()->runningInConsole() || $this->isIgnoredRequest()) {
                return;
            }

            $trace = app(TraceContext::class);
            $objectId = spl_object_id($event->request);
            $pending = $trace->peekPendingHttp($objectId);
            $startedAt = isset($pending['_started_at']) ? (float) $pending['_started_at'] : null;
            $ms = $startedAt !== null
                ? round((microtime(true) - $startedAt) * 1000, 2)
                : 0.0;

            $record = $trace->failHttp($objectId, [
                'ms' => $ms,
                'status' => null,
                'failed' => true,
                'error' => 'connection_failed',
                'already_written' => true,
            ]);

            if (is_array($record)) {
                $this->writer->write('http', $this->persistable($record));
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function splitCapturePayload(array $record): array
    {
        $this->writer->write('capture', [
            't' => $record['t'] ?? now()->format('Y-m-d H:i:s.v'),
            'trace_id' => $record['trace_id'] ?? null,
            'kind' => 'http_out',
            'method' => $record['method'] ?? null,
            'url' => $record['url'] ?? null,
            'status' => $record['status'] ?? null,
            'request_body' => $record['request_body'] ?? null,
            'response_body' => $record['response_body'] ?? null,
        ]);

        $max = (int) config('spoke.recorders.http_client.max_body_bytes', 4096);
        $record['request_body'] = $this->clip($record['request_body'] ?? null, $max);
        $record['response_body'] = $this->clip($record['response_body'] ?? null, $max);
        $record['captured'] = true;

        return $record;
    }

    private function clip(?string $body, int $max): ?string
    {
        if ($body === null || mb_strlen($body) <= $max) {
            return $body;
        }

        return mb_substr($body, 0, $max) . '…';
    }

    private function transferMs(Response $response): float
    {
        $seconds = $response->transferStats?->getTransferTime();

        return $seconds !== null ? round($seconds * 1000, 2) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function persistable(array $record): array
    {
        unset($record['already_written'], $record['_started_at']);

        return $record;
    }

    /**
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, mixed>
     */
    private function redactHeaders(array $headers): array
    {
        $redact = array_map(
            'strtolower',
            config('spoke.recorders.http_client.redact_headers', [])
        );
        $out = [];

        foreach ($headers as $name => $value) {
            $key = (string) $name;

            if (in_array(strtolower($key), $redact, true)) {
                $out[$key] = ['[REDACTED]'];

                continue;
            }

            $out[$key] = is_array($value)
                ? array_slice(array_map('strval', $value), 0, 10)
                : [(string) $value];
        }

        return $out;
    }

    private function truncateBody(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $body = BodyRedactor::redact($body, config('spoke.redact_keys', []));

        $max = $this->capture->active()
            ? (int) config('spoke.capture.max_body_bytes', 262144)
            : (int) config('spoke.recorders.http_client.max_body_bytes', 4096);

        if (mb_strlen($body) > $max) {
            return mb_substr($body, 0, $max) . '…';
        }

        return $body;
    }

    private function isIgnoredRequest(): bool
    {
        try {
            $path = request()->path();
        } catch (Throwable $e) {
            return false;
        }

        foreach (config('spoke.ignore_paths', []) as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}

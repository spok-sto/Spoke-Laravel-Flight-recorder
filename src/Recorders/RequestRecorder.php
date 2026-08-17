<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Konekt\Spoke\Support\BodyRedactor;
use Konekt\Spoke\Support\CaptureMode;
use Konekt\Spoke\Support\JsonlWriter;
use Konekt\Spoke\Support\NPlusOneDetector;
use Konekt\Spoke\Support\QueryNormalizer;
use Konekt\Spoke\Support\TraceContext;
use Throwable;

class RequestRecorder
{
    public function __construct(
        private JsonlWriter $writer,
        private NPlusOneDetector $nPlusOneDetector,
        private CaptureMode $capture,
    ) {
    }

    public function record(RequestHandled $event): void
    {
        try {
            $path = $event->request->path();

            foreach (config('spoke.ignore_paths', []) as $pattern) {
                if (fnmatch($pattern, $path)) {
                    return;
                }
            }

            $trace = app(TraceContext::class);
            $traceId = $trace->traceId();
            $status = $event->response->getStatusCode();

            if ($this->sampledOut($status, $trace)) {
                return;
            }

            $durationMs = defined('LARAVEL_START')
                ? round((microtime(true) - LARAVEL_START) * 1000)
                : round((microtime(true) - $trace->startedAt()) * 1000);

            $queries = $trace->queries();
            $redisCommands = $trace->redisCommands();
            $httpCalls = $trace->httpCalls();
            $jobs = $trace->jobs();
            $exceptions = $trace->exceptions();

            $threshold = (int) config('spoke.recorders.queries.n_plus_one_threshold', 10);
            $nPlusOne = $this->nPlusOneDetector->detect($queries, $threshold);
            $nPlusOneSql = [];

            foreach ($nPlusOne as $group) {
                $nPlusOneSql[$group['normalized_sql']] = true;
            }

            $this->persistRemainingQueries($queries, $nPlusOneSql);
            $this->persistRemainingRedis($redisCommands);
            $this->persistRemainingHttp($httpCalls);

            $queryTotalMs = 0.0;
            $slowMs = (float) config('spoke.recorders.queries.slow_only_ms', 50);
            $slowQueries = 0;

            foreach ($queries as $query) {
                $ms = (float) ($query['ms'] ?? 0);
                $queryTotalMs += $ms;
                if ($ms >= $slowMs) {
                    $slowQueries++;
                }
            }

            $redisTotalMs = 0.0;
            foreach ($redisCommands as $command) {
                $redisTotalMs += (float) ($command['ms'] ?? 0);
            }

            $httpTotalMs = 0.0;
            $httpSummary = [];
            foreach ($httpCalls as $call) {
                $httpTotalMs += (float) ($call['ms'] ?? 0);
                if (count($httpSummary) < 8) {
                    $url = (string) ($call['url'] ?? '');
                    $httpSummary[] = [
                        'method' => $call['method'] ?? null,
                        'host' => parse_url($url, PHP_URL_HOST) ?: mb_substr($url, 0, 80),
                        'ms' => isset($call['ms']) ? round((float) $call['ms'], 2) : null,
                        'status' => $call['status'] ?? null,
                        'failed' => ! empty($call['failed']),
                    ];
                }
            }

            $peakMb = round(memory_get_peak_usage(true) / 1048576, 1);

            $jobSummary = [];
            foreach ($jobs as $job) {
                if (count($jobSummary) >= 8) {
                    break;
                }
                $jobSummary[] = [
                    'event' => $job['event'] ?? 'queued',
                    'name' => $job['name'] ?? null,
                    'queue' => $job['queue'] ?? null,
                    'ms' => isset($job['ms']) ? round((float) $job['ms'], 2) : null,
                ];
            }

            $exceptionSummary = null;
            if ($exceptions !== []) {
                $last = $exceptions[array_key_last($exceptions)];
                $exceptionSummary = [
                    'class' => $last['class'] ?? null,
                    'message' => $last['message'] ?? null,
                ];
            }

            $record = [
                't' => now()->format('Y-m-d H:i:s.v'),
                'trace_id' => $traceId,
                'method' => $event->request->method(),
                'uri' => '/' . $path,
                'status' => $status,
                'ms' => $durationMs,
                'user_id' => $event->request->user()?->id,
                'ip' => $event->request->ip(),
                'memory_mb' => $peakMb,
                'summary' => [
                    'queries_count' => count($queries),
                    'queries_total_ms' => round($queryTotalMs, 2),
                    'slow_queries' => $slowQueries,
                    'redis_count' => count($redisCommands),
                    'redis_total_ms' => round($redisTotalMs, 2),
                    'http_count' => count($httpCalls),
                    'http_total_ms' => round($httpTotalMs, 2),
                    'http_calls' => $httpSummary,
                    'n_plus_one_count' => count($nPlusOne),
                    'n_plus_one' => $nPlusOne,
                    'memory_start_mb' => $trace->memoryStartMb(),
                    'memory_peak_mb' => $peakMb,
                    'jobs_count' => count($jobs),
                    'jobs' => $jobSummary,
                    'exception' => $exceptionSummary,
                ],
            ];

            $body = $this->captureBody($event, $status);

            if ($body !== null) {
                if ($this->capture->active()) {
                    $this->writer->write('capture', [
                        't' => $record['t'],
                        'trace_id' => $traceId,
                        'kind' => 'request',
                        'method' => $record['method'],
                        'uri' => $record['uri'],
                        'status' => $status,
                        'body' => $body,
                    ]);
                    $record['captured'] = true;
                } else {
                    $record['body'] = $body;
                }
            }

            $this->writer->write('requests', $record);
        } catch (Throwable $e) {
        }
    }

    /**
     * @param  list<array<string, mixed>>  $queries
     * @param  array<string, true>  $nPlusOneSql
     */
    private function persistRemainingQueries(array $queries, array $nPlusOneSql): void
    {
        $maxPersisted = max(0, (int) config('spoke.recorders.queries.n_plus_one_max_persisted', 5));
        $written = [];

        foreach ($queries as $query) {
            if (! empty($query['already_written'])) {
                continue;
            }

            $normalized = QueryNormalizer::normalize((string) ($query['sql'] ?? ''));

            if (! isset($nPlusOneSql[$normalized])) {
                continue;
            }

            $written[$normalized] = ($written[$normalized] ?? 0) + 1;

            if ($written[$normalized] > $maxPersisted) {
                continue;
            }

            $row = $query;
            unset($row['already_written']);
            $row['n_plus_one'] = true;
            $this->writer->write('queries', $row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $commands
     */
    private function persistRemainingRedis(array $commands): void
    {
        $slowOnly = config('spoke.recorders.redis.slow_only_ms', 5);

        foreach ($commands as $command) {
            if (! empty($command['already_written'])) {
                continue;
            }

            $ms = (float) ($command['ms'] ?? 0);

            if ($slowOnly !== null && $slowOnly !== '' && $ms < (float) $slowOnly) {
                continue;
            }

            $row = $command;
            unset($row['already_written']);
            $this->writer->write('redis', $row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $calls
     */
    private function persistRemainingHttp(array $calls): void
    {
        $slowOnly = config('spoke.recorders.http_client.slow_only_ms', 200);
        $captureActive = $this->capture->active();

        foreach ($calls as $call) {
            if (! empty($call['already_written'])) {
                continue;
            }

            $failed = ! empty($call['failed']) || ! array_key_exists('status', $call);
            $ms = (float) ($call['ms'] ?? 0);
            $isSlow = $slowOnly === null || $slowOnly === '' || $ms >= (float) $slowOnly;

            if (! $failed && ! $isSlow && ! $captureActive) {
                continue;
            }

            $row = $call;
            unset($row['already_written'], $row['_started_at']);
            $this->writer->write('http', $row);
        }
    }

    private function sampledOut(int $status, TraceContext $trace): bool
    {
        $rate = (float) config('spoke.recorders.requests.sample_rate', 1.0);

        if ($rate >= 1.0 || $status >= 400 || $trace->exceptions() !== [] || $this->capture->active()) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) >= $rate;
    }

    private function captureBody(RequestHandled $event, int $status): ?string
    {
        $captureActive = $this->capture->active();

        if (! $captureActive && (! config('spoke.recorders.requests.record_body', false) || $status < 400)) {
            return null;
        }

        $contentType = (string) $event->request->headers->get('Content-Type', '');

        if (str_contains(strtolower($contentType), 'multipart/')) {
            return null;
        }

        $input = $event->request->input();

        if (! is_array($input) || $input === []) {
            return null;
        }

        $redacted = BodyRedactor::redactArray($input, config('spoke.redact_keys', []));
        $encoded = json_encode(
            $redacted,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($encoded === false) {
            return null;
        }

        $max = $captureActive
            ? (int) config('spoke.capture.max_body_bytes', 262144)
            : (int) config('spoke.recorders.requests.record_body_max_bytes', 8192);

        return mb_strlen($encoded) > $max ? mb_substr($encoded, 0, $max) . '…' : $encoded;
    }
}

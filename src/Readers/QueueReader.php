<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use DateTimeInterface;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Konekt\Spoke\Support\JobPayloadInspector;
use Throwable;
use Traversable;

class QueueReader
{
    public function overview(): array
    {
        $connectionName = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connectionName}.driver");

        return [
            'connection' => $connectionName,
            'driver' => $driver,
            'pending' => $this->pending($connectionName, $driver),
            'failed' => $this->failed(),
        ];
    }

    private function pending(string $connectionName, string $driver): array
    {
        if ($driver === 'database') {
            return $this->pendingFromDatabase($connectionName);
        }

        if ($driver === 'redis') {
            return $this->pendingFromRedis($connectionName);
        }

        return [
            'supported' => false,
            'driver' => $driver,
            'jobs' => [],
            'total' => 0,
        ];
    }

    private function pendingFromDatabase(string $connectionName): array
    {
        try {
            $database = config("queue.connections.{$connectionName}.connection");
            $table = (string) config("queue.connections.{$connectionName}.table", 'jobs');
            $query = DB::connection($database)->table($table);

            $jobs = (clone $query)
                ->select([
                    'id',
                    'queue',
                    'payload',
                    'attempts',
                    'available_at',
                    'created_at',
                ])
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(function ($job) {
                    $payload = $job->payload ?? null;

                    return [
                        'id' => $job->id,
                        'queue' => $job->queue,
                        'job' => $this->jobNameFromPayload($payload),
                        'attempts' => $job->attempts,
                        'created_at' => date('Y-m-d H:i:s', (int) $job->created_at),
                        'available_at' => date('Y-m-d H:i:s', (int) $job->available_at),
                        'status' => 'pending',
                        'payload' => JobPayloadInspector::inspect(is_string($payload) ? $payload : null),
                    ];
                })
                ->all();

            return [
                'supported' => true,
                'driver' => 'database',
                'connection' => $database,
                'jobs' => $jobs,
                'total' => (clone $query)->count(),
            ];
        } catch (Throwable $e) {
            return [
                'supported' => true,
                'driver' => 'database',
                'jobs' => [],
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function pendingFromRedis(string $connectionName): array
    {
        try {
            $queue = app('queue')->connection($connectionName);

            if (! $queue instanceof RedisQueue) {
                return [
                    'supported' => false,
                    'driver' => 'redis',
                    'jobs' => [],
                    'total' => 0,
                    'error' => 'Configured queue connection is not a RedisQueue instance.',
                ];
            }

            $queueName = (string) config("queue.connections.{$connectionName}.queue", 'default');
            $redis = $queue->getConnection();
            $queueKey = $queue->getQueue($queueName);
            $jobs = [];

            foreach ((array) $redis->lrange($queueKey, 0, 99) as $payload) {
                $jobs[] = $this->redisJob((string) $payload, $queueName, 'ready');
            }

            if (count($jobs) < 100) {
                $remaining = 99 - count($jobs);

                foreach ((array) $redis->zrange($queueKey . ':delayed', 0, $remaining) as $payload) {
                    $jobs[] = $this->redisJob((string) $payload, $queueName, 'delayed');
                }
            }

            if (count($jobs) < 100) {
                $remaining = 99 - count($jobs);

                foreach ((array) $redis->zrange($queueKey . ':reserved', 0, $remaining) as $payload) {
                    $jobs[] = $this->redisJob((string) $payload, $queueName, 'reserved');
                }
            }

            return [
                'supported' => true,
                'driver' => 'redis',
                'connection' => config("queue.connections.{$connectionName}.connection"),
                'queue' => $queueName,
                'jobs' => $jobs,
                'total' => (int) $queue->size($queueName),
            ];
        } catch (Throwable $e) {
            return [
                'supported' => true,
                'driver' => 'redis',
                'jobs' => [],
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function redisJob(string $rawPayload, string $queue, string $status): array
    {
        $payload = json_decode($rawPayload, true);
        $payload = is_array($payload) ? $payload : [];

        return [
            'id' => $payload['uuid'] ?? $payload['id'] ?? null,
            'queue' => $queue,
            'job' => $payload['displayName'] ?? $payload['job'] ?? 'n/a',
            'attempts' => (int) ($payload['attempts'] ?? 0),
            'created_at' => null,
            'available_at' => null,
            'status' => $status,
            'payload' => JobPayloadInspector::inspect($rawPayload),
        ];
    }

    private function failed(): array
    {
        try {
            $provider = app('queue.failer');
            $driver = (string) config('queue.failed.driver', 'database');

            if ($provider instanceof DatabaseFailedJobProvider
                || $provider instanceof DatabaseUuidFailedJobProvider) {
                return $this->failedFromDatabase(
                    $provider instanceof DatabaseUuidFailedJobProvider,
                    $driver,
                    get_class($provider)
                );
            }

            $records = $provider->all();

            if ($records instanceof Traversable) {
                $records = iterator_to_array($records, false);
            }

            $records = is_array($records) ? $records : [];

            return [
                'driver' => $driver,
                'provider' => get_class($provider),
                'jobs' => array_map(
                    fn ($record) => $this->failedJob((array) $record, false),
                    array_slice($records, 0, 100)
                ),
                'total' => count($records),
            ];
        } catch (Throwable $e) {
            return [
                'driver' => config('queue.failed.driver'),
                'jobs' => [],
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function failedFromDatabase(bool $usesUuid, string $driver, string $provider): array
    {
        $database = config('queue.failed.database');
        $table = (string) config('queue.failed.table', 'failed_jobs');
        $query = DB::connection($database)->table($table);
        $records = (clone $query)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn ($record) => $this->failedJob((array) $record, $usesUuid))
            ->all();

        return [
            'driver' => $driver,
            'provider' => $provider,
            'connection' => $database,
            'jobs' => $records,
            'total' => (clone $query)->count(),
        ];
    }

    private function failedJob(array $record, bool $usesUuid): array
    {
        $identifier = $usesUuid
            ? ($record['uuid'] ?? $record['id'] ?? null)
            : ($record['id'] ?? $record['uuid'] ?? null);
        $failedAt = $record['failed_at'] ?? null;

        if ($failedAt instanceof DateTimeInterface) {
            $failedAt = $failedAt->format('Y-m-d H:i:s');
        }

        $rawPayload = isset($record['payload']) && is_string($record['payload'])
            ? $record['payload']
            : null;

        return [
            'id' => $identifier !== null ? (string) $identifier : '',
            'uuid' => isset($record['uuid']) ? (string) $record['uuid'] : null,
            'queue' => (string) ($record['queue'] ?? 'n/a'),
            'job' => $this->jobNameFromPayload($rawPayload),
            'exception' => mb_substr((string) ($record['exception'] ?? ''), 0, 800),
            'failed_at' => $failedAt !== null ? (string) $failedAt : null,
            'payload' => JobPayloadInspector::inspect($rawPayload),
        ];
    }

    public function retry(string $identifier): array
    {
        try {
            $provider = app('queue.failer');

            if ($identifier !== 'all' && $provider->find($identifier) === null) {
                return ['ok' => false, 'output' => 'Failed job was not found.'];
            }

            $exitCode = Artisan::call('queue:retry', ['id' => [$identifier]]);
            $output = trim(Artisan::output());
            $removed = $identifier === 'all' || $provider->find($identifier) === null;

            return [
                'ok' => $exitCode === 0 && $removed,
                'output' => $output,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    public function forget(string $identifier): array
    {
        try {
            $deleted = (bool) app('queue.failer')->forget($identifier);

            return [
                'ok' => $deleted,
                'output' => $deleted
                    ? 'Failed job deleted successfully.'
                    : 'Failed job was not found.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    private function jobNameFromPayload(?string $payload): string
    {
        if ($payload === null || $payload === '') {
            return 'n/a';
        }

        $decoded = json_decode($payload, true);

        if (is_array($decoded) && ! empty($decoded['displayName'])) {
            return (string) $decoded['displayName'];
        }

        if (preg_match('/"displayName"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $payload, $matches)) {
            return stripcslashes($matches[1]);
        }

        return 'n/a';
    }
}

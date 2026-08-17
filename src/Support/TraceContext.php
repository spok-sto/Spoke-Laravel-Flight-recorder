<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Illuminate\Support\Str;

class TraceContext
{
    private ?string $traceId = null;

    private float $startedAt;

    private float $memoryStartMb;

    private array $queries = [];

    private array $redisCommands = [];

    private array $httpCalls = [];

    private array $pendingHttp = [];

    private array $jobs = [];

    private array $exceptions = [];

    private array $exceptionFingerprints = [];

    public function __construct()
    {
        $this->startedAt = microtime(true);
        $this->memoryStartMb = round(memory_get_usage(true) / 1048576, 1);
    }

    public function traceId(): string
    {
        if ($this->traceId === null) {
            $this->traceId = (string) Str::uuid();
        }

        return $this->traceId;
    }

    public function currentTraceId(): ?string
    {
        return $this->traceId;
    }

    public function startedAt(): float
    {
        return $this->startedAt;
    }

    public function memoryStartMb(): float
    {
        return $this->memoryStartMb;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function bufferQuery(array $record): void
    {
        $this->queries[] = $record;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function bufferRedis(array $record): void
    {
        $this->redisCommands[] = $record;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function beginHttp(int $objectId, array $record): void
    {
        $this->pendingHttp[$objectId] = $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function peekPendingHttp(int $objectId): ?array
    {
        return $this->pendingHttp[$objectId] ?? null;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>|null
     */
    public function completeHttp(int $objectId, array $updates): ?array
    {
        if (! isset($this->pendingHttp[$objectId])) {
            return null;
        }

        $record = array_merge($this->pendingHttp[$objectId], $updates);
        unset($this->pendingHttp[$objectId], $record['_started_at']);
        $this->httpCalls[] = $record;

        return $record;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>|null
     */
    public function failHttp(int $objectId, array $updates): ?array
    {
        if (! isset($this->pendingHttp[$objectId])) {
            return null;
        }

        $record = array_merge($this->pendingHttp[$objectId], $updates, [
            'failed' => true,
        ]);
        unset($this->pendingHttp[$objectId], $record['_started_at']);
        $this->httpCalls[] = $record;

        return $record;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function redisCommands(): array
    {
        return $this->redisCommands;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function httpCalls(): array
    {
        return array_values(array_merge($this->httpCalls, $this->pendingHttp));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function bufferJob(array $record): void
    {
        $this->jobs[] = $record;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    public function rememberExceptionFingerprint(string $fingerprint): bool
    {
        if (isset($this->exceptionFingerprints[$fingerprint])) {
            return false;
        }

        $this->exceptionFingerprints[$fingerprint] = true;

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function bufferException(array $record): void
    {
        $this->exceptions[] = $record;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exceptions(): array
    {
        return $this->exceptions;
    }
}

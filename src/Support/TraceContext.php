<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Illuminate\Support\Str;

/**
 * Per-request (scoped) nosilac trace stanja.
 *
 * Binding mora biti scoped(), ne singleton — recorderi ne smeju da ga
 * injectuju kroz konstruktor, već da ga rešavaju preko app() u record().
 */
class TraceContext
{
    private ?string $traceId = null;

    private float $startedAt;

    private float $memoryStartMb;

    /** @var list<array<string, mixed>> */
    private array $queries = [];

    /** @var list<array<string, mixed>> */
    private array $redisCommands = [];

    /** @var list<array<string, mixed>> */
    private array $httpCalls = [];

    /** @var array<int, array<string, mixed>> */
    private array $pendingHttp = [];

    /** @var list<array<string, mixed>> */
    private array $jobs = [];

    /** @var list<array<string, mixed>> */
    private array $exceptions = [];

    /** @var array<string, true> */
    private array $exceptionFingerprints = [];

    public function __construct()
    {
        $this->startedAt = microtime(true);
        $this->memoryStartMb = round(memory_get_usage(true) / 1048576, 1);
    }

    /**
     * Lenjo generisan UUID za trenutni request.
     */
    public function traceId(): string
    {
        if ($this->traceId === null) {
            $this->traceId = (string) Str::uuid();
        }

        return $this->traceId;
    }

    /**
     * Postojeći trace_id bez lenjog kreiranja (CLI worker / payload hook).
     */
    public function currentTraceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * Početak request/trace konteksta (microtime).
     */
    public function startedAt(): float
    {
        return $this->startedAt;
    }

    /**
     * Memorija na početku request konteksta (MB).
     */
    public function memoryStartMb(): float
    {
        return $this->memoryStartMb;
    }

    /**
     * Buffer SQL upita za finalize / N+1 analizu.
     *
     * @param  array<string, mixed>  $record
     */
    public function bufferQuery(array $record): void
    {
        $this->queries[] = $record;
    }

    /**
     * Buffer Redis komande.
     *
     * @param  array<string, mixed>  $record
     */
    public function bufferRedis(array $record): void
    {
        $this->redisCommands[] = $record;
    }

    /**
     * Početak odlaznog HTTP poziva (parovanje preko spl_object_id).
     *
     * @param  array<string, mixed>  $record
     */
    public function beginHttp(int $objectId, array $record): void
    {
        $this->pendingHttp[$objectId] = $record;
    }

    /**
     * Peek pending HTTP zapisa pre complete/fail (za računanje trajanja).
     *
     * @return array<string, mixed>|null
     */
    public function peekPendingHttp(int $objectId): ?array
    {
        return $this->pendingHttp[$objectId] ?? null;
    }

    /**
     * Završetak odlaznog HTTP poziva (uspeh).
     *
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
     * Završetak odlaznog HTTP poziva (connection failed).
     *
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
     * Uključuje i incomplete pending pozive (npr. request pukao pre response-a).
     *
     * @return list<array<string, mixed>>
     */
    public function httpCalls(): array
    {
        return array_values(array_merge($this->httpCalls, $this->pendingHttp));
    }

    /**
     * Buffer dispatch-ovanih jobova za Flight Recorder summary.
     *
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

    /**
     * Zapamti exception fingerprint u ovom requestu. True ako je nov.
     */
    public function rememberExceptionFingerprint(string $fingerprint): bool
    {
        if (isset($this->exceptionFingerprints[$fingerprint])) {
            return false;
        }

        $this->exceptionFingerprints[$fingerprint] = true;

        return true;
    }

    /**
     * Buffer uhvaćenih exception-a za Flight Recorder summary.
     *
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

<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Konekt\Spoke\Support\DeploymentMarker;
use Konekt\Spoke\Support\JsonlFile;
use Throwable;

class HealthReader
{
    public function __construct(
        private ServerInfoReader $server,
        private QueueReader $queue,
        private ExceptionStatsReader $exceptions,
        private RequestStatsReader $requests,
        private QueryStatsReader $queries,
        private DeploymentMarker $deploy,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?string $date = null): array
    {
        $date = JsonlFile::normalizeDate($date);
        $server = $this->server->info();
        $exceptionGroups = $this->exceptions->grouping($date);
        $requestReg = $this->requests->regressions($date);
        $queryRanking = $this->queries->ranking($date);
        $failedJobs = 0;

        try {
            $failedJobs = (int) ($this->queue->overview()['failed']['total'] ?? 0);
        } catch (Throwable $e) {
            $failedJobs = 0;
        }

        $queryRegressions = array_values(array_filter(
            $queryRanking['data'] ?? [],
            static fn ($row): bool => is_array($row) && ($row['regression_pct'] ?? null) !== null
        ));

        $checks = [];
        $alerts = [];

        $this->addResourceCheck($checks, $alerts, 'cpu', 'CPU', (float) ($server['cpu']['load_pct'] ?? 0), 60, 80, '%');
        $this->addResourceCheck($checks, $alerts, 'memory', 'Memory', (float) ($server['memory']['used_pct'] ?? 0), 70, 85, '%');
        $this->addResourceCheck($checks, $alerts, 'disk', 'Disk', (float) ($server['disk']['used_pct'] ?? 0), 80, 90, '%');

        $redisOk = ! empty($server['redis']['available']);
        $checks[] = [
            'key' => 'redis',
            'label' => 'Redis',
            'status' => $redisOk ? 'ok' : 'crit',
            'value' => $redisOk
                ? (($server['redis']['hit_rate_pct'] ?? null) !== null ? $server['redis']['hit_rate_pct'] . '% hit' : 'connected')
                : 'unavailable',
        ];
        if (! $redisOk) {
            $alerts[] = $this->alert('crit', 'redis', 'Redis is unavailable.');
        }

        $exToday = (int) ($exceptionGroups['meta']['total'] ?? 0);
        $exYesterday = (int) ($exceptionGroups['meta']['yesterday_total'] ?? 0);
        $exWarn = (int) config('spoke.health.exception_warn', 10);
        $exCrit = (int) config('spoke.health.exception_crit', 50);
        $spikeFactor = (float) config('spoke.health.exception_spike_factor', 2.0);
        $exStatus = 'ok';

        if ($exToday >= $exCrit) {
            $exStatus = 'crit';
        } elseif ($exToday >= $exWarn) {
            $exStatus = 'warn';
        }

        if ($exYesterday > 0 && $exToday >= $exWarn && ($exToday / $exYesterday) >= $spikeFactor) {
            $exStatus = $exStatus === 'ok' ? 'warn' : $exStatus;
            $alerts[] = $this->alert(
                $exStatus === 'crit' ? 'crit' : 'warn',
                'exceptions_spike',
                $exToday . ' exceptions today (' . round($exToday / max(1, $exYesterday), 1) . '× yesterday).'
            );
        } elseif ($exToday >= $exWarn) {
            $alerts[] = $this->alert(
                $exStatus,
                'exceptions',
                $exToday . ' exceptions recorded today.'
            );
        }

        $checks[] = [
            'key' => 'exceptions',
            'label' => 'Exceptions',
            'status' => $exStatus,
            'value' => $exToday . ' today',
        ];

        $jobStatus = $failedJobs > 10 ? 'crit' : ($failedJobs > 0 ? 'warn' : 'ok');
        $checks[] = [
            'key' => 'failed_jobs',
            'label' => 'Failed jobs',
            'status' => $jobStatus,
            'value' => (string) $failedJobs,
        ];
        if ($failedJobs > 0) {
            $alerts[] = $this->alert($jobStatus, 'failed_jobs', $failedJobs . ' failed jobs in the queue.');
        }

        $n1Count = $this->nPlusOneRequestCount($date);
        $n1Warn = (int) config('spoke.health.n1_warn', 1);
        $n1Crit = (int) config('spoke.health.n1_crit', 20);
        $n1Status = $n1Count >= $n1Crit ? 'crit' : ($n1Count >= $n1Warn ? 'warn' : 'ok');
        $n1Label = $n1Count === 1 ? '1 request' : $n1Count . ' requests';

        $checks[] = [
            'key' => 'n_plus_one',
            'label' => 'N+1 queries',
            'status' => $n1Status,
            'value' => $n1Label . ' today',
        ];

        if ($n1Status !== 'ok') {
            $alerts[] = $this->alert(
                $n1Status,
                'n_plus_one',
                $n1Label . ' with possible N+1 today.'
            );
        }

        foreach (array_slice($requestReg['data'], 0, 5) as $row) {
            $alerts[] = $this->alert(
                'warn',
                'request_regression',
                $row['uri'] . ' P95 ' . round((float) $row['p95_ms']) . ' ms (▲ ' . $row['regression_pct'] . '%).'
            );
        }

        foreach (array_slice($queryRegressions, 0, 3) as $row) {
            $sql = mb_substr((string) ($row['sql'] ?? 'query'), 0, 80);
            $alerts[] = $this->alert(
                'warn',
                'query_regression',
                $sql . ' ▲ ' . $row['regression_pct'] . '%.'
            );
        }

        $score = $this->score($checks);

        return [
            'score' => $score,
            'status' => $score >= 80 ? 'ok' : ($score >= 50 ? 'warn' : 'crit'),
            'checks' => $checks,
            'alerts' => $alerts,
            'exceptions' => [
                'today' => $exToday,
                'yesterday' => $exYesterday,
                'groups' => (int) ($exceptionGroups['meta']['groups'] ?? 0),
                'hourly' => $exceptionGroups['hourly'] ?? array_fill(0, 24, 0),
            ],
            'regressions' => [
                'requests' => $requestReg['data'],
                'queries' => array_slice($queryRegressions, 0, 8),
            ],
            'deploy' => $this->deploy->latest(),
            'deploys_today' => $this->deploy->forDate($date),
            'meta' => [
                'date' => $date,
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    private function nPlusOneRequestCount(string $date): int
    {
        $count = 0;

        foreach (JsonlFile::rows(JsonlFile::path('requests', $date)) as $row) {
            $summary = $row['summary'] ?? null;

            if (is_array($summary) && (int) ($summary['n_plus_one_count'] ?? 0) > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @param  list<array<string, mixed>>  $alerts
     */
    private function addResourceCheck(
        array &$checks,
        array &$alerts,
        string $key,
        string $label,
        float $pct,
        float $warnAt,
        float $critAt,
        string $suffix
    ): void {
        $status = $pct >= $critAt ? 'crit' : ($pct >= $warnAt ? 'warn' : 'ok');
        $checks[] = [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'value' => round($pct) . $suffix,
        ];

        if ($status !== 'ok') {
            $alerts[] = $this->alert($status, $key, $label . ' at ' . round($pct) . $suffix . '.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     */
    private function score(array $checks): int
    {
        $score = 100;

        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'crit') {
                $score -= 25;
            } elseif (($check['status'] ?? '') === 'warn') {
                $score -= 10;
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * @return array{severity: string, key: string, message: string}
     */
    private function alert(string $severity, string $key, string $message): array
    {
        return [
            'severity' => $severity,
            'key' => $key,
            'message' => $message,
        ];
    }
}

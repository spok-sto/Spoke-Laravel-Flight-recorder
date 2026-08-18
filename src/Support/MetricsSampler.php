<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Konekt\Spoke\Readers\RedisInfoReader;
use Konekt\Spoke\Readers\ServerInfoReader;
use Throwable;

/**
 * Records server metrics samples for the Server history charts.
 */
class MetricsSampler
{
    public function __construct(
        private JsonlWriter $writer,
        private ServerInfoReader $server,
        private RedisInfoReader $redis,
    ) {
    }

    /**
     * Takes one sample from the CLI context (scheduler).
     *
     * @return array<string, mixed>|null null when sampling is disabled
     */
    public function sample(): ?array
    {
        if (! config('spoke.metrics.enabled', false)) {
            return null;
        }

        $row = ['t' => now()->format('Y-m-d H:i:s'), 'src' => 'cli'];

        $row += $this->cpu();
        $row += $this->memory();
        $row += $this->disk();

        if (config('spoke.metrics.sample_database', true)) {
            $row += $this->database();
        }

        if (config('spoke.metrics.sample_redis', true)) {
            $row += $this->redis();
        }

        if (config('spoke.metrics.sample_queue', true)) {
            $row += $this->queue();
        }

        $this->writer->write('metrics', $row);

        return $row;
    }

    /**
     * Samples OPcache and PHP-FPM uptime from the web process, because the CLI
     * has its own OPcache. Throttled to one sample per minute.
     */
    public function sampleWebRuntime(): void
    {
        if (! config('spoke.metrics.enabled', false) || ! config('spoke.metrics.sample_web_opcache', true)) {
            return;
        }

        try {
            if (! Cache::add('spoke:metrics:web-runtime', 1, 55)) {
                return;
            }

            $opcache = $this->server->opcache();

            if (empty($opcache['available'])) {
                return;
            }

            $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
            $stats = is_array($status) ? ($status['opcache_statistics'] ?? []) : [];

            $this->writer->write('metrics', [
                't' => now()->format('Y-m-d H:i:s'),
                'src' => 'web',
                'opcache_used_mb' => $opcache['used_mb'] ?? null,
                'opcache_free_mb' => $opcache['free_mb'] ?? null,
                'opcache_hit_rate_pct' => $opcache['hit_rate_pct'] ?? null,
                'opcache_hits' => isset($stats['hits']) ? (int) $stats['hits'] : null,
                'opcache_misses' => isset($stats['misses']) ? (int) $stats['misses'] : null,
                'opcache_cached_scripts' => $opcache['cached_scripts'] ?? null,
                'php_uptime_s' => $opcache['uptime_seconds'] ?? null,
            ]);
        } catch (Throwable $e) {
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cpu(): array
    {
        try {
            $cpu = $this->server->cpu();

            return [
                'load_1m' => $cpu['load_1m'] ?? null,
                'load_pct' => $cpu['load_pct'] ?? null,
                'cores' => $cpu['cores'] ?? null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function memory(): array
    {
        try {
            $memory = $this->server->memory();

            return [
                'mem_used_mb' => $memory['used_mb'] ?? null,
                'mem_used_pct' => $memory['used_pct'] ?? null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function disk(): array
    {
        try {
            $disk = $this->server->disk();

            return [
                'disk_used_pct' => $disk['used_pct'] ?? null,
                'disk_free_gb' => $disk['free_gb'] ?? null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        try {
            $db = $this->server->database();

            return [
                'db_conn' => isset($db['active_connections']) ? (int) $db['active_connections'] : null,
                'db_max_conn' => isset($db['max_connections']) ? (int) $db['max_connections'] : null,
                'db_cache_hit_pct' => $db['cache_hit_pct'] ?? null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function redis(): array
    {
        try {
            $redis = $this->redis->info();

            if (empty($redis['available'])) {
                return [];
            }

            return [
                'redis_mem_mb' => $redis['used_memory_mb'] ?? null,
                'redis_clients' => isset($redis['connected_clients']) ? (int) $redis['connected_clients'] : null,
                'redis_keys' => $redis['total_keys'] ?? null,
                'redis_hits' => isset($redis['keyspace_hits']) ? (int) $redis['keyspace_hits'] : null,
                'redis_misses' => isset($redis['keyspace_misses']) ? (int) $redis['keyspace_misses'] : null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Pending jobs are sampled for the database and redis drivers only, so that
     * sampling never turns into a paid API call.
     *
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $out = [];

        try {
            $connection = (string) config('queue.default');
            $driver = (string) config("queue.connections.{$connection}.driver");

            if (in_array($driver, ['database', 'redis'], true)) {
                $queueName = (string) config("queue.connections.{$connection}.queue", 'default');
                $out['queue_pending'] = (int) app('queue')->connection($connection)->size($queueName);
            }
        } catch (Throwable $e) {
        }

        try {
            if ((string) config('queue.failed.driver') !== 'null') {
                $out['queue_failed'] = (int) DB::connection(config('queue.failed.database'))
                    ->table((string) config('queue.failed.table', 'failed_jobs'))
                    ->count();
            }
        } catch (Throwable $e) {
        }

        return $out;
    }
}

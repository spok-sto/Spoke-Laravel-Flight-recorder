<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Illuminate\Support\Facades\DB;
use Throwable;

class ServerInfoReader
{
    public function __construct(private RedisInfoReader $redis)
    {
    }

    public function info(): array
    {
        return [
            'os' => $this->section(fn () => $this->os()),
            'http' => $this->section(fn () => $this->http()),
            'cpu' => $this->section(fn () => $this->cpu()),
            'memory' => $this->section(fn () => $this->memory()),
            'disk' => $this->section(fn () => $this->disk()),
            'php' => $this->section(fn () => $this->php()),
            'opcache' => $this->section(fn () => $this->opcache()),
            'database' => $this->section(fn () => $this->database()),
            'redis' => $this->redis->info(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    private function section(callable $callback): array
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function os(): array
    {
        $pretty = null;

        if (is_readable('/etc/os-release')) {
            $release = parse_ini_file('/etc/os-release');
            $pretty = $release['PRETTY_NAME'] ?? null;
        }

        $uptimeSeconds = $this->serverUptimeSeconds();

        return [
            'name' => $pretty ?? php_uname('s') . ' ' . php_uname('r'),
            'kernel' => php_uname('r'),
            'hostname' => gethostname() ?: 'n/a',
            'uptime' => $uptimeSeconds !== null ? $this->formatDuration($uptimeSeconds) : 'n/a',
            'uptime_seconds' => $uptimeSeconds,
        ];
    }

    private function http(): array
    {
        $software = request()->server('SERVER_SOFTWARE');

        if (! $software) {
            if (app()->runningInConsole()) {
                $software = 'CLI';
            } elseif (isset($_SERVER['LARAVEL_OCTANE'])) {
                $software = 'Laravel Octane';
            } elseif (function_exists('frankenphp_handle_request')) {
                $software = 'FrankenPHP';
            } else {
                $software = 'PHP-FPM / FastCGI';
            }
        }

        $httpUptimeSec = $this->detectHttpServerUptime();

        return [
            'software' => $software,
            'protocol' => request()->server('SERVER_PROTOCOL') ?? 'HTTP/1.1',
            'port' => request()->server('SERVER_PORT'),
            'https' => request()->isSecure(),
            'host' => request()->getHost(),
            'uptime' => $httpUptimeSec !== null ? $this->formatDuration($httpUptimeSec) : null,
            'uptime_seconds' => $httpUptimeSec,
        ];
    }

    private function serverUptimeSeconds(): ?int
    {
        if (! is_readable('/proc/uptime')) {
            return null;
        }

        return (int) floatval(file_get_contents('/proc/uptime'));
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return "{$m}m {$s}s";
        }

        if ($seconds < 86400) {
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);

            return "{$h}h {$m}m";
        }

        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);

        return "{$d}d {$h}h {$m}m";
    }

    private function detectHttpServerUptime(): ?int
    {
        $pidFiles = [
            '/run/nginx.pid',
            '/var/run/nginx.pid',
            '/run/httpd/httpd.pid',
            '/var/run/httpd.pid',
            '/run/apache2/apache2.pid',
            '/var/run/apache2/apache2.pid',
        ];

        foreach ($pidFiles as $pidFile) {
            if (is_readable($pidFile)) {
                $pid = trim((string) file_get_contents($pidFile));
                if ($pid !== '' && is_dir("/proc/{$pid}")) {
                    $stat = @stat("/proc/{$pid}");
                    if ($stat && isset($stat['ctime'])) {
                        return max(0, time() - (int) $stat['ctime']);
                    }
                }
            }
        }

        return null;
    }

    private function detectPhpUptime(): ?int
    {
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            $startTime = $status['opcache_statistics']['start_time'] ?? null;
            if ($startTime) {
                return max(0, time() - (int) $startTime);
            }
        }

        $fpmPids = [
            '/run/php-fpm/php-fpm.pid',
            '/var/run/php-fpm/php-fpm.pid',
            '/run/php/php-fpm.pid',
            '/var/run/php/php-fpm.pid',
        ];

        foreach ($fpmPids as $pidFile) {
            if (is_readable($pidFile)) {
                $pid = trim((string) file_get_contents($pidFile));
                if ($pid !== '' && is_dir("/proc/{$pid}")) {
                    $stat = @stat("/proc/{$pid}");
                    if ($stat && isset($stat['ctime'])) {
                        return max(0, time() - (int) $stat['ctime']);
                    }
                }
            }
        }

        return null;
    }

    public function cpu(): array
    {
        $cores = null;

        if (is_readable('/proc/cpuinfo')) {
            $cores = substr_count((string) file_get_contents('/proc/cpuinfo'), 'processor');
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;

        return [
            'cores' => $cores,
            'load_1m' => $load[0] ?? null,
            'load_5m' => $load[1] ?? null,
            'load_15m' => $load[2] ?? null,
            'load_pct' => ($load !== null && $cores) ? round($load[0] / $cores * 100, 1) : null,
        ];
    }

    public function memory(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return ['available' => false];
        }

        $content = (string) file_get_contents('/proc/meminfo');

        $get = function (string $key) use ($content): ?int {
            return preg_match('/^' . $key . ':\s+(\d+) kB/m', $content, $m) ? (int) $m[1] : null;
        };

        $totalKb = $get('MemTotal');
        $availableKb = $get('MemAvailable');

        $usedKb = ($totalKb !== null && $availableKb !== null) ? $totalKb - $availableKb : null;

        return [
            'total_mb' => $totalKb !== null ? intdiv($totalKb, 1024) : null,
            'used_mb' => $usedKb !== null ? intdiv($usedKb, 1024) : null,
            'available_mb' => $availableKb !== null ? intdiv($availableKb, 1024) : null,
            'used_pct' => ($usedKb !== null && $totalKb) ? round($usedKb / $totalKb * 100, 1) : null,
        ];
    }

    public function disk(): array
    {
        $path = base_path();

        $total = disk_total_space($path);
        $free = disk_free_space($path);

        if ($total === false || $free === false) {
            return ['available' => false];
        }

        $used = $total - $free;

        return [
            'path' => $path,
            'total_gb' => round($total / 1073741824, 1),
            'used_gb' => round($used / 1073741824, 1),
            'free_gb' => round($free / 1073741824, 1),
            'used_pct' => round($used / $total * 100, 1),
        ];
    }

    private function php(): array
    {
        $uptimeSec = $this->detectPhpUptime();

        return [
            'debug' => (bool) config('app.debug'),
            'environment' => app()->environment(),
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'current_memory_mb' => round(memory_get_usage(true) / 1048576, 1),
            'uptime' => $uptimeSec !== null ? $this->formatDuration($uptimeSec) : null,
            'uptime_seconds' => $uptimeSec,
        ];
    }

    public function opcache(): array
    {
        if (! function_exists('opcache_get_status')) {
            return ['available' => false];
        }

        $status = @opcache_get_status(false);

        if ($status === false) {
            return ['available' => false];
        }

        $memory = $status['memory_usage'] ?? [];
        $stats = $status['opcache_statistics'] ?? [];
        $startTime = $stats['start_time'] ?? null;
        $uptimeSec = $startTime ? max(0, time() - (int) $startTime) : null;

        return [
            'available' => true,
            'enabled' => $status['opcache_enabled'] ?? false,
            'validate_timestamps' => (bool) ini_get('opcache.validate_timestamps'),
            'used_mb' => isset($memory['used_memory']) ? round($memory['used_memory'] / 1048576, 1) : null,
            'free_mb' => isset($memory['free_memory']) ? round($memory['free_memory'] / 1048576, 1) : null,
            'hit_rate_pct' => isset($stats['opcache_hit_rate']) ? round($stats['opcache_hit_rate'], 1) : null,
            'cached_scripts' => $stats['num_cached_scripts'] ?? null,
            'uptime' => $uptimeSec !== null ? $this->formatDuration($uptimeSec) : null,
            'uptime_seconds' => $uptimeSec,
            'start_time' => $startTime ? date('Y-m-d H:i:s', $startTime) : null,
        ];
    }

    public function database(): array
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        $info = [
            'driver' => $driver,
            'database' => $database,
            'uptime' => null,
            'uptime_seconds' => null,
            'start_time' => null,
        ];

        if ($driver === 'pgsql') {
            $info['version'] = DB::selectOne('SHOW server_version')->server_version ?? 'n/a';
            $info['size'] = DB::selectOne(
                'SELECT pg_size_pretty(pg_database_size(?)) AS size',
                [$database]
            )->size ?? null;
            $info['active_connections'] = DB::selectOne(
                'SELECT COUNT(*) AS cnt FROM pg_stat_activity WHERE datname = ?',
                [$database]
            )->cnt ?? null;
            $info['max_connections'] = (int) (DB::selectOne('SHOW max_connections')->max_connections ?? 0);

            try {
                $pgInfo = DB::selectOne('SELECT pg_postmaster_start_time() as start_time, EXTRACT(EPOCH FROM (now() - pg_postmaster_start_time()))::integer as uptime_seconds');
                if ($pgInfo) {
                    $sec = (int) ($pgInfo->uptime_seconds ?? 0);
                    $info['uptime_seconds'] = $sec;
                    $info['uptime'] = $this->formatDuration($sec);
                    $info['start_time'] = (string) ($pgInfo->start_time ?? '');
                }
            } catch (Throwable) {
            }

            try {
                $hit = DB::selectOne(
                    'SELECT ROUND(blks_hit * 100.0 / NULLIF(blks_hit + blks_read, 0), 1) AS cache_hit_pct
                     FROM pg_stat_database WHERE datname = ?',
                    [$database]
                );
                $info['cache_hit_pct'] = ($hit && $hit->cache_hit_pct !== null)
                    ? (float) $hit->cache_hit_pct
                    : null;
            } catch (Throwable) {
                $info['cache_hit_pct'] = null;
            }

            try {
                $info['hot_tables'] = array_map(static function ($row) {
                    return [
                        'table' => $row->relname,
                        'seq_scan' => (int) $row->seq_scan,
                        'idx_scan' => (int) ($row->idx_scan ?? 0),
                    ];
                }, DB::select(
                    'SELECT relname, seq_scan, idx_scan
                     FROM pg_stat_user_tables
                     ORDER BY seq_scan DESC
                     LIMIT 5'
                ));
            } catch (Throwable) {
                $info['hot_tables'] = [];
            }
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $info['version'] = DB::selectOne('SELECT VERSION() AS v')->v ?? 'n/a';
            $info['size'] = DB::selectOne(
                "SELECT CONCAT(ROUND(SUM(data_length + index_length) / 1048576, 1), ' MB') AS size
                 FROM information_schema.tables WHERE table_schema = ?",
                [$database]
            )->size ?? null;
            $info['active_connections'] = (int) $this->mysqlShowValue(
                DB::selectOne("SHOW STATUS LIKE 'Threads_connected'")
            );
            $info['max_connections'] = (int) $this->mysqlShowValue(
                DB::selectOne("SHOW VARIABLES LIKE 'max_connections'")
            );

            try {
                $myUptime = DB::selectOne("SHOW GLOBAL STATUS LIKE 'Uptime'");
                if ($myUptime) {
                    $sec = (int) $this->mysqlShowValue($myUptime);
                    $info['uptime_seconds'] = $sec;
                    $info['uptime'] = $this->formatDuration($sec);
                }
            } catch (Throwable) {
            }

            $info['com_select'] = null;
            $info['slow_queries'] = null;
            $info['cache_hit_pct'] = null;

            try {
                $info['com_select'] = (int) $this->mysqlShowValue(
                    DB::selectOne("SHOW GLOBAL STATUS LIKE 'Com_select'")
                );
                $info['slow_queries'] = (int) $this->mysqlShowValue(
                    DB::selectOne("SHOW GLOBAL STATUS LIKE 'Slow_queries'")
                );
                $requests = (float) $this->mysqlShowValue(
                    DB::selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_read_requests'")
                );
                $diskReads = (float) $this->mysqlShowValue(
                    DB::selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_reads'")
                );
                if ($requests > 0) {
                    $info['cache_hit_pct'] = round((($requests - $diskReads) / $requests) * 100, 1);
                }
            } catch (Throwable) {
            }
        }

        return $info;
    }

    private function mysqlShowValue(?object $row): mixed
    {
        if ($row === null) {
            return 0;
        }

        return $row->Value
            ?? $row->value
            ?? $row->VARIABLE_VALUE
            ?? $row->variable_value
            ?? 0;
    }
}

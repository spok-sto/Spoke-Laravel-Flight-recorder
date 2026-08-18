<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisInfoReader
{
    public function info(): array
    {
        if (! config('database.redis.default')) {
            return ['available' => false, 'reason' => 'Redis is not configured.'];
        }

        try {
            $connection = Redis::connection();
            $raw = $connection->info();

            $flat = [];
            array_walk_recursive($raw, function ($value, $key) use (&$flat) {
                $flat[$key] = $value;
            });

            $hits = (int) ($flat['keyspace_hits'] ?? 0);
            $misses = (int) ($flat['keyspace_misses'] ?? 0);
            $lookups = $hits + $misses;

            $connections = $this->keyCounts();

            $countedDbs = [];
            $totalKeys = 0;

            foreach ($connections as $conn) {
                $db = $conn['database'];

                if ($conn['keys'] !== null && ! in_array($db, $countedDbs, true)) {
                    $totalKeys += $conn['keys'];
                    $countedDbs[] = $db;
                }
            }

            $uptimeSeconds = (int) ($flat['uptime_in_seconds'] ?? 0);

            return [
                'available' => true,
                'version' => $flat['redis_version'] ?? 'n/a',
                'uptime' => $this->formatDuration($uptimeSeconds),
                'uptime_seconds' => $uptimeSeconds,
                'uptime_days' => $flat['uptime_in_days'] ?? null,
                'used_memory_human' => $flat['used_memory_human'] ?? null,
                'used_memory_mb' => isset($flat['used_memory'])
                    ? round((int) $flat['used_memory'] / 1048576, 1)
                    : null,
                'used_memory_peak_human' => $flat['used_memory_peak_human'] ?? null,
                'maxmemory_human' => $flat['maxmemory_human'] ?? null,
                'connected_clients' => $flat['connected_clients'] ?? null,
                'total_commands_processed' => $flat['total_commands_processed'] ?? null,
                'keyspace_hits' => $hits,
                'keyspace_misses' => $misses,
                'hit_rate_pct' => $lookups > 0 ? round($hits / $lookups * 100, 1) : null,
                'total_keys' => $totalKeys,
                'connections' => $connections,
            ];
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => $e->getMessage()];
        }
    }

    public function listKeys(?string $connection = 'default', ?string $pattern = '*', int $limit = 100): array
    {
        $connectionName = $connection ?: 'default';
        $pattern = ($pattern !== null && $pattern !== '') ? $pattern : '*';
        $limit = max(1, min(250, $limit));

        if (! config("database.redis.{$connectionName}")) {
            return ['ok' => false, 'error' => "Connection [{$connectionName}] not found."];
        }

        try {
            $redis = Redis::connection($connectionName);
            [$rawKeys, $hasMore] = $this->scanKeys($redis, $pattern, $limit);
            $meta = $this->keyMeta($redis, $rawKeys);

            return [
                'ok' => true,
                'connection' => $connectionName,
                'pattern' => $pattern,
                'total' => count($rawKeys),
                'count' => count($rawKeys),
                'has_more' => $hasMore,
                'keys' => $meta,
                'connections' => array_values(array_filter(
                    array_keys(config('database.redis', [])),
                    fn ($c) => ! in_array($c, ['client', 'options'], true)
                )),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function getKey(?string $connection = 'default', ?string $key = ''): array
    {
        $connectionName = $connection ?: 'default';
        $keyStr = (string) $key;

        if ($keyStr === '') {
            return ['ok' => false, 'error' => 'Key parameter is required.'];
        }

        try {
            $redis = Redis::connection($connectionName);
            $type = $this->normalizeType($redis->type($keyStr));
            $ttl = (int) $redis->ttl($keyStr);

            $value = null;

            switch (strtolower($type)) {
                case 'string':
                    $val = $redis->get($keyStr);
                    $decoded = is_string($val) ? json_decode($val, true) : null;
                    $value = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $val;
                    break;
                case 'hash':
                    $value = $redis->hgetall($keyStr);
                    break;
                case 'list':
                    $value = $redis->lrange($keyStr, 0, 99);
                    break;
                case 'set':
                    $value = $redis->smembers($keyStr);
                    break;
                case 'zset':
                    try {
                        $value = $redis->zrange($keyStr, 0, 99, ['WITHSCORES' => true]);
                    } catch (Throwable) {
                        $value = $redis->zrange($keyStr, 0, 99);
                    }
                    break;
                default:
                    $value = '(unsupported or none)';
                    break;
            }

            [$value, $raw, $truncated] = $this->truncateRedisValue($value);

            return [
                'ok' => true,
                'key' => $keyStr,
                'connection' => $connectionName,
                'type' => $type,
                'ttl' => $ttl,
                'truncated' => $truncated,
                'value' => $value,
                'raw' => $raw,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function scanKeys($redis, string $pattern, int $limit): array
    {
        $keys = [];
        $seen = [];
        $cursor = $this->initialScanCursor($redis);
        $iterations = 0;
        $maxIterations = 40;
        $count = min(200, max(50, $limit));
        $prefix = $this->redisPrefix($redis);
        $scanPattern = $redis instanceof PredisConnection ? $prefix . $pattern : $pattern;

        do {
            $iterations++;
            $result = $redis->scan($cursor, [
                'match' => $scanPattern,
                'count' => $count,
            ]);

            if ($result === false || $result === null) {
                break;
            }

            if (! is_array($result) || count($result) < 2) {
                break;
            }

            $cursor = $result[0];
            $batch = array_values(is_array($result[1]) ? $result[1] : []);

            foreach ($batch as $index => $key) {
                $key = $this->unprefixRedisKey($redis, (string) $key);

                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $keys[] = $key;

                    if (count($keys) >= $limit) {
                        $moreInBatch = $index < count($batch) - 1;

                        return [$keys, $moreInBatch || $this->scanHasMore($cursor)];
                    }
                }
            }
        } while ($this->scanHasMore($cursor) && $iterations < $maxIterations);

        return [$keys, $this->scanHasMore($cursor)];
    }

    private function initialScanCursor($redis): mixed
    {
        if ($redis instanceof PhpRedisConnection
            && version_compare((string) phpversion('redis'), '6.1.0', '>=')) {
            return null;
        }

        return '0';
    }

    private function scanHasMore(mixed $cursor): bool
    {
        return $cursor !== null && (string) $cursor !== '0';
    }

    private function unprefixRedisKey($redis, string $key): string
    {
        $prefix = $this->redisPrefix($redis);

        if ($prefix !== '' && str_starts_with($key, $prefix)) {
            return substr($key, strlen($prefix));
        }

        return $key;
    }

    private function redisPrefix($redis): string
    {
        try {
            $client = $redis->client();

            if (is_object($client) && method_exists($client, 'getOption') && defined('Redis::OPT_PREFIX')) {
                return (string) $client->getOption(\Redis::OPT_PREFIX);
            }

            if (is_object($client) && method_exists($client, 'getOptions')) {
                return (string) $client->getOptions()->prefix;
            }
        } catch (Throwable) {
            return (string) config('database.redis.options.prefix', '');
        }

        return (string) config('database.redis.options.prefix', '');
    }

    private function keyMeta($redis, array $rawKeys): array
    {
        if ($rawKeys === []) {
            return [];
        }

        try {
            $results = $redis->pipeline(function ($pipe) use ($rawKeys) {
                foreach ($rawKeys as $key) {
                    $pipe->type($key);
                    $pipe->ttl($key);
                }
            });
        } catch (Throwable) {
            $results = null;
        }

        $keys = [];

        foreach ($rawKeys as $i => $keyStr) {
            if (is_array($results) && array_key_exists($i * 2, $results)) {
                $type = $this->normalizeType($results[$i * 2]);
                $ttl = (int) ($results[$i * 2 + 1] ?? -1);
            } else {
                $type = $this->normalizeType($redis->type($keyStr));
                $ttl = (int) $redis->ttl($keyStr);
            }

            $keys[] = [
                'key' => $keyStr,
                'type' => $type,
                'ttl' => $ttl,
            ];
        }

        return $keys;
    }

    private function truncateRedisValue(mixed $value): array
    {
        $maxBytes = (int) config('spoke.redis_value_max_bytes', 8192);
        $raw = is_string($value)
            ? $value
            : (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (strlen($raw) <= $maxBytes) {
            return [$value, $raw, false];
        }

        $raw = substr($raw, 0, $maxBytes) . "\n… [truncated]";

        return [is_string($value) ? $raw : $value, $raw, true];
    }

    private function normalizeType(mixed $type): string
    {
        if (is_int($type)) {
            return match ($type) {
                1 => 'string',
                2 => 'set',
                3 => 'list',
                4 => 'zset',
                5 => 'hash',
                default => 'none',
            };
        }

        $str = strtolower((string) $type);

        if (str_contains($str, 'string')) {
            return 'string';
        }
        if (str_contains($str, 'hash')) {
            return 'hash';
        }
        if (str_contains($str, 'list')) {
            return 'list';
        }
        if (str_contains($str, 'set') && ! str_contains($str, 'zset')) {
            return 'set';
        }
        if (str_contains($str, 'zset')) {
            return 'zset';
        }

        return $str ?: 'none';
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

    private function keyCounts(): array
    {
        $result = [];

        foreach (array_keys(config('database.redis', [])) as $name) {
            if (in_array($name, ['client', 'options'], true)) {
                continue;
            }

            try {
                $result[] = [
                    'name' => $name,
                    'database' => config("database.redis.{$name}.database"),
                    'keys' => (int) Redis::connection($name)->command('dbsize'),
                ];
            } catch (Throwable $e) {
                $result[] = ['name' => $name, 'database' => null, 'keys' => null, 'error' => $e->getMessage()];
            }
        }

        return $result;
    }
}

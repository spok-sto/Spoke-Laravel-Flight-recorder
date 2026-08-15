<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pokreće EXPLAIN / EXPLAIN ANALYZE nad snimljenim SQL-om.
 *
 * ANALYZE je dozvoljen samo za SELECT/WITH. Timeout je obavezan.
 */
class QueryExplainer
{
    /**
     * @param  list<mixed>  $bindings
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     driver?: string,
     *     analyze?: bool,
     *     health?: array<string, mixed>,
     *     plan?: mixed
     * }
     */
    public function explain(string $sql, array $bindings, ?string $connection, bool $analyze): array
    {
        $sql = trim($sql);

        if ($sql === '' || mb_strlen($sql) > 5000) {
            return ['ok' => false, 'error' => 'SQL is empty or too long.'];
        }

        if (! $this->isSingleStatement($sql)) {
            return ['ok' => false, 'error' => 'Multiple SQL statements are not allowed.'];
        }

        $keyword = $this->firstKeyword($sql);

        if ($keyword === 'EXPLAIN') {
            return ['ok' => false, 'error' => 'Cannot EXPLAIN an EXPLAIN statement.'];
        }

        if ($analyze && ! in_array($keyword, ['SELECT', 'WITH'], true)) {
            return ['ok' => false, 'error' => 'ANALYZE is only allowed for SELECT / WITH queries.'];
        }

        $connection = $connection ?: (string) config('database.default');

        try {
            $db = DB::connection($connection);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Unknown database connection.'];
        }

        $driver = $db->getDriverName();
        $timeoutMs = max(100, (int) config('spoke.explain.timeout_ms', 5000));
        $safeBindings = $this->safeBindings($bindings);

        if ($analyze && in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            return [
                'ok' => false,
                'error' => 'EXPLAIN ANALYZE is only supported on PostgreSQL.',
                'driver' => $driver,
            ];
        }

        try {
            $plan = $this->runExplain($db, $driver, $sql, $safeBindings, $analyze, $timeoutMs);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'driver' => $driver,
            ];
        }

        return [
            'ok' => true,
            'driver' => $driver,
            'analyze' => $analyze,
            'health' => $this->healthFromPlan($driver, $plan, $analyze),
            'plan' => $plan,
        ];
    }

    public function isSingleStatement(string $sql): bool
    {
        $stripped = rtrim($sql, " \t\n\r;");

        return ! str_contains($stripped, ';');
    }

    public function firstKeyword(string $sql): string
    {
        $sql = preg_replace('/^\s*\/\*.*?\*\//s', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*--[^\n]*/m', '', $sql) ?? $sql;

        if (preg_match(
            '/\b(SELECT|WITH|INSERT|UPDATE|DELETE|EXPLAIN|DROP|ALTER|TRUNCATE|CREATE|SHOW)\b/i',
            $sql,
            $m
        )) {
            return strtoupper($m[1]);
        }

        return '';
    }

    /**
     * @param  \Illuminate\Database\Connection  $db
     * @param  list<mixed>  $bindings
     */
    private function runExplain($db, string $driver, string $sql, array $bindings, bool $analyze, int $timeoutMs): mixed
    {
        if ($driver === 'pgsql') {
            $prefix = $analyze
                ? 'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '
                : 'EXPLAIN (FORMAT JSON) ';

            $db->beginTransaction();

            try {
                $db->unprepared('SET LOCAL statement_timeout = ' . $timeoutMs);
                $rows = $db->select($prefix . $sql, $bindings);
            } finally {
                $db->rollBack();
            }

            return $this->decodeFirstJsonColumn($rows);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $previous = null;

            try {
                $row = $db->selectOne('SELECT @@SESSION.MAX_EXECUTION_TIME AS v');
                $previous = $row->v ?? null;
                $db->unprepared('SET SESSION MAX_EXECUTION_TIME = ' . $timeoutMs);
                $rows = $db->select('EXPLAIN FORMAT=JSON ' . $sql, $bindings);
            } finally {
                if ($previous !== null) {
                    $db->unprepared('SET SESSION MAX_EXECUTION_TIME = ' . (int) $previous);
                }
            }

            return $this->decodeFirstJsonColumn($rows);
        }

        $rows = $db->select('EXPLAIN QUERY PLAN ' . $sql, $bindings);

        return array_map(static function ($row) {
            return (array) $row;
        }, $rows);
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     */
    private function decodeFirstJsonColumn(array $rows): mixed
    {
        if ($rows === []) {
            return null;
        }

        $row = (array) $rows[0];
        $raw = reset($row);

        if (! is_string($raw)) {
            return $row;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    /**
     * Best-effort Query Health iz EXPLAIN JSON / QUERY PLAN redova.
     *
     * @return array<string, mixed>
     */
    private function healthFromPlan(string $driver, mixed $plan, bool $analyze): array
    {
        $health = [
            'seq_scan' => false,
            'index_used' => null,
            'node_type' => null,
            'relation' => null,
            'total_cost' => null,
            'plan_rows' => null,
            'actual_ms' => null,
            'actual_rows' => null,
            'analyze' => $analyze,
        ];

        if ($driver === 'pgsql') {
            $root = $plan;

            if (is_array($plan) && array_is_list($plan) && isset($plan[0]['Plan'])) {
                $root = $plan[0];
            }

            if (is_array($root) && isset($root['Plan']) && is_array($root['Plan'])) {
                $this->walkPgPlan($root['Plan'], $health);
                $health['actual_ms'] = isset($root['Execution Time'])
                    ? round((float) $root['Execution Time'], 2)
                    : $health['actual_ms'];
            }
        } elseif (in_array($driver, ['mysql', 'mariadb'], true) && is_array($plan)) {
            $this->walkMysqlPlan($plan, $health);
        } elseif ($driver === 'sqlite' && is_array($plan)) {
            foreach ($plan as $row) {
                $detail = strtolower((string) ($row['detail'] ?? ''));
                if ($health['node_type'] === null) {
                    $health['node_type'] = $row['detail'] ?? null;
                }
                if (str_contains($detail, 'scan table') && ! str_contains($detail, 'using index') && ! str_contains($detail, 'covering index')) {
                    $health['seq_scan'] = true;
                }
                if (str_contains($detail, 'using index') || str_contains($detail, 'search table')) {
                    $health['index_used'] = true;
                }
            }
        }

        if ($health['index_used'] === null) {
            $health['index_used'] = $health['seq_scan'] ? false : null;
        }

        return $health;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $health
     */
    private function walkPgPlan(array $node, array &$health): void
    {
        $type = (string) ($node['Node Type'] ?? '');

        if ($health['node_type'] === null && $type !== '') {
            $health['node_type'] = $type;
            $health['relation'] = $node['Relation Name'] ?? $node['Index Name'] ?? null;
            $health['total_cost'] = isset($node['Total Cost']) ? round((float) $node['Total Cost'], 2) : null;
            $health['plan_rows'] = isset($node['Plan Rows']) ? (int) $node['Plan Rows'] : null;
            $health['actual_ms'] = isset($node['Actual Total Time'])
                ? round((float) $node['Actual Total Time'], 2)
                : $health['actual_ms'];
            $health['actual_rows'] = isset($node['Actual Rows']) ? (int) $node['Actual Rows'] : $health['actual_rows'];
        }

        if (strcasecmp($type, 'Seq Scan') === 0) {
            $health['seq_scan'] = true;
            $health['relation'] = $node['Relation Name'] ?? $health['relation'];
        }

        if (str_contains(strtolower($type), 'index')) {
            $health['index_used'] = true;
        }

        foreach ($node['Plans'] ?? [] as $child) {
            if (is_array($child)) {
                $this->walkPgPlan($child, $health);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $health
     */
    private function walkMysqlPlan(array $node, array &$health): void
    {
        if (isset($node['query_block']) && is_array($node['query_block'])) {
            $this->walkMysqlPlan($node['query_block'], $health);
        }

        if (isset($node['table']) && is_array($node['table'])) {
            $table = $node['table'];
            $access = strtolower((string) ($table['access_type'] ?? ''));
            $health['node_type'] = $health['node_type'] ?? ($table['access_type'] ?? null);
            $health['relation'] = $health['relation'] ?? ($table['table_name'] ?? null);
            $health['plan_rows'] = $health['plan_rows'] ?? (isset($table['rows_examined_per_scan'])
                ? (int) $table['rows_examined_per_scan']
                : null);

            if (in_array($access, ['all', 'index'], true) && $access === 'all') {
                $health['seq_scan'] = true;
            }
            if (in_array($access, ['ref', 'eq_ref', 'const', 'range', 'index'], true) && $access !== 'all') {
                $health['index_used'] = true;
            }

            $this->walkMysqlPlan($table, $health);
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->walkMysqlPlan($value, $health);
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return list<mixed>
     */
    private function safeBindings(array $bindings): array
    {
        $out = [];

        foreach (array_slice($bindings, 0, 50) as $value) {
            if (is_scalar($value) || $value === null) {
                $out[] = $value;
            }
        }

        return $out;
    }
}

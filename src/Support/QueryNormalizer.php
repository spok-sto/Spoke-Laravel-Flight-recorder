<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

/**
 * Normalizacija SQL-a za N+1 grupisanje.
 *
 * QueryExecuted::$sql je već parametrizovan (? umesto vrednosti) —
 * kolapsiramo samo promenljive IN (?) liste i whitespace.
 */
class QueryNormalizer
{
    /**
     * Kanonski oblik SQL-a za poređenje / grupisanje.
     */
    public static function normalize(string $sql): string
    {
        $sql = preg_replace('/\s+/u', ' ', trim($sql)) ?? trim($sql);

        $sql = preg_replace(
            '/\bIN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/iu',
            'IN (?)',
            $sql
        ) ?? $sql;

        return $sql;
    }

    /**
     * Stabilan fingerprint normalizovanog SQL-a za ranking / regresiju.
     */
    public static function fingerprint(string $sql): string
    {
        return sha1(self::normalize($sql));
    }
}

<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

class QueryNormalizer
{
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

    public static function fingerprint(string $sql): string
    {
        return sha1(self::normalize($sql));
    }
}

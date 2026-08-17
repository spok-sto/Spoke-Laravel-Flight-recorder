<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

class BodyRedactor
{
    private const MAX_PARSE_BYTES = 1048576;

    /**
     * @param  list<string>  $keys
     */
    public static function redact(string $body, array $keys): string
    {
        if ($body === '' || $keys === [] || strlen($body) > self::MAX_PARSE_BYTES) {
            return $body;
        }

        try {
            $trimmed = ltrim($body);
            $first = $trimmed !== '' ? $trimmed[0] : '';

            if ($first === '{' || $first === '[') {
                $decoded = json_decode($body, true);

                if (is_array($decoded)) {
                    $encoded = json_encode(
                        self::redactArray($decoded, $keys),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                    );

                    return $encoded !== false ? $encoded : $body;
                }

                return $body;
            }

            if (str_contains($body, '=') && ! preg_match('/\s/', $body)) {
                parse_str($body, $parsed);

                if ($parsed !== []) {
                    return http_build_query(self::redactArray($parsed, $keys));
                }
            }
        } catch (Throwable $e) {
        }

        return $body;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $keys
     * @return array<array-key, mixed>
     */
    public static function redactArray(array $data, array $keys): array
    {
        $lookup = array_flip(array_map('strtolower', $keys));

        return self::redactRecursive($data, $lookup);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, int>  $lookup
     * @return array<array-key, mixed>
     */
    private static function redactRecursive(array $data, array $lookup): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && isset($lookup[strtolower($key)])) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactRecursive($value, $lookup);
            }
        }

        return $data;
    }
}

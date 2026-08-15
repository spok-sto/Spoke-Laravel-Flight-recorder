<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

/**
 * Normalizacija URI-ja za regresiju i exception route grouping.
 */
class UriNormalizer
{
    /**
     * Skini query string, UUID i numeričke ID-eve.
     */
    public static function normalize(?string $uri): string
    {
        $uri = explode('?', (string) $uri)[0];
        $uri = '/' . ltrim($uri, '/');
        $uri = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '{uuid}',
            $uri
        ) ?? $uri;
        $uri = preg_replace('#/\d+#', '/{id}', $uri) ?? $uri;

        return $uri === '//' ? '/' : $uri;
    }
}

<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

class ExceptionNormalizer
{
    public static function normalizeMessage(string $message): string
    {
        $message = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '{uuid}',
            $message
        ) ?? $message;
        $message = preg_replace('/\'[^\']{0,200}\'/', "'?'", $message) ?? $message;
        $message = preg_replace('/"[^"]{0,200}"/', '"?"', $message) ?? $message;
        $message = preg_replace('/\b\d+\b/', 'N', $message) ?? $message;
        $message = preg_replace('/\s+/u', ' ', trim($message)) ?? trim($message);

        return mb_substr($message, 0, 400);
    }

    public static function stackFingerprint(Throwable $exception): string
    {
        $file = str_replace('\\', '/', $exception->getFile());

        return sha1($file . ':' . $exception->getLine());
    }

    public static function fingerprint(Throwable $exception): string
    {
        return sha1(
            $exception::class
            . "\0"
            . self::normalizeMessage($exception->getMessage())
            . "\0"
            . self::stackFingerprint($exception)
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fingerprintFromRow(array $row): string
    {
        if (isset($row['fingerprint']) && is_string($row['fingerprint']) && $row['fingerprint'] !== '') {
            return $row['fingerprint'];
        }

        $class = (string) ($row['class'] ?? 'Exception');
        $message = self::normalizeMessage((string) ($row['message'] ?? ''));
        $file = (string) ($row['file'] ?? '');
        $line = (string) ($row['line'] ?? '');

        return sha1($class . "\0" . $message . "\0" . $file . ':' . $line);
    }
}

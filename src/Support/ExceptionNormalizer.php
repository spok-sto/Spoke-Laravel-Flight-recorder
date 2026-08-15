<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

/**
 * Fingerprint exception-a za grouping (klasa + normalizovana poruka + file:line).
 */
class ExceptionNormalizer
{
    /**
     * Ukloni volatile delove poruke (ID, UUID, brojevi, quoted stringovi).
     */
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

    /**
     * Mesto bacanja (file:line) — caller stack se namerno ne koristi
     * da ista greška iz različitih requesta ostane u istoj grupi.
     */
    public static function stackFingerprint(Throwable $exception): string
    {
        $file = str_replace('\\', '/', $exception->getFile());

        return sha1($file . ':' . $exception->getLine());
    }

    /**
     * Stabilan fingerprint za Exception Center grouping.
     */
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
     * Fallback kad JSONL red nema sačuvan fingerprint.
     *
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

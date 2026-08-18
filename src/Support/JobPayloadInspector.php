<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

class JobPayloadInspector
{
    /**
     * @return array<string, mixed>|null
     */
    public static function inspect(?string $rawPayload): ?array
    {
        if ($rawPayload === null || $rawPayload === '') {
            return null;
        }

        $decoded = json_decode($rawPayload, true);

        if (! is_array($decoded)) {
            return null;
        }

        $preview = [
            'uuid' => $decoded['uuid'] ?? null,
            'displayName' => $decoded['displayName'] ?? null,
            'job' => $decoded['job'] ?? null,
            'maxTries' => $decoded['maxTries'] ?? null,
            'maxExceptions' => $decoded['maxExceptions'] ?? null,
            'timeout' => $decoded['timeout'] ?? null,
            'retryUntil' => $decoded['retryUntil'] ?? null,
            'backoff' => $decoded['backoff'] ?? null,
            'attempts' => $decoded['attempts'] ?? null,
            'trace_id' => $decoded['spoke_trace_id'] ?? null,
        ];

        $command = self::commandPreview($decoded['data']['command'] ?? null);

        if ($command !== null) {
            $preview['command'] = $command;
        }

        $redacted = BodyRedactor::redactArray($preview, config('spoke.redact_keys', []));

        return self::limit($redacted);
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    private static function commandPreview(mixed $command): mixed
    {
        if (is_array($command)) {
            return self::normalize($command);
        }

        if (! is_string($command) || $command === '') {
            return null;
        }

        try {
            $decoded = @unserialize($command, ['allowed_classes' => false]);
        } catch (Throwable $e) {
            return null;
        }

        if ($decoded === false && $command !== serialize(false)) {
            return null;
        }

        return self::normalize($decoded);
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $out = [];

            foreach ((array) $value as $key => $item) {
                $name = preg_replace('/^\x00.+\x00/', '', (string) $key) ?? (string) $key;

                if ($name === '' || $name === '__PHP_Incomplete_Class_Name') {
                    if ($name === '__PHP_Incomplete_Class_Name' && is_string($item) && $item !== '') {
                        $out['class'] = $item;
                    }

                    continue;
                }

                $out[$name] = self::normalize($item);
            }

            return $out;
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $item) {
                $out[$key] = self::normalize($item);
            }

            return $out;
        }

        if (is_string($value) && strlen($value) > 2000) {
            return mb_substr($value, 0, 2000) . '…';
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function limit(array $data): array
    {
        $max = max(1024, (int) config('spoke.job_payload_max_bytes', 16384));
        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($encoded === false || strlen($encoded) <= $max) {
            return $data;
        }

        if (isset($data['command'])) {
            $data['command'] = '[truncated]';
        }

        return $data;
    }
}

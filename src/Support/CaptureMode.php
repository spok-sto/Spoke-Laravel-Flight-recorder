<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

class CaptureMode
{
    private ?bool $memoized = null;

    /**
     * @return array{active: bool, expires_at: string|null}
     */
    public function enable(?int $minutes = null): array
    {
        if (! DebugTools::enabled()) {
            return $this->state();
        }

        $minutes ??= (int) config('spoke.capture.ttl_minutes', 60);
        $expiresAt = time() + max(1, $minutes) * 60;

        $dir = dirname($this->flagFile());

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->flagFile(), json_encode([
            'enabled_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
        ]), LOCK_EX);

        $this->memoized = null;

        return $this->state();
    }

    public function disable(): array
    {
        @unlink($this->flagFile());
        $this->memoized = null;

        return $this->state();
    }

    public function active(): bool
    {
        if (! DebugTools::enabled()) {
            return false;
        }

        if ($this->memoized !== null) {
            return $this->memoized;
        }

        return $this->memoized = $this->expiresAt() > time();
    }

    /**
     * @return array{active: bool, expires_at: string|null}
     */
    public function state(): array
    {
        if (! DebugTools::enabled()) {
            return [
                'active' => false,
                'expires_at' => null,
            ];
        }

        $expiresAt = $this->expiresAt();
        $active = $expiresAt > time();

        return [
            'active' => $active,
            'expires_at' => $active ? date('Y-m-d H:i:s', $expiresAt) : null,
        ];
    }

    private function expiresAt(): int
    {
        try {
            $file = $this->flagFile();

            if (! is_file($file)) {
                return 0;
            }

            $data = json_decode((string) file_get_contents($file), true);

            return is_array($data) ? (int) ($data['expires_at'] ?? 0) : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function flagFile(): string
    {
        return config('spoke.storage_path') . '/capture.json';
    }
}

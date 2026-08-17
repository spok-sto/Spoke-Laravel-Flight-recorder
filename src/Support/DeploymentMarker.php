<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

use Throwable;

class DeploymentMarker
{
    public function __construct(private JsonlWriter $writer)
    {
    }

    public function recordIfChanged(): bool
    {
        try {
            $snapshot = $this->snapshot();
            $identity = $snapshot['identity'];
            $dir = (string) config('spoke.storage_path');

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
                @chmod($dir, 0775);
            }

            $stateFile = $dir . '/.last-deploy';
            $handle = fopen($stateFile, 'c+');

            if ($handle === false) {
                return false;
            }

            try {
                if (! flock($handle, LOCK_EX)) {
                    return false;
                }

                rewind($handle);
                $previous = trim((string) stream_get_contents($handle));

                if ($previous === $identity) {
                    return false;
                }

                $this->writer->write('deploys', [
                    't' => now()->format('Y-m-d H:i:s.v'),
                    'version' => $snapshot['version'],
                    'commit' => $snapshot['commit'],
                    'branch' => $snapshot['branch'],
                    'php' => PHP_VERSION,
                    'laravel' => app()->version(),
                ]);

                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, $identity);

                return true;
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        foreach (JsonlFile::dates('deploys') as $date) {
            $row = JsonlFile::lastRow(JsonlFile::path('deploys', $date));
            if (is_array($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forDate(?string $date = null): array
    {
        $date = JsonlFile::normalizeDate($date);

        return JsonlFile::rows(JsonlFile::path('deploys', $date));
    }

    /**
     * @return array{identity: string, version: ?string, commit: ?string, branch: ?string}
     */
    public function snapshot(): array
    {
        $version = $this->appVersion();
        $git = $this->gitInfo();
        $commit = $git['commit'];
        $branch = $git['branch'];
        $identity = $commit ?: $version;

        if ($identity === null || $identity === '') {
            $lock = base_path('composer.lock');
            $identity = is_file($lock) ? 'lock:' . filemtime($lock) : 'boot:' . PHP_VERSION;
        }

        return [
            'identity' => $identity,
            'version' => $version,
            'commit' => $commit,
            'branch' => $branch,
        ];
    }

    private function appVersion(): ?string
    {
        $version = config('app.version');
        if (is_string($version) && $version !== '') {
            return $version;
        }

        $env = env('APP_VERSION');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        return null;
    }

    /**
     * @return array{commit: ?string, branch: ?string}
     */
    private function gitInfo(): array
    {
        $headFile = base_path('.git/HEAD');

        if (! is_readable($headFile)) {
            return ['commit' => null, 'branch' => null];
        }

        $head = trim((string) file_get_contents($headFile));

        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $branch = str_starts_with($ref, 'refs/heads/')
                ? substr($ref, 11)
                : $ref;
            $shaFile = base_path('.git/' . $ref);
            $sha = is_readable($shaFile)
                ? substr(trim((string) file_get_contents($shaFile)), 0, 12)
                : null;

            return ['commit' => $sha, 'branch' => $branch];
        }

        return [
            'commit' => substr($head, 0, 12),
            'branch' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Generator;

class LaravelLogReader
{
    private const ENTRY_HEADER = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (.+)\.([A-Z]+):\s?(.*)$/';

    /**
     * Čita Laravel log unazad bez učitavanja celog fajla u memoriju.
     */
    public function read(
        ?string $file = null,
        ?string $level = null,
        ?string $search = null,
        int $page = 1,
        ?int $perPage = null,
        ?int $cursor = null
    ): array {
        $perPage = max(1, min(100, $perPage ?: (int) config('spoke.per_page', 50)));
        $page = max(1, $page);
        $level = $level !== null && $level !== '' ? strtoupper(trim($level)) : null;
        $search = $search !== null && $search !== ''
            ? mb_substr(trim($search), 0, 200)
            : null;

        $files = $this->availableFiles();
        $file = $file ?: ($files[0]['name'] ?? null);
        $path = $file !== null ? $this->logPath($file) : null;

        if ($path === null) {
            return $this->emptyResult($file, $files, $page, $perPage);
        }

        $result = $this->scanFile($path, $level, $search, $perPage, $cursor);

        return [
            'data' => $result['entries'],
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => null,
                'loaded' => count($result['entries']),
                'file' => $file,
                'files' => $files,
                'levels' => $result['levels'],
                'cursor' => $result['cursor'],
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
                'file_size' => $result['file_size'],
                'scanned_bytes' => $result['scanned_bytes'],
                'scanned_entries' => $result['scanned_entries'],
                'scan_limited' => $result['scan_limited'],
            ],
        ];
    }

    /**
     * Vraća dostupne Laravel log fajlove sortirane po vremenu izmene.
     */
    public function availableFiles(): array
    {
        $dir = (string) config('spoke.logs_path');
        $files = [];

        foreach (glob($dir . '/*.log') ?: [] as $path) {
            clearstatcache(true, $path);

            $files[] = [
                'name' => basename($path),
                'size' => filesize($path) ?: 0,
                'modified' => date('Y-m-d H:i:s', filemtime($path) ?: 0),
            ];
        }

        usort($files, fn ($a, $b) => strcmp($b['modified'], $a['modified']));

        return $files;
    }

    /**
     * Bezbedno razrešava log fajl isključivo unutar konfigurisanog direktorijuma.
     */
    private function logPath(string $file): ?string
    {
        $directory = realpath((string) config('spoke.logs_path'));
        $path = $directory !== false
            ? realpath($directory . DIRECTORY_SEPARATOR . basename($file))
            : false;

        if ($directory === false
            || $path === false
            || ! is_file($path)
            || ! str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    /**
     * Skenira log unazad do popunjavanja stranice ili dostizanja limita po zahtevu.
     */
    private function scanFile(
        string $path,
        ?string $level,
        ?string $search,
        int $perPage,
        ?int $cursor
    ): array {
        clearstatcache(true, $path);
        $fileSize = max(0, (int) (filesize($path) ?: 0));
        $cursor = $cursor === null ? $fileSize : max(0, min($cursor, $fileSize));
        $chunkBytes = max(4096, min(
            4 * 1024 * 1024,
            (int) config('spoke.log_reader.chunk_bytes', 256 * 1024)
        ));
        $maxScanBytes = max($chunkBytes, min(
            2 * 1024 * 1024 * 1024,
            (int) config('spoke.log_reader.max_scan_bytes', 64 * 1024 * 1024)
        ));
        $maxEntryBytes = max(20 * 1024, min(
            4 * 1024 * 1024,
            (int) config('spoke.log_reader.max_entry_bytes', 256 * 1024)
        ));
        $maxOutputBytes = max(4096, min(
            $maxEntryBytes,
            (int) config('spoke.log_reader.max_output_bytes', 20 * 1024)
        ));
        $needle = $search !== null ? mb_strtolower($search) : null;
        $handle = fopen($path, 'rb');

        if ($handle === false || $cursor === 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return [
                'entries' => [],
                'levels' => [],
                'cursor' => $cursor,
                'next_cursor' => null,
                'has_more' => false,
                'file_size' => $fileSize,
                'scanned_bytes' => 0,
                'scanned_entries' => 0,
                'scan_limited' => false,
            ];
        }

        $entries = [];
        $levelCounts = [];
        $entryLines = [];
        $entryBytes = 0;
        $entryEndOffset = null;
        $entryTruncated = false;
        $scannedBytes = 0;
        $scanPosition = $cursor;
        $oldestProcessedOffset = null;
        $scannedEntries = 0;
        $foundExtraEntry = false;

        foreach ($this->reverseLines(
            $handle,
            $cursor,
            $chunkBytes,
            $maxScanBytes,
            $scannedBytes,
            $scanPosition
        ) as $lineData) {
            $line = $lineData['line'];
            $oldestProcessedOffset = $lineData['start'];

            if ($entryLines === []) {
                $entryEndOffset = $lineData['end'];
            }

            $entryLines[] = $line;
            $entryBytes += strlen($line) + 1;

            while ($entryBytes > $maxEntryBytes && count($entryLines) > 1) {
                $removedLine = array_shift($entryLines);
                $entryBytes -= strlen((string) $removedLine) + 1;
                $entryTruncated = true;
            }

            if (! preg_match(self::ENTRY_HEADER, $line, $header)) {
                continue;
            }

            $entry = $this->makeEntry(
                $entryLines,
                $header,
                $lineData['start'],
                $entryTruncated,
                $maxEntryBytes,
                $maxOutputBytes
            );
            $entryLines = [];
            $entryBytes = 0;
            $entryEndOffset = null;
            $entryTruncated = false;
            $scannedEntries++;
            $levelCounts[$entry['level']] = ($levelCounts[$entry['level']] ?? 0) + 1;

            if (! $this->matchesFilters($entry, $level, $needle)) {
                continue;
            }

            $entries[] = $entry;

            if (count($entries) > $perPage) {
                $foundExtraEntry = true;
                array_pop($entries);
                break;
            }
        }

        fclose($handle);
        arsort($levelCounts);

        $hasMore = $foundExtraEntry || $scanPosition > 0;
        $nextCursor = null;

        if ($foundExtraEntry) {
            $lastEntry = $entries !== [] ? $entries[array_key_last($entries)] : null;
            $nextCursor = $lastEntry['_offset'] ?? null;
        } elseif ($scanPosition > 0) {
            $nextCursor = $oldestProcessedOffset ?? $scanPosition;

            if ($entryEndOffset !== null
                && $oldestProcessedOffset !== null
                && $entryEndOffset - $oldestProcessedOffset <= $maxEntryBytes) {
                $nextCursor = $entryEndOffset;
            }
        }

        $entries = array_map(static function (array $entry): array {
            unset($entry['_offset'], $entry['_search']);

            return $entry;
        }, $entries);

        return [
            'entries' => $entries,
            'levels' => $levelCounts,
            'cursor' => $cursor,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore && $nextCursor !== null && $nextCursor < $cursor,
            'file_size' => $fileSize,
            'scanned_bytes' => $scannedBytes,
            'scanned_entries' => $scannedEntries,
            'scan_limited' => ! $foundExtraEntry && $scanPosition > 0 && $scannedBytes >= $maxScanBytes,
        ];
    }

    /**
     * Iterira linije od kraja ka početku uz ograničenu memoriju.
     *
     * @param  resource  $handle
     */
    private function reverseLines(
        $handle,
        int $endOffset,
        int $chunkBytes,
        int $maxScanBytes,
        int &$scannedBytes,
        int &$scanPosition
    ): Generator {
        $position = $endOffset;
        $buffer = '';
        $firstLine = true;

        while ($position > 0 && $scannedBytes < $maxScanBytes) {
            $bytesToRead = min($chunkBytes, $position, $maxScanBytes - $scannedBytes);
            $position -= $bytesToRead;

            if (fseek($handle, $position) !== 0) {
                break;
            }

            $chunk = fread($handle, $bytesToRead);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $scannedBytes += strlen($chunk);
            $scanPosition = $position;
            $buffer = $chunk . $buffer;

            while (($newlinePosition = strrpos($buffer, "\n")) !== false) {
                $bufferLength = strlen($buffer);
                $line = rtrim(substr($buffer, $newlinePosition + 1), "\r");
                $lineStart = $position + $newlinePosition + 1;
                $lineEnd = $position + $bufferLength;
                $buffer = substr($buffer, 0, $newlinePosition);

                if ($firstLine && $line === '') {
                    $firstLine = false;

                    continue;
                }

                $firstLine = false;

                yield [
                    'line' => $line,
                    'start' => $lineStart,
                    'end' => $lineEnd,
                ];
            }
        }

        if ($position === 0 && $buffer !== '') {
            yield [
                'line' => rtrim($buffer, "\r"),
                'start' => 0,
                'end' => strlen($buffer),
            ];
        }
    }

    /**
     * Pretvara prikupljene linije u strukturirani log zapis.
     */
    private function makeEntry(
        array $reverseLines,
        array $header,
        int $offset,
        bool $truncated,
        int $maxEntryBytes,
        int $maxOutputBytes
    ): array {
        $lines = array_reverse($reverseLines);
        $full = implode("\n", $lines);

        if (strlen($full) > $maxEntryBytes) {
            $full = substr($full, 0, $maxEntryBytes);
            $truncated = true;
        }

        $output = mb_strcut($full, 0, $maxOutputBytes);
        $outputTruncated = strlen($output) < strlen($full);

        if ($truncated || $outputTruncated) {
            $output .= "\n… [truncated]";
        }

        return [
            'time' => $header[1],
            'env' => $header[2],
            'level' => $header[3],
            'message' => mb_substr(trim($header[4]), 0, 2000),
            'full' => $output,
            'truncated' => $truncated || $outputTruncated,
            '_offset' => $offset,
            '_search' => $full,
        ];
    }

    /**
     * Proverava nivo i tekstualni filter nad jednim zapisom.
     */
    private function matchesFilters(array $entry, ?string $level, ?string $needle): bool
    {
        if ($level !== null && $entry['level'] !== $level) {
            return false;
        }

        return $needle === null || str_contains(mb_strtolower($entry['_search']), $needle);
    }

    /**
     * Vraća prazan, ali stabilan API odgovor kada log nije dostupan.
     */
    private function emptyResult(?string $file, array $files, int $page, int $perPage): array
    {
        return [
            'data' => [],
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => null,
                'loaded' => 0,
                'file' => $file,
                'files' => $files,
                'levels' => [],
                'cursor' => null,
                'next_cursor' => null,
                'has_more' => false,
                'file_size' => 0,
                'scanned_bytes' => 0,
                'scanned_entries' => 0,
                'scan_limited' => false,
            ],
        ];
    }
}

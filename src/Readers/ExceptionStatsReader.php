<?php

declare(strict_types=1);

namespace Konekt\Spoke\Readers;

use Konekt\Spoke\Support\ExceptionNormalizer;
use Konekt\Spoke\Support\JsonlFile;
use Konekt\Spoke\Support\UriNormalizer;

class ExceptionStatsReader
{
    /**
     * @return array<string, mixed>
     */
    public function grouping(?string $date = null, ?string $search = null): array
    {
        $date = JsonlFile::normalizeDate($date);
        $rows = JsonlFile::rows(JsonlFile::path('exceptions', $date));
        $groups = $this->groupRows($rows);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $groups = array_values(array_filter(
                $groups,
                static function (array $group) use ($needle): bool {
                    $hay = mb_strtolower(
                        $group['class'] . ' ' . $group['message'] . ' ' . implode(' ', array_column($group['uris'], 'uri'))
                    );

                    return str_contains($hay, $needle);
                }
            ));
        }

        usort($groups, static function (array $a, array $b): int {
            return ($b['count'] <=> $a['count']) ?: strcmp((string) $b['last_seen'], (string) $a['last_seen']);
        });

        $yesterday = date('Y-m-d', (strtotime($date . ' 00:00:00') ?: time()) - 86400);
        $yesterdayCount = count(JsonlFile::rows(JsonlFile::path('exceptions', $yesterday)));

        return [
            'data' => array_slice($groups, 0, (int) config('spoke.per_page', 50)),
            'hourly' => $this->hourly($rows),
            'meta' => [
                'date' => $date,
                'total' => count($rows),
                'groups' => count($groups),
                'yesterday_total' => $yesterdayCount,
                'dates' => JsonlFile::dates('exceptions'),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupRows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $fp = ExceptionNormalizer::fingerprintFromRow($row);
            $uri = UriNormalizer::normalize($row['uri'] ?? null);
            $t = (string) ($row['t'] ?? '');

            if (! isset($groups[$fp])) {
                $groups[$fp] = [
                    'fingerprint' => $fp,
                    'class' => (string) ($row['class'] ?? 'Exception'),
                    'message' => (string) ($row['message'] ?? ''),
                    'count' => 0,
                    'first_seen' => $t,
                    'last_seen' => $t,
                    'uris' => [],
                    'file' => $row['file'] ?? null,
                    'line' => $row['line'] ?? null,
                    'stack' => $row['stack'] ?? null,
                    'trace_id' => $row['trace_id'] ?? null,
                ];
            }

            $groups[$fp]['count']++;

            if ($t !== '' && ($groups[$fp]['first_seen'] === '' || $t < $groups[$fp]['first_seen'])) {
                $groups[$fp]['first_seen'] = $t;
            }
            if ($t !== '' && $t > (string) $groups[$fp]['last_seen']) {
                $groups[$fp]['last_seen'] = $t;
                $groups[$fp]['stack'] = $row['stack'] ?? $groups[$fp]['stack'];
                $groups[$fp]['trace_id'] = $row['trace_id'] ?? $groups[$fp]['trace_id'];
            }

            if ($uri !== '/') {
                $groups[$fp]['uris'][$uri] = ($groups[$fp]['uris'][$uri] ?? 0) + 1;
            }
        }

        foreach ($groups as &$group) {
            arsort($group['uris']);
            $uriList = [];
            foreach (array_slice($group['uris'], 0, 12, true) as $uri => $count) {
                $uriList[] = ['uri' => $uri, 'count' => $count];
            }
            $group['uris'] = $uriList;
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    private function hourly(array $rows): array
    {
        $buckets = array_fill(0, 24, 0);

        foreach ($rows as $row) {
            $t = (string) ($row['t'] ?? '');
            if (preg_match('/\s(\d{2}):/', $t, $m)) {
                $buckets[(int) $m[1]]++;
            }
        }

        return $buckets;
    }
}

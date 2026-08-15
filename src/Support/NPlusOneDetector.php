<?php

declare(strict_types=1);

namespace Konekt\Spoke\Support;

/**
 * Detekcija N+1 obrazaca nad baferovanim SQL upitima jednog requesta.
 */
class NPlusOneDetector
{
    /**
     * Grupiše normalizovane upite i vraća one koji prelaze prag.
     *
     * @param  list<array<string, mixed>>  $bufferedQueries
     * @return list<array{normalized_sql: string, count: int, total_ms: float, possible_relation: string|null}>
     */
    public function detect(array $bufferedQueries, int $threshold): array
    {
        if ($threshold < 2 || $bufferedQueries === []) {
            return [];
        }

        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];

        foreach ($bufferedQueries as $query) {
            $sql = (string) ($query['sql'] ?? '');

            if ($sql === '') {
                continue;
            }

            $normalized = QueryNormalizer::normalize($sql);
            $groups[$normalized][] = $query;
        }

        $detected = [];

        foreach ($groups as $normalized => $items) {
            $count = count($items);

            if ($count < $threshold) {
                continue;
            }

            $totalMs = 0.0;

            foreach ($items as $item) {
                $totalMs += (float) ($item['ms'] ?? 0);
            }

            $detected[] = [
                'normalized_sql' => $normalized,
                'count' => $count,
                'total_ms' => round($totalMs, 2),
                'possible_relation' => $this->guessRelation($normalized),
            ];
        }

        usort($detected, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $detected;
    }

    /**
     * Best-effort pogađanje tabela.kolona iz WHERE klauzule (nije Eloquent relacija).
     */
    public function guessRelation(string $normalizedSql): ?string
    {
        if (preg_match(
            '/\bwhere\b[\s\S]*?(?:^|[^a-zA-Z0-9_])(["`]?)([a-zA-Z_][a-zA-Z0-9_]*)\1\s*\.\s*(["`]?)([a-zA-Z_][a-zA-Z0-9_]*)\3\s*=\s*\?/iu',
            $normalizedSql,
            $m
        )) {
            return $m[2] . '.' . $m[4];
        }

        if (preg_match(
            '/\bfrom\s+(["`]?)([a-zA-Z_][a-zA-Z0-9_]*)\1[\s\S]*?\bwhere\s+(["`]?)([a-zA-Z_][a-zA-Z0-9_]*)\3\s*=\s*\?/iu',
            $normalizedSql,
            $m
        )) {
            return $m[2] . '.' . $m[4];
        }

        return null;
    }
}

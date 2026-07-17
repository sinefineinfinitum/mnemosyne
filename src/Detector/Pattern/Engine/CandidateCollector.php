<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Engine;

use PDO;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\PatternInterface;
use SineFine\Mnemosyne\Detector\Pattern\Model\PatternMatch;
use SineFine\Mnemosyne\Detector\Pattern\Model\PatternParticipant;

/**
 * Collects candidate pattern matches by executing SQL from PatternInterface::candidateSql().
 *
 * TRUST BOUNDARY: The SQL executed here originates exclusively from built-in
 * PatternInterface implementations registered in PatternRegistry. These are
 * compile-time constants, not user-supplied input. If the pattern catalog is
 * extended to load patterns from external sources (plugins, config files, etc.),
 * the SQL must be validated before execution (e.g., read-only assertion, EXPLAIN
 * pre-check, or allowlist of query structures).
 */
final class CandidateCollector
{
    public function __construct(
        private PDO              $pdo,
        private PatternInterface $pattern,
    ) {
    }

    /**
     * @return PatternMatch[]
     */
    public function collect(): array
    {
        $sql = $this->pattern->candidateSql();

        if (empty($sql)) {
            return [];
        }

        return $this->collectFromSql($sql);
    }

    /**
     * @return PatternMatch[]
     */
    private function collectFromSql(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /** @var array<int, list<array{entityId: int, role: string}>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $matchId = isset($row['match_id']) ? (int) $row['match_id'] : 0;
            $entityId = isset($row['entity_id']) ? (int) $row['entity_id'] : 0;
            if ($matchId === 0 || $entityId === 0) {
                continue;
            }

            $role = isset($row['role']) && is_string($row['role']) ? $row['role'] : '';
            if ($role === '') {
                continue;
            }
            $dedupeKey = $entityId . "\0" . $role;
            $grouped[$matchId][$dedupeKey] = ['entityId' => $entityId, 'role' => $role];
        }

        $matches = [];
        foreach ($grouped as $participants) {
            $patternParticipants = [];
            foreach ($participants as $p) {
                $patternParticipants[] = new PatternParticipant(role: $p['role'], entityId: $p['entityId']);
            }

            if ($patternParticipants !== []) {
                $matches[] = new PatternMatch(
                    pattern: $this->pattern,
                    participants: $patternParticipants,
                );
            }
        }

        return $matches;
    }
}

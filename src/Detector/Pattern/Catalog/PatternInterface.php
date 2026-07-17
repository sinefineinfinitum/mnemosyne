<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

interface PatternInterface
{
    public function name(): string;

    /**
     * @return string[]
     */
    public function roles(): array;

    /**
     * SQL must return rows with columns:
     *   - match_id  (int)  — groups participants belonging to the same pattern instance
     *   - entity_id (int)  — the participant entity
     *   - role      (string) — the role this entity plays in the pattern
     *
     * Use DENSE_RANK() OVER (ORDER BY ...) to generate deterministic match_ids
     * from sorted participant entity IDs.
     */
    public function candidateSql(): string;
}

<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Mediator implements PatternInterface
{
    public function name(): string
    {
        return 'mediator';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Mediator', 'Colleague'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    med.id      AS mediator_id,
                    colleague.id AS colleague_id
                FROM entities med
                JOIN relationships r_dep
                  ON r_dep.source_id = med.id AND r_dep.type = 'dependency'
                JOIN entities colleague ON r_dep.target_id = colleague.id
                JOIN relationships r_back
                  ON r_back.source_id = colleague.id
                  AND r_back.type = 'dependency'
                  AND r_back.target_id = med.id
                WHERE med.type = 'class'
                  AND colleague.type = 'class'
                  AND med.id != colleague.id
            )
            SQL;

    private const SELECT_MEDIATOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY mediator_id, colleague_id) AS match_id,
                   mediator_id AS entity_id, 'Mediator' AS role
            FROM base
            SQL;

    private const SELECT_COLLEAGUE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY mediator_id, colleague_id) AS match_id,
                   colleague_id AS entity_id, 'Colleague' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_MEDIATOR . "\n" .
            "UNION ALL\n" .
            self::SELECT_COLLEAGUE;
    }
}

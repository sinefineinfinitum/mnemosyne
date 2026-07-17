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
                JOIN properties p_med
                  ON p_med.entity_id = med.id
                  AND p_med.declared_type_entity_id IS NOT NULL
                JOIN entities colleague
                  ON p_med.declared_type_entity_id = colleague.id
                JOIN properties p_col
                  ON p_col.entity_id = colleague.id
                  AND p_col.declared_type_entity_id = med.id
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

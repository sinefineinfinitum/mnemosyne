<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Builder implements PatternInterface
{
    public function name(): string
    {
        return 'builder';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Builder', 'ConcreteBuilder', 'Director'];
    }

    private const CTE_CONCRETE = <<<'SQL'
            WITH concrete_pairs AS (
                SELECT b.id  AS builder_id,
                       cb.id AS concrete_id
                FROM entities b
                JOIN relationships r ON r.target_id = b.id AND r.type = 'implements'
                JOIN entities cb ON r.source_id = cb.id
                WHERE b.type = 'interface'
                  AND cb.type = 'class'
            )
            SQL;

    private const SELECT_BUILDER_FROM_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY builder_id, concrete_id) AS match_id,
                   builder_id AS entity_id, 'Builder' AS role
            FROM concrete_pairs
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY builder_id, concrete_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteBuilder' AS role
            FROM concrete_pairs
            SQL;

    private const CTE_DIRECTOR = <<<'SQL'
            director_pairs AS (
                SELECT b.id AS builder_id,
                       d.id AS director_id
                FROM entities b
                JOIN relationships r ON r.target_id = b.id AND r.type = 'dependency'
                JOIN entities d ON r.source_id = d.id
                WHERE b.type = 'interface'
            )
            SQL;

    private const SELECT_BUILDER_FROM_DIRECTOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY builder_id, director_id) AS match_id,
                   builder_id AS entity_id, 'Builder' AS role
            FROM director_pairs
            SQL;

    private const SELECT_DIRECTOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY builder_id, director_id) AS match_id,
                   director_id AS entity_id, 'Director' AS role
            FROM director_pairs
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_CONCRETE . ",\n" .
            self::CTE_DIRECTOR . "\n" .
            self::SELECT_BUILDER_FROM_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_BUILDER_FROM_DIRECTOR . "\n" .
            "UNION ALL\n" .
            self::SELECT_DIRECTOR;
    }
}

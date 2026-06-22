<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class TemplateMethod implements PatternInterface
{
    public function name(): string
    {
        return 'template_method';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['AbstractClass', 'ConcreteClass'];
    }

    private const CTE_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    abs.id   AS abstract_id,
                    child.id AS concrete_id
                FROM entities abs
                JOIN relationships r
                  ON r.target_id = abs.id AND r.type = 'extends'
                JOIN entities child ON r.source_id = child.id
                WHERE abs.type = 'class'
                  AND abs.is_abstract = 1
                  AND child.type = 'class'
                  AND child.is_abstract = 0
                  AND EXISTS (
                    SELECT 1 FROM members
                    WHERE entity_id = abs.id
                      AND member_type = 'method'
                      AND is_abstract = 1
                      AND visibility IN ('protected', 'public')
                  )
                  AND EXISTS (
                    SELECT 1 FROM members
                    WHERE entity_id = abs.id
                      AND member_type = 'method'
                      AND is_abstract = 0
                      AND visibility = 'public'
                  )
                  AND EXISTS (
                    SELECT 1 FROM relationships r2
                    WHERE r2.source_id = abs.id
                      AND r2.source_member_id IS NOT NULL
                      AND r2.type LIKE 'call_%'
                  )
            )
            SQL;

    private const SELECT_ABSTRACT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY abstract_id, concrete_id) AS match_id,
                   abstract_id AS entity_id, 'AbstractClass' AS role
            FROM base
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY abstract_id, concrete_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteClass' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_BASE . "\n" .
            self::SELECT_ABSTRACT . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE;
    }
}

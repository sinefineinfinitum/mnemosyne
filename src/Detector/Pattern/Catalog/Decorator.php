<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Decorator implements PatternInterface
{
    public function name(): string
    {
        return 'decorator';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Component', 'Decorator'];
    }

    private const CTE_BASE = <<<'SQL'
            WITH impl_pairs AS (
                SELECT c.id AS component_id,
                       d.id AS decorator_id
                FROM entities c
                JOIN relationships r ON r.target_id = c.id AND r.type = 'implements'
                JOIN entities d ON r.source_id = d.id
                WHERE c.type = 'interface'
                  AND d.type = 'class'
            ),
            decorators_with_component_field AS (
                SELECT ip.component_id, ip.decorator_id
                FROM impl_pairs ip
                JOIN properties p ON p.entity_id = ip.decorator_id
                  AND p.declared_type_entity_id = ip.component_id
            )
            SQL;

    private const SELECT_COMPONENT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY component_id) AS match_id,
                   component_id AS entity_id, 'Component' AS role
            FROM decorators_with_component_field
            SQL;

    private const SELECT_DECORATOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY component_id) AS match_id,
                   decorator_id AS entity_id, 'Decorator' AS role
            FROM decorators_with_component_field
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_BASE . "\n" .
            self::SELECT_COMPONENT . "\n" .
            "UNION ALL\n" .
            self::SELECT_DECORATOR;
    }
}

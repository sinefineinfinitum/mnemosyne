<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Composite implements PatternInterface
{
    public function name(): string
    {
        return 'composite';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Component', 'Composite'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    iface.id AS component_id,
                    comp.id  AS composite_id
                FROM entities iface
                JOIN relationships r_impl
                  ON r_impl.target_id = iface.id AND r_impl.type = 'implements'
                JOIN entities comp ON r_impl.source_id = comp.id
                JOIN members m ON m.entity_id = comp.id
                  AND m.member_type = 'property'
                JOIN types t ON t.owner_id = m.id AND t.owner_type = 'property'
                  AND t.name = iface.fqn
                WHERE iface.type = 'interface'
                  AND comp.type = 'class'
            )
            SQL;

    private const SELECT_COMPONENT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY component_id, composite_id) AS match_id,
                   component_id AS entity_id, 'Component' AS role
            FROM base
            SQL;

    private const SELECT_COMPOSITE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY component_id, composite_id) AS match_id,
                   composite_id AS entity_id, 'Composite' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_COMPONENT . "\n" .
            "UNION ALL\n" .
            self::SELECT_COMPOSITE;
    }
}

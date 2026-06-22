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

    private const CTE_IMPLEMENTS = <<<'SQL'
            WITH impl_pairs AS (
                SELECT c.id AS component_id,
                       d.id AS decorator_id
                FROM entities c
                JOIN relationships r ON r.target_id = c.id AND r.type = 'implements'
                JOIN entities d ON r.source_id = d.id
                WHERE c.type = 'interface'
                  AND d.type = 'class'
            )
            SQL;

    private const SELECT_COMPONENT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY component_id, decorator_id) AS match_id,
                   component_id AS entity_id, 'Component' AS role
            FROM impl_pairs
            SQL;

    private const SELECT_DECORATOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY component_id, decorator_id) AS match_id,
                   decorator_id AS entity_id, 'Decorator' AS role
            FROM impl_pairs
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_IMPLEMENTS . "\n" .
            self::SELECT_COMPONENT . "\n" .
            "UNION ALL\n" .
            self::SELECT_DECORATOR;
    }
}

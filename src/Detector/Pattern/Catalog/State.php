<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class State implements PatternInterface
{
    public function name(): string
    {
        return 'state';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['State', 'ConcreteState', 'Context'];
    }

    private const CTE_BASE = <<<'SQL'
            WITH impl_pairs AS (
                SELECT s.id    AS state_id,
                       impl.id AS concrete_id
                FROM entities s
                JOIN relationships r ON r.type = 'implements' AND r.target_id = s.id
                JOIN entities impl ON impl.id = r.source_id
                WHERE s.type = 'interface'
            ),
            multi_impls AS (
                SELECT state_id
                FROM impl_pairs
                GROUP BY state_id
                HAVING COUNT(DISTINCT concrete_id) >= 2
            ),
            ctx_with_state_field AS (
                SELECT DISTINCT mi.state_id,
                       p.entity_id AS context_id
                FROM multi_impls mi
                JOIN properties p ON p.declared_type_entity_id = mi.state_id
            )
            SQL;

    private const SELECT_STATE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY cw.state_id) AS match_id,
                   cw.state_id AS entity_id, 'State' AS role
            FROM ctx_with_state_field cw
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY ip.state_id) AS match_id,
                   ip.concrete_id AS entity_id, 'ConcreteState' AS role
            FROM impl_pairs ip
            JOIN ctx_with_state_field cw ON cw.state_id = ip.state_id
            SQL;

    private const SELECT_CONTEXT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY cw.state_id) AS match_id,
                   cw.context_id AS entity_id, 'Context' AS role
            FROM ctx_with_state_field cw
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_BASE . "\n" .
            self::SELECT_STATE . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONTEXT;
    }
}

<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Strategy implements PatternInterface
{
    public function name(): string
    {
        return 'strategy';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Strategy', 'ConcreteStrategy', 'Context'];
    }

    private const CTE_IMPLS = <<<'SQL'
            WITH impl_pairs AS (
                SELECT s.id    AS strategy_id,
                       impl.id AS concrete_id
                FROM entities s
                JOIN relationships r ON r.type = 'implements' AND r.target_id = s.id
                JOIN entities impl ON impl.id = r.source_id
                WHERE s.type = 'interface'
            ),
            multi_impls AS (
                SELECT strategy_id
                FROM impl_pairs
                GROUP BY strategy_id
                HAVING COUNT(DISTINCT concrete_id) >= 2
            )
            SQL;

    private const CTE_CTX = <<<'SQL'
            ctx_uses_strategy AS (
                SELECT DISTINCT mi.strategy_id,
                       p.entity_id AS context_id
                FROM multi_impls mi
                JOIN properties p ON p.declared_type_entity_id = mi.strategy_id
                UNION
                SELECT DISTINCT mi.strategy_id,
                       m.entity_id AS context_id
                FROM multi_impls mi
                JOIN methods m ON m.name = '__construct'
                JOIN parameters p ON p.method_id = m.id
                  AND p.declared_type_entity_id = mi.strategy_id
            )
            SQL;

    private const SELECT_STRATEGY = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY us.strategy_id) AS match_id,
                   us.strategy_id AS entity_id, 'Strategy' AS role
            FROM ctx_uses_strategy us
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY ip.strategy_id) AS match_id,
                   ip.concrete_id AS entity_id, 'ConcreteStrategy' AS role
            FROM impl_pairs ip
            JOIN ctx_uses_strategy us ON us.strategy_id = ip.strategy_id
            SQL;

    private const SELECT_CONTEXT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY us.strategy_id) AS match_id,
                   us.context_id AS entity_id, 'Context' AS role
            FROM ctx_uses_strategy us
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_IMPLS . ",\n" .
            self::CTE_CTX . "\n" .
            self::SELECT_STRATEGY . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONTEXT;
    }
}

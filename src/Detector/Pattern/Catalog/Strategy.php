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

    private const SELECT_STRATEGY_FROM_IMPLS = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY ip.strategy_id, ip.concrete_id) AS match_id,
                   ip.strategy_id AS entity_id, 'Strategy' AS role
            FROM impl_pairs ip
            JOIN multi_impls mi ON mi.strategy_id = ip.strategy_id
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY ip.strategy_id, ip.concrete_id) AS match_id,
                   ip.concrete_id AS entity_id, 'ConcreteStrategy' AS role
            FROM impl_pairs ip
            JOIN multi_impls mi ON mi.strategy_id = ip.strategy_id
            SQL;

    private const CTE_CTX = <<<'SQL'
            ctx_pairs AS (
                SELECT s.id   AS strategy_id,
                       ctx.id AS context_id
                FROM entities s
                JOIN relationships r ON r.type = 'dependency' AND r.target_id = s.id
                JOIN entities ctx ON ctx.id = r.source_id
                WHERE s.type = 'interface'
            )
            SQL;

    private const SELECT_STRATEGY_FROM_CTX = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY cp.strategy_id) AS match_id,
                   cp.strategy_id AS entity_id, 'Strategy' AS role
            FROM ctx_pairs cp
            JOIN multi_impls mi ON mi.strategy_id = cp.strategy_id
            SQL;

    private const SELECT_CONTEXT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY cp.strategy_id) AS match_id,
                   cp.context_id AS entity_id, 'Context' AS role
            FROM ctx_pairs cp
            JOIN multi_impls mi ON mi.strategy_id = cp.strategy_id
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_IMPLS . ",\n" .
            self::CTE_CTX . "\n" .
            self::SELECT_STRATEGY_FROM_IMPLS . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_STRATEGY_FROM_CTX . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONTEXT;
    }
}

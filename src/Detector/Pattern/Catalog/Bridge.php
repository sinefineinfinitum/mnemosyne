<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Bridge implements PatternInterface
{
    public function name(): string
    {
        return 'bridge';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Abstraction', 'Implementor', 'ConcreteImplementor'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    abs.id   AS abstraction_id,
                    impl.id  AS implementor_id,
                    ci.id    AS concrete_id
                FROM entities abs
                JOIN relationships r_dep
                  ON r_dep.source_id = abs.id AND r_dep.type = 'dependency'
                JOIN entities impl ON r_dep.target_id = impl.id
                JOIN relationships r_impl
                  ON r_impl.target_id = impl.id AND r_impl.type = 'implements'
                JOIN entities ci ON r_impl.source_id = ci.id
                WHERE abs.type = 'class'
                  AND impl.type = 'interface'
                  AND ci.type = 'class'
                  AND ci.is_abstract = 0
            )
            SQL;

    private const SELECT_ABSTRACTION = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY abstraction_id, implementor_id, concrete_id) AS match_id,
                   abstraction_id AS entity_id, 'Abstraction' AS role
            FROM base
            SQL;

    private const SELECT_IMPLEMENTOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY abstraction_id, implementor_id, concrete_id) AS match_id,
                   implementor_id AS entity_id, 'Implementor' AS role
            FROM base
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY abstraction_id, implementor_id, concrete_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteImplementor' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_ABSTRACTION . "\n" .
            "UNION ALL\n" .
            self::SELECT_IMPLEMENTOR . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE;
    }
}

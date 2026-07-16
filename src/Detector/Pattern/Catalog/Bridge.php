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
                JOIN properties p ON p.entity_id = abs.id
                  AND p.declared_type_entity_id = impl.id
                JOIN entities impl ON impl.id = p.declared_type_entity_id
                JOIN relationships r_impl
                  ON r_impl.target_id = impl.id AND r_impl.type = 'implements'
                JOIN entities ci ON r_impl.source_id = ci.id
                WHERE abs.type = 'class'
                  AND impl.type = 'interface'
                  AND ci.type = 'class'
                  AND ci.is_abstract = 0
            ),
            pairs AS (
                SELECT DISTINCT
                    abstraction_id,
                    implementor_id,
                    DENSE_RANK() OVER (ORDER BY abstraction_id, implementor_id) AS match_id
                FROM base
            )
            SQL;

    private const SELECT_ABSTRACTION = <<<'SQL'
            SELECT match_id,
                   abstraction_id AS entity_id, 'Abstraction' AS role
            FROM pairs
            SQL;

    private const SELECT_IMPLEMENTOR = <<<'SQL'
            SELECT match_id,
                   implementor_id AS entity_id, 'Implementor' AS role
            FROM pairs
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DISTINCT p.match_id,
                   b.concrete_id AS entity_id, 'ConcreteImplementor' AS role
            FROM base b
            JOIN pairs p ON p.abstraction_id = b.abstraction_id AND p.implementor_id = b.implementor_id
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

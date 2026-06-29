<?php declare(strict_types=1);

namespace SineFine\Ponymator\Detector\Pattern\Catalog;

final class Iterator implements PatternInterface
{
    public function name(): string
    {
        return 'iterator';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Iterator', 'ConcreteIterator'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    iface.id AS iterator_id,
                    ci.id    AS concrete_id
                FROM entities iface
                JOIN relationships r_impl
                  ON r_impl.target_id = iface.id AND r_impl.type = 'implements'
                JOIN entities ci ON r_impl.source_id = ci.id
                WHERE iface.type = 'interface'
                  AND ci.type = 'class'
                  AND ci.is_abstract = 0
                  AND (
                    iface.fqn LIKE '%Iterator'
                    OR iface.short_name IN ('Iterator', 'IteratorAggregate')
                    OR EXISTS (
                        SELECT 1 FROM members m
                        WHERE m.entity_id = ci.id
                          AND m.member_type = 'method'
                          AND m.name IN ('current', 'next', 'rewind', 'valid', 'key')
                    )
                  )
            )
            SQL;

    private const SELECT_ITERATOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY iterator_id, concrete_id) AS match_id,
                   iterator_id AS entity_id, 'Iterator' AS role
            FROM base
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY iterator_id, concrete_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteIterator' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_ITERATOR . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE;
    }
}

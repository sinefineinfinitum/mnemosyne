<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Observer implements PatternInterface
{
    public function name(): string
    {
        return 'observer';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Subject', 'Observer', 'ConcreteObserver'];
    }

    private const CTE_SUBJECTS = <<<'SQL'
            WITH subject_interfaces AS (
                SELECT e.id
                FROM entities e
                JOIN methods m ON m.entity_id = e.id
                  AND m.name IN ('attach', 'detach', 'subscribe', 'unsubscribe', 'addListener', 'removeListener', 'notify')
                WHERE e.type = 'interface'
                GROUP BY e.id
                HAVING COUNT(DISTINCT m.id) >= 2
            ),
            observer_interfaces AS (
                SELECT e.id
                FROM entities e
                JOIN methods m ON m.entity_id = e.id
                  AND m.name IN ('update', 'handle', 'onEvent', 'notify')
                WHERE e.type = 'interface'
            )
            SQL;

    private const CTE_SUBJECT_OBSERVER = <<<'SQL'
            subject_observer_pairs AS (
                SELECT si.id AS subject_id,
                       p.declared_type_entity_id AS observer_id
                FROM subject_interfaces si
                JOIN methods m ON m.entity_id = si.id
                  AND m.name IN ('attach', 'detach', 'subscribe', 'unsubscribe', 'addListener', 'removeListener')
                JOIN parameters p ON p.method_id = m.id
                  AND p.declared_type_entity_id IN (SELECT id FROM observer_interfaces)
            )
            SQL;

    private const CTE_CONCRETE_OBSERVERS = <<<'SQL'
            concrete_observers AS (
                SELECT oi.id AS observer_id,
                       co.id AS concrete_id
                FROM observer_interfaces oi
                JOIN relationships r_impl
                  ON r_impl.target_id = oi.id AND r_impl.type = 'implements'
                JOIN entities co ON r_impl.source_id = co.id
                WHERE co.type = 'class'
                  AND co.is_abstract = 0
            )
            SQL;

    private const SELECT_SUBJECT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY subject_id, observer_id) AS match_id,
                   subject_id AS entity_id, 'Subject' AS role
            FROM subject_observer_pairs
            SQL;

    private const SELECT_OBSERVER = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY subject_id, observer_id) AS match_id,
                   observer_id AS entity_id, 'Observer' AS role
            FROM subject_observer_pairs
            SQL;

    private const SELECT_OBSERVER_FROM_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY observer_id, concrete_id) AS match_id,
                   observer_id AS entity_id, 'Observer' AS role
            FROM concrete_observers
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY observer_id, concrete_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteObserver' AS role
            FROM concrete_observers
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_SUBJECTS . ",\n" .
            self::CTE_SUBJECT_OBSERVER . ",\n" .
            self::CTE_CONCRETE_OBSERVERS . "\n" .
            self::SELECT_SUBJECT . "\n" .
            "UNION ALL\n" .
            self::SELECT_OBSERVER . "\n" .
            "UNION ALL\n" .
            self::SELECT_OBSERVER_FROM_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE;
    }
}

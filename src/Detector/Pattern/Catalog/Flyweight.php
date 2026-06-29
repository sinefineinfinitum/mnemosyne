<?php declare(strict_types=1);

namespace SineFine\Ponymator\Detector\Pattern\Catalog;

final class Flyweight implements PatternInterface
{
    public function name(): string
    {
        return 'flyweight';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Flyweight', 'FlyweightFactory'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    fw.id      AS flyweight_id,
                    factory.id AS factory_id
                FROM entities fw
                JOIN relationships r_impl
                  ON r_impl.target_id = fw.id AND r_impl.type = 'implements'
                JOIN entities factory
                JOIN relationships r_creates
                  ON r_creates.source_id = factory.id
                  AND r_creates.type IN ('creates', 'creates_strong')
                  AND r_creates.target_id = fw.id
                WHERE fw.type = 'interface'
                  AND factory.type = 'class'
                  AND EXISTS (
                    SELECT 1 FROM members
                    WHERE entity_id = factory.id
                      AND member_type = 'property'
                      AND is_static = 1
                  )
            )
            SQL;

    private const SELECT_FLYWEIGHT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY flyweight_id, factory_id) AS match_id,
                   flyweight_id AS entity_id, 'Flyweight' AS role
            FROM base
            SQL;

    private const SELECT_FACTORY = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY flyweight_id, factory_id) AS match_id,
                   factory_id AS entity_id, 'FlyweightFactory' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_FLYWEIGHT . "\n" .
            "UNION ALL\n" .
            self::SELECT_FACTORY;
    }
}

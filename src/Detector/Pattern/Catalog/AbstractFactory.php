<?php declare(strict_types=1);

namespace SineFine\Ponymator\Detector\Pattern\Catalog;

final class AbstractFactory implements PatternInterface
{
    public function name(): string
    {
        return 'abstract_factory';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['AbstractFactory', 'ConcreteFactory', 'Product'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH factory_interfaces AS (
                SELECT e.id
                FROM entities e
                JOIN members m ON m.entity_id = e.id AND m.member_type = 'method'
                JOIN types t ON t.owner_id = m.id AND t.owner_type = 'return'
                  AND t.name NOT IN ('void','never','null','mixed','int','string','float','bool','array')
                WHERE e.type = 'interface'
                GROUP BY e.id
                HAVING COUNT(DISTINCT m.id) >= 2
            ),
            base AS (
                SELECT fi.id       AS factory_id,
                       cf.id       AS concrete_id,
                       product.id  AS product_id
                FROM factory_interfaces fi
                JOIN relationships r_impl
                  ON r_impl.target_id = fi.id AND r_impl.type = 'implements'
                JOIN entities cf ON r_impl.source_id = cf.id
                JOIN relationships r_creates
                  ON r_creates.source_id = cf.id AND r_creates.type IN ('creates', 'creates_strong')
                JOIN entities product ON r_creates.target_id = product.id
                WHERE cf.type = 'class'
                  AND cf.is_abstract = 0
            )
            SQL;

    private const SELECT_FACTORY = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY factory_id) AS match_id,
                   factory_id AS entity_id, 'AbstractFactory' AS role
            FROM base
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY factory_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteFactory' AS role
            FROM base
            SQL;

    private const SELECT_PRODUCT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY factory_id) AS match_id,
                   product_id AS entity_id, 'Product' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_FACTORY . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_PRODUCT;
    }
}

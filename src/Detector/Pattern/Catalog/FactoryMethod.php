<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class FactoryMethod implements PatternInterface
{
    public function name(): string
    {
        return 'factory_method';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Creator', 'ConcreteCreator', 'Product'];
    }

    private const CTE_CREATORS = <<<'SQL'
            WITH creators AS (
                SELECT DISTINCT e.id
                FROM entities e
                JOIN members m ON m.entity_id = e.id AND m.member_type = 'method'
                JOIN types t ON t.owner_id = m.id AND t.owner_type = 'return'
                  AND t.name NOT IN ('void','never','null','mixed','int','string','float','bool','array')
                WHERE (e.type = 'interface' OR (e.type = 'class' AND e.is_abstract = 1))
                  AND (m.is_abstract = 1 OR e.type = 'interface')
            ),
            base AS (
                SELECT c.id       AS creator_id,
                       child.id   AS concrete_id,
                       product.id AS product_id
                FROM creators c
                JOIN relationships r_child
                  ON r_child.target_id = c.id AND r_child.type IN ('extends', 'implements')
                JOIN entities child ON r_child.source_id = child.id
                JOIN relationships r_creates
                  ON r_creates.source_id = child.id AND r_creates.type IN ('creates', 'creates_strong')
                JOIN entities product ON r_creates.target_id = product.id
                WHERE child.type = 'class'
                  AND child.is_abstract = 0
            )
            SQL;

    private const SELECT_CREATOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY creator_id) AS match_id,
                   creator_id AS entity_id, 'Creator' AS role
            FROM base
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY creator_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteCreator' AS role
            FROM base
            SQL;

    private const SELECT_PRODUCT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY creator_id) AS match_id,
                   product_id AS entity_id, 'Product' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_CREATORS . "\n" .
            self::SELECT_CREATOR . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_PRODUCT;
    }
}

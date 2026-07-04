<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Visitor implements PatternInterface
{
    public function name(): string
    {
        return 'visitor';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Visitor', 'ConcreteVisitor', 'Element', 'ConcreteElement'];
    }

    private const CTE_BASE = <<<'SQL'
            WITH visitor_methods AS (
                SELECT DISTINCT
                    m.entity_id AS visitor_id,
                    t.entity_id AS element_param_id
                FROM members m
                JOIN types t ON t.owner_id = m.id AND t.owner_type = 'param'
                WHERE m.member_type = 'method'
                  AND m.name LIKE 'visit%'
                  AND t.entity_id IS NOT NULL
            ),
            element_accepts AS (
                SELECT DISTINCT
                    m.entity_id AS element_id,
                    t.entity_id AS visitor_param_id
                FROM members m
                JOIN types t ON t.owner_id = m.id AND t.owner_type = 'param'
                WHERE m.member_type = 'method'
                  AND m.name = 'accept'
                  AND t.entity_id IS NOT NULL
            ),
            double_dispatch AS (
                SELECT
                    vm.visitor_id,
                    ea.element_id
                FROM visitor_methods vm
                JOIN element_accepts ea ON ea.visitor_param_id = vm.visitor_id
                WHERE vm.element_param_id = ea.element_id
            ),
            visitor_hierarchy AS (
                SELECT DISTINCT
                    dd.visitor_id,
                    COALESCE(impl.source_id, dd.visitor_id) AS visitor_entity_id
                FROM double_dispatch dd
                LEFT JOIN relationships impl
                  ON impl.target_id = dd.visitor_id AND impl.type = 'implements'
            ),
            element_hierarchy AS (
                SELECT DISTINCT
                    dd.element_id,
                    COALESCE(impl.source_id, dd.element_id) AS element_entity_id
                FROM double_dispatch dd
                LEFT JOIN relationships impl
                  ON impl.target_id = dd.element_id AND impl.type = 'implements'
            ),
            all_visitors AS (
                SELECT vh.visitor_id, vh.visitor_entity_id
                FROM visitor_hierarchy vh
                UNION
                SELECT vh.visitor_id, vh2.visitor_entity_id
                FROM visitor_hierarchy vh
                JOIN relationships ext ON ext.source_id = vh.visitor_entity_id AND ext.type = 'extends'
                JOIN visitor_hierarchy vh2 ON vh2.visitor_id = ext.target_id
            ),
            all_elements AS (
                SELECT eh.element_id, eh.element_entity_id
                FROM element_hierarchy eh
                UNION
                SELECT eh.element_id, eh2.element_entity_id
                FROM element_hierarchy eh
                JOIN relationships ext ON ext.source_id = eh.element_entity_id AND ext.type = 'extends'
                JOIN element_hierarchy eh2 ON eh2.element_id = ext.target_id
            ),
            base AS (
                SELECT
                    av.visitor_id,
                    av.visitor_entity_id,
                    ae.element_id,
                    ae.element_entity_id
                FROM all_visitors av
                JOIN all_elements ae ON 1=1
                WHERE EXISTS (
                    SELECT 1 FROM double_dispatch dd
                    WHERE dd.visitor_id = av.visitor_id
                      AND dd.element_id = ae.element_id
                )
            )
            SQL;

    private const SELECT_VISITOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY visitor_id) AS match_id,
                   visitor_entity_id AS entity_id,
                   CASE WHEN visitor_entity_id = visitor_id THEN 'Visitor' ELSE 'ConcreteVisitor' END AS role
            FROM base
            SQL;

    private const SELECT_ELEMENT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY visitor_id, element_id) AS match_id,
                   element_entity_id AS entity_id,
                   CASE WHEN element_entity_id = element_id THEN 'Element' ELSE 'ConcreteElement' END AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_BASE . "\n" .
            self::SELECT_VISITOR . "\n" .
            "UNION ALL\n" .
            self::SELECT_ELEMENT;
    }
}

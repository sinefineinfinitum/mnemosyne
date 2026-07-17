<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Singleton implements PatternInterface
{
    public function name(): string
    {
        return 'singleton';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Singleton'];
    }

    public function candidateSql(): string
    {
        return <<<'SQL'
            SELECT e.id AS match_id, e.id AS entity_id, 'Singleton' AS role
            FROM entities e
            WHERE e.type = 'class'
              AND e.is_abstract = 0
              AND EXISTS (
                SELECT 1 FROM methods
                WHERE entity_id = e.id
                  AND name = '__construct'
                  AND visibility = 'private'
              )
              AND EXISTS (
                SELECT 1 FROM properties p
                WHERE p.entity_id = e.id
                  AND p.visibility = 'private'
                  AND p.is_static = 1
                  AND (p.declared_type_name IN ('self', 'static') OR p.declared_type_name = e.fqn)
              )
              AND EXISTS (
                SELECT 1 FROM methods m
                WHERE m.entity_id = e.id
                  AND m.visibility = 'public'
                  AND m.is_static = 1
                  AND (m.return_type_name IN ('self', 'static') OR m.return_type_name = e.fqn)
              )
            SQL;
    }
}

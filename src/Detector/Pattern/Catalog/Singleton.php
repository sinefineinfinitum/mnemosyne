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
                SELECT 1 FROM members
                WHERE entity_id = e.id
                  AND member_type = 'method'
                  AND name = '__construct'
                  AND visibility = 'private'
              )
              AND EXISTS (
                SELECT 1 FROM members m
                WHERE m.entity_id = e.id
                  AND m.member_type = 'property'
                  AND m.visibility = 'private'
                  AND m.is_static = 1
                  AND (m.declared_type IN ('self', 'static') OR m.declared_type = e.fqn)
              )
              AND EXISTS (
                SELECT 1 FROM members m
                WHERE m.entity_id = e.id
                  AND m.member_type = 'method'
                  AND m.visibility = 'public'
                  AND m.is_static = 1
                  AND (m.return_type IN ('self', 'static') OR m.return_type = e.fqn)
              )
            SQL;
    }
}

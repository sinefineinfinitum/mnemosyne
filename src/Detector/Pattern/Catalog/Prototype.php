<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Prototype implements PatternInterface
{
    public function name(): string
    {
        return 'prototype';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Prototype'];
    }

    public function candidateSql(): string
    {
        return <<<'SQL'
            SELECT e.id AS match_id, e.id AS entity_id, 'Prototype' AS role
            FROM entities e
            WHERE e.type = 'class'
              AND e.is_abstract = 0
              AND EXISTS (
                SELECT 1 FROM members
                WHERE entity_id = e.id
                  AND member_type = 'method'
                  AND name = '__clone'
              )
            SQL;
    }
}

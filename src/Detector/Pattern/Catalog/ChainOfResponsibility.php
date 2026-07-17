<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class ChainOfResponsibility implements PatternInterface
{
    public function name(): string
    {
        return 'chain_of_responsibility';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Handler', 'ConcreteHandler'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    iface.id AS handler_id,
                    ch.id    AS concrete_id
                FROM entities iface
                JOIN relationships r_impl
                  ON r_impl.target_id = iface.id AND r_impl.type = 'implements'
                JOIN entities ch ON r_impl.source_id = ch.id
                JOIN properties p ON p.entity_id = ch.id
                  AND p.declared_type_entity_id = iface.id
                WHERE iface.type = 'interface'
                  AND ch.type = 'class'
            )
            SQL;

    private const SELECT_HANDLER = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY handler_id, concrete_id) AS match_id,
                   handler_id AS entity_id, 'Handler' AS role
            FROM base
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY handler_id, concrete_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteHandler' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_HANDLER . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE;
    }
}

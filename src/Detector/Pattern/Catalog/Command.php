<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Command implements PatternInterface
{
    public function name(): string
    {
        return 'command';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Command', 'ConcreteCommand', 'Invoker'];
    }

    private const CTE_COMMANDS = <<<'SQL'
            WITH command_interfaces AS (
                SELECT e.id
                FROM entities e
                JOIN members m ON m.entity_id = e.id
                  AND m.member_type = 'method'
                  AND m.name IN ('execute', 'run', 'handle')
                WHERE e.type = 'interface'
            ),
            concrete_commands AS (
                SELECT ci.id AS command_id,
                       cc.id AS concrete_id
                FROM command_interfaces ci
                JOIN relationships r_impl
                  ON r_impl.target_id = ci.id AND r_impl.type = 'implements'
                JOIN entities cc ON r_impl.source_id = cc.id
                WHERE cc.type = 'class'
                  AND cc.is_abstract = 0
            )
            SQL;

    private const CTE_INVOKER = <<<'SQL'
            invoker_pairs AS (
                SELECT ci.id   AS command_id,
                       inv.id  AS invoker_id
                FROM command_interfaces ci
                JOIN relationships r_dep
                  ON r_dep.target_id = ci.id AND r_dep.type = 'dependency'
                JOIN entities inv ON r_dep.source_id = inv.id
                WHERE inv.type = 'class'
            )
            SQL;

    private const SELECT_COMMAND_FROM_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY command_id, concrete_id) AS match_id,
                   command_id AS entity_id, 'Command' AS role
            FROM concrete_commands
            SQL;

    private const SELECT_CONCRETE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY command_id, concrete_id) AS match_id,
                   concrete_id AS entity_id, 'ConcreteCommand' AS role
            FROM concrete_commands
            SQL;

    private const SELECT_COMMAND_FROM_INVOKER = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY command_id, invoker_id) AS match_id,
                   command_id AS entity_id, 'Command' AS role
            FROM invoker_pairs
            SQL;

    private const SELECT_INVOKER = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY command_id, invoker_id) AS match_id,
                   invoker_id AS entity_id, 'Invoker' AS role
            FROM invoker_pairs
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_COMMANDS . ",\n" .
            self::CTE_INVOKER . "\n" .
            self::SELECT_COMMAND_FROM_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_CONCRETE . "\n" .
            "UNION ALL\n" .
            self::SELECT_COMMAND_FROM_INVOKER . "\n" .
            "UNION ALL\n" .
            self::SELECT_INVOKER;
    }
}

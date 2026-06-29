<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Adapter implements PatternInterface
{
    public function name(): string
    {
        return 'adapter';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Adapter', 'Target', 'Adaptee'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT adapter.id AS adapter_id,
                       iface.id   AS target_id,
                       adaptee.id AS adaptee_id
                FROM entities adapter
                JOIN relationships r_impl
                  ON r_impl.source_id = adapter.id AND r_impl.type = 'implements'
                JOIN entities iface
                  ON iface.id = r_impl.target_id AND iface.type = 'interface'
                JOIN relationships r_dep
                  ON r_dep.source_id = adapter.id
                 AND r_dep.type IN ('dependency', 'creates', 'creates_strong')
                 AND r_dep.target_id IS NOT NULL
                 AND r_dep.target_id != iface.id
                 AND r_dep.target_id NOT IN (
                     SELECT r2.target_id FROM relationships r2
                     WHERE r2.source_id = iface.id AND r2.type IN ('extends', 'implements')
                 )
                JOIN entities adaptee ON adaptee.id = r_dep.target_id
                WHERE adapter.type = 'class'
            )
            SQL;

    private const SELECT_ADAPTER = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY adapter_id, target_id) AS match_id,
                   adapter_id AS entity_id, 'Adapter' AS role
            FROM base
            SQL;

    private const SELECT_TARGET = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY adapter_id, target_id) AS match_id,
                   target_id AS entity_id, 'Target' AS role
            FROM base
            SQL;

    private const SELECT_ADAPTEE = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY adapter_id, target_id) AS match_id,
                   adaptee_id AS entity_id, 'Adaptee' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_ADAPTER . "\n" .
            "UNION ALL\n" .
            self::SELECT_TARGET . "\n" .
            "UNION ALL\n" .
            self::SELECT_ADAPTEE;
    }
}

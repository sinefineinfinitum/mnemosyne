<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Catalog;

final class Memento implements PatternInterface
{
    public function name(): string
    {
        return 'memento';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Originator', 'Memento'];
    }

    private const PATTERN_BASE = <<<'SQL'
            WITH base AS (
                SELECT DISTINCT
                    orig.id    AS originator_id,
                    memento.id AS memento_id
                FROM entities orig
                JOIN relationships r_creates
                  ON r_creates.source_id = orig.id
                  AND r_creates.type IN ('creates', 'creates_strong')
                JOIN entities memento ON r_creates.target_id = memento.id
                JOIN methods m_save ON m_save.entity_id = orig.id
                  AND m_save.name IN ('save', 'saveState', 'createMemento', 'getState')
                JOIN methods m_restore ON m_restore.entity_id = orig.id
                  AND m_restore.name IN ('restore', 'restoreState', 'setMemento', 'setState')
                WHERE orig.type = 'class'
                  AND memento.type = 'class'
            )
            SQL;

    private const SELECT_ORIGINATOR = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY originator_id, memento_id) AS match_id,
                   originator_id AS entity_id, 'Originator' AS role
            FROM base
            SQL;

    private const SELECT_MEMENTO = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY originator_id, memento_id) AS match_id,
                   memento_id AS entity_id, 'Memento' AS role
            FROM base
            SQL;

    public function candidateSql(): string
    {
        return
            self::PATTERN_BASE . "\n" .
            self::SELECT_ORIGINATOR . "\n" .
            "UNION ALL\n" .
            self::SELECT_MEMENTO;
    }
}

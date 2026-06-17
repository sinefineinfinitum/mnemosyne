<?php declare(strict_types=1);

namespace SineFine\Ponymator\Graph\Experimental;

use PDO;

/**
 * @experimental This API is experimental and may change without notice.\
 * @since        4.0.0
 */
final class Schema
{
    public const TABLES = [
        'namespaces',
        'files',
        'entities',
        'members',
        'parameters',
        'types',
        'relationships',
    ];

    public static function create(PDO $pdo): void
    {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');

        foreach (self::ddl() as $sql) {
            $pdo->exec($sql);
        }
    }

    public static function drop(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys=OFF');
        foreach (array_reverse(self::TABLES) as $table) {
            $pdo->exec("DROP TABLE IF EXISTS \"$table\"");
        }
        $pdo->exec('PRAGMA foreign_keys=ON');
    }

    /**
     * @return list<string>
     */
    private static function ddl(): array
    {
        return [
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS namespaces (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                fqn        TEXT    NOT NULL UNIQUE,
                label      TEXT    NOT NULL,
                parent_id  INTEGER REFERENCES namespaces(id) ON DELETE SET NULL,
                depth      INTEGER NOT NULL DEFAULT 0
            )
            SQL,

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS files (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                path          TEXT    NOT NULL UNIQUE,
                relative_path TEXT,
                hash          TEXT
            )
            SQL,

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS entities (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                fqn          TEXT    NOT NULL UNIQUE,
                short_name   TEXT    NOT NULL,
                type         TEXT    NOT NULL CHECK(type IN ('class','interface','trait','enum')),
                namespace_id INTEGER REFERENCES namespaces(id) ON DELETE SET NULL,
                file_id      INTEGER REFERENCES files(id)      ON DELETE SET NULL,
                parent_class TEXT,
                is_abstract  INTEGER NOT NULL DEFAULT 0,
                is_final     INTEGER NOT NULL DEFAULT 0,
                is_readonly  INTEGER NOT NULL DEFAULT 0,
                scalar_type  TEXT
            )
            SQL,

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS members (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_id           INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
                name                TEXT    NOT NULL,
                member_type         TEXT    NOT NULL CHECK(member_type IN ('method','property','constant','case')),
                visibility          TEXT    CHECK(visibility IN ('public','protected','private')),
                is_static           INTEGER NOT NULL DEFAULT 0,
                is_abstract         INTEGER NOT NULL DEFAULT 0,
                is_final            INTEGER NOT NULL DEFAULT 0,
                is_readonly         INTEGER NOT NULL DEFAULT 0,
                declared_type       TEXT,
                default_value       TEXT,
                return_type         TEXT,
                UNIQUE(entity_id, name, member_type)
            )
            SQL,

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS parameters (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                member_id            INTEGER NOT NULL REFERENCES members(id) ON DELETE CASCADE,
                name                 TEXT    NOT NULL,
                declared_type        TEXT,
                default_value        TEXT,
                is_variadic          INTEGER NOT NULL DEFAULT 0,
                is_passed_by_reference INTEGER NOT NULL DEFAULT 0,
                position             INTEGER NOT NULL
            )
            SQL,

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS types (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_type        TEXT    NOT NULL CHECK(owner_type IN ('param','return','property')),
                owner_id          INTEGER NOT NULL,
                name              TEXT    NOT NULL,
                entity_id         INTEGER REFERENCES entities(id) ON DELETE SET NULL,
                is_union          INTEGER NOT NULL DEFAULT 0,
                is_intersection   INTEGER NOT NULL DEFAULT 0,
                position          INTEGER NOT NULL DEFAULT 0
            )
            SQL,

            'CREATE INDEX IF NOT EXISTS idx_types_owner ON types(owner_type, owner_id)',
            'CREATE INDEX IF NOT EXISTS idx_types_entity ON types(entity_id)',

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS relationships (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                source_id        INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
                target_id        INTEGER          REFERENCES entities(id) ON DELETE SET NULL,
                target_fqn       TEXT,
                target_member_name TEXT,
                type             TEXT    NOT NULL CHECK(type IN (
                    'extends','implements','uses_trait',
                    'creates','creates_strong',
                    'call_static_weak','call_static_strong',
                    'call_dynamic_weak','call_dynamic_strong',
                    'call_global_weak','call_global_strong',
                    'dependency'
                )),
                source_member_id INTEGER          REFERENCES members(id) ON DELETE SET NULL
            )
            SQL,

            'CREATE INDEX IF NOT EXISTS idx_entities_namespace  ON entities(namespace_id)',
            'CREATE INDEX IF NOT EXISTS idx_entities_file       ON entities(file_id)',
            'CREATE INDEX IF NOT EXISTS idx_entities_type       ON entities(type)',
            'CREATE INDEX IF NOT EXISTS idx_members_entity      ON members(entity_id)',
            'CREATE INDEX IF NOT EXISTS idx_parameters_member   ON parameters(member_id)',
            'CREATE INDEX IF NOT EXISTS idx_rel_source          ON relationships(source_id)',
            'CREATE INDEX IF NOT EXISTS idx_rel_target          ON relationships(target_id)',
            'CREATE INDEX IF NOT EXISTS idx_rel_type            ON relationships(type)',
            'CREATE INDEX IF NOT EXISTS idx_rel_source_member   ON relationships(source_member_id)',
            'CREATE INDEX IF NOT EXISTS idx_namespaces_parent   ON namespaces(parent_id)',
            'CREATE INDEX IF NOT EXISTS idx_namespaces_depth    ON namespaces(depth)',
        ];
    }
}

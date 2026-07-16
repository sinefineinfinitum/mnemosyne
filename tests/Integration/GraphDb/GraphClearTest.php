<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Integration\GraphDb;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

final class GraphClearTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/mnemosyne_graph_clear_test_' . uniqid() . '.db';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
        $wal = $this->dbPath . '-wal';
        $shm = $this->dbPath . '-shm';
        if (file_exists($wal)) {
            @unlink($wal);
        }
        if (file_exists($shm)) {
            @unlink($shm);
        }
    }

    private function createPopulatedDatabase(): PDO
    {
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($pdo);

        $pdo->exec("INSERT INTO namespaces (fqn, label, depth) VALUES ('App', 'App', 0)");
        $pdo->exec("INSERT INTO files (path, relative_path) VALUES ('src/Foo.php', 'Foo.php')");
        $pdo->exec("INSERT INTO entities (fqn, short_name, type, namespace_id, file_id) VALUES ('App\\\\Foo', 'Foo', 'class', 1, 1)");
        $pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Bar', 'Bar', 'class')");
        $pdo->exec("INSERT INTO methods (entity_id, name) VALUES (1, 'doSomething')");
        $pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES (1, 2, 'creates')");

        return $pdo;
    }

    public function testClearPopulatedDatabaseResetsAllTables(): void
    {
        $this->createPopulatedDatabase();

        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        Schema::drop($pdo);
        Schema::create($pdo);

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount(9, $tables);

        $counts = [
            'entities' => (int) $pdo->query('SELECT COUNT(*) FROM entities')->fetchColumn(),
            'methods' => (int) $pdo->query('SELECT COUNT(*) FROM methods')->fetchColumn(),
            'properties' => (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn(),
            'relationships' => (int) $pdo->query('SELECT COUNT(*) FROM relationships')->fetchColumn(),
            'namespaces' => (int) $pdo->query('SELECT COUNT(*) FROM namespaces')->fetchColumn(),
            'files' => (int) $pdo->query('SELECT COUNT(*) FROM files')->fetchColumn(),
            'parameters' => (int) $pdo->query('SELECT COUNT(*) FROM parameters')->fetchColumn(),
        ];

        $this->assertSame(0, $counts['entities']);
        $this->assertSame(0, $counts['methods']);
        $this->assertSame(0, $counts['properties']);
        $this->assertSame(0, $counts['relationships']);
        $this->assertSame(0, $counts['namespaces']);
        $this->assertSame(0, $counts['files']);
        $this->assertSame(0, $counts['parameters']);
    }

    public function testClearEmptyDatabaseIsIdempotent(): void
    {
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($pdo);

        Schema::drop($pdo);
        Schema::create($pdo);

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount(9, $tables);
    }

    public function testClearCreatesDatabaseFileIfNotExists(): void
    {
        $this->assertFileDoesNotExist($this->dbPath);

        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($pdo);

        $this->assertFileExists($this->dbPath);

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount(9, $tables);
    }

    public function testClearOnNewDatabaseIsIdempotent(): void
    {
        $this->assertFileDoesNotExist($this->dbPath);

        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($pdo);

        Schema::drop($pdo);
        Schema::create($pdo);

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount(9, $tables);
    }
}

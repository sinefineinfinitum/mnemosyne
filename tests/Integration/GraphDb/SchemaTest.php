<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Integration\GraphDb;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Graph\Experimental\Schema;

final class SchemaTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testCreateCreatesAllTables(): void
    {
        Schema::create($this->pdo);

        $tables = $this->getTableNames();

        $this->assertContains('namespaces', $tables);
        $this->assertContains('files', $tables);
        $this->assertContains('entities', $tables);
        $this->assertContains('members', $tables);
        $this->assertContains('parameters', $tables);
        $this->assertContains('relationships', $tables);
        $this->assertContains('types', $tables);
    }

    public function testDropRemovesAllTables(): void
    {
        Schema::create($this->pdo);
        Schema::drop($this->pdo);

        $tables = $this->getTableNames();

        $this->assertNotContains('namespaces', $tables);
        $this->assertNotContains('files', $tables);
        $this->assertNotContains('entities', $tables);
        $this->assertNotContains('members', $tables);
        $this->assertNotContains('parameters', $tables);
        $this->assertNotContains('relationships', $tables);
        $this->assertNotContains('types', $tables);
    }

    public function testCreateIsIdempotent(): void
    {
        Schema::create($this->pdo);
        Schema::create($this->pdo);

        $tables = $this->getTableNames();
        $this->assertCount(7, $tables);
    }

    public function testRelationshipTypeConstraint(): void
    {
        Schema::create($this->pdo);

        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");

        $validTypes = [
            'extends', 'implements', 'uses_trait',
            'creates', 'creates_strong',
            'call_static_weak', 'call_static_strong',
            'call_dynamic_weak', 'call_dynamic_strong',
            'call_global_weak', 'call_global_strong',
            'dependency',
        ];

        foreach ($validTypes as $type) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO relationships (source_id, target_id, type) VALUES (1, 2, :type)'
            );
            $stmt->execute(['type' => $type]);
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM relationships')->fetchColumn();
        $this->assertSame(count($validTypes), $count);
    }

    public function testInvalidRelationshipTypeIsRejected(): void
    {
        Schema::create($this->pdo);

        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");

        $this->expectException(\PDOException::class);
        $this->pdo->exec(
            "INSERT INTO relationships (source_id, target_id, type) VALUES (1, 1, 'invalid_type')"
        );
    }

    public function testEntityTypeConstraint(): void
    {
        Schema::create($this->pdo);

        $validTypes = ['class', 'interface', 'trait', 'enum'];
        foreach ($validTypes as $i => $type) {
            $this->pdo->exec(
                "INSERT INTO entities (fqn, short_name, type) VALUES ('$type', '$type', '$type')"
            );
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM entities')->fetchColumn();
        $this->assertSame(4, $count);
    }

    public function testInvalidEntityTypeIsRejected(): void
    {
        Schema::create($this->pdo);

        $this->expectException(\PDOException::class);
        $this->pdo->exec(
            "INSERT INTO entities (fqn, short_name, type) VALUES ('X', 'X', 'struct')"
        );
    }

    public function testMemberTypeConstraint(): void
    {
        Schema::create($this->pdo);
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");

        $validTypes = ['method', 'property', 'constant', 'case'];
        foreach ($validTypes as $i => $type) {
            $this->pdo->exec(
                "INSERT INTO members (entity_id, name, member_type) VALUES (1, 'm$i', '$type')"
            );
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
        $this->assertSame(4, $count);
    }

    public function testCascadeDeleteEntityRemovesMembersAndRelationships(): void
    {
        Schema::create($this->pdo);

        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES (1, 'foo', 'method')");
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES (1, 2, 'extends')");

        $this->pdo->exec("DELETE FROM entities WHERE id = 1");

        $members = (int) $this->pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
        $rels = (int) $this->pdo->query('SELECT COUNT(*) FROM relationships')->fetchColumn();

        $this->assertSame(0, $members);
        $this->assertSame(0, $rels);
    }

    /**
     * @return list<string>
     */
    private function getTableNames(): array
    {
        $stmt = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

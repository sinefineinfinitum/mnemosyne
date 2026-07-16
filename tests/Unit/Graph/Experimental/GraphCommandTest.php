<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Graph\Experimental;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Graph\Experimental\GraphCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

class GraphCommandTest extends TestCase
{
    private PDO $pdo;
    private GraphCommand $cmd;
    private GraphQuery $query;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->cmd = new GraphCommand($this->pdo);
        $this->query = new GraphQuery($this->pdo);
    }

    public function testInsertNamespaceReturnsId(): void
    {
        $id = $this->cmd->insertNamespace('App', 'App', null, 0);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertFileReturnsId(): void
    {
        $id = $this->cmd->insertFile('/src/Foo.php', 'Foo.php', 'abc123');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertFileReturnsExistingIdWhenDuplicate(): void
    {
        $id1 = $this->cmd->insertFile('/src/Foo.php', 'Foo.php', null);
        $id2 = $this->cmd->insertFile('/src/Foo.php', 'Foo.php', null);
        $this->assertSame($id1, $id2);
    }

    public function testInsertEntityReturnsId(): void
    {
        $id = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertEntityReturnsExistingIdWhenDuplicate(): void
    {
        $id1 = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $id2 = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $this->assertSame($id1, $id2);
    }

    public function testInsertEntityWithModifiers(): void
    {
        $id = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, ['abstract', 'final', 'readonly']);
        $entity = $this->query->findEntityById($id);
        $this->assertSame(1, (int) $entity['is_abstract']);
        $this->assertSame(1, (int) $entity['is_final']);
        $this->assertSame(1, (int) $entity['is_readonly']);
    }

    public function testInsertEntityWithScalarType(): void
    {
        $id = $this->cmd->insertEntity('App\\Color', 'Color', 'enum', null, null, null, [], 'string');
        $entity = $this->query->findEntityById($id);
        $this->assertSame('string', $entity['scalar_type']);
    }

    public function testInsertMethodReturnsId(): void
    {
        $entityId = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $id = $this->cmd->insertMethod($entityId, 'bar', 'public', false, false, false, null, 'void');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertMethodReturnsExistingIdWhenDuplicate(): void
    {
        $entityId = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $id1 = $this->cmd->insertMethod($entityId, 'bar', 'public', false, false, false, null, null);
        $id2 = $this->cmd->insertMethod($entityId, 'bar', 'public', false, false, false, null, null);
        $this->assertSame($id1, $id2);
    }

    public function testInsertMethodWithAllFlags(): void
    {
        $entityId = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $id = $this->cmd->insertMethod($entityId, 'bar', 'protected', true, true, true, null, 'string');
        $this->assertIsInt($id);
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertCount(1, $members);
        $m = $members[0];
        $this->assertSame('bar', $m['name']);
        $this->assertSame('method', $m['member_type']);
        $this->assertSame('protected', $m['visibility']);
        $this->assertSame(1, (int) $m['is_static']);
        $this->assertSame(1, (int) $m['is_abstract']);
        $this->assertSame(1, (int) $m['is_final']);
        $this->assertSame('string', $m['return_type']);
    }

    public function testInsertParameterReturnsId(): void
    {
        $entityId = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $memberId = $this->cmd->insertMethod($entityId, 'bar', 'public', false, false, false, null, null);
        $id = $this->cmd->insertParameter($memberId, 'x', null, 'int', '0', false, false, 0);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertParameterWithVariadicAndReference(): void
    {
        $entityId = $this->cmd->insertEntity('App\\Foo', 'Foo', 'class', null, null, null, []);
        $memberId = $this->cmd->insertMethod($entityId, 'bar', 'public', false, false, false, null, null);
        $id = $this->cmd->insertParameter($memberId, 'args', null, 'string', null, true, true, 0);
        $params = $this->query->findParametersByMember($memberId);
        $this->assertCount(1, $params);
        $this->assertSame(1, (int) $params[0]['is_variadic']);
        $this->assertSame(1, (int) $params[0]['is_passed_by_reference']);
    }

    public function testInsertRelationshipReturnsId(): void
    {
        $sourceId = $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $targetId = $this->cmd->insertEntity('B', 'B', 'class', null, null, null, []);
        $id = $this->cmd->insertRelationship($sourceId, $targetId, null, 'extends', null);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertRelationshipReturnsExistingIdWhenDuplicate(): void
    {
        $sourceId = $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $targetId = $this->cmd->insertEntity('B', 'B', 'class', null, null, null, []);
        $id1 = $this->cmd->insertRelationship($sourceId, $targetId, null, 'extends', null);
        $id2 = $this->cmd->insertRelationship($sourceId, $targetId, null, 'extends', null);
        $this->assertSame($id1, $id2);
    }

    public function testInsertRelationshipWithTargetFqn(): void
    {
        $sourceId = $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $id = $this->cmd->insertRelationship($sourceId, null, 'Unknown', 'creates', null);
        $this->assertIsInt($id);
    }

    public function testInsertRelationshipWithSourceMemberId(): void
    {
        $sourceId = $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $targetId = $this->cmd->insertEntity('B', 'B', 'class', null, null, null, []);
        $memberId = $this->cmd->insertMethod($sourceId, 'foo', 'public', false, false, false, null, null);
        $id = $this->cmd->insertRelationship($sourceId, $targetId, null, 'call_static_strong', $memberId);
        $this->assertIsInt($id);
    }

    public function testResolvePendingTargets(): void
    {
        $sourceId = $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $targetId = $this->cmd->insertEntity('B', 'B', 'class', null, null, null, []);
        $this->cmd->insertRelationship($sourceId, null, 'B', 'creates', null);

        $this->cmd->resolvePendingTargets(['B' => $targetId]);

        $rels = $this->query->findRelationshipsBySource($sourceId);
        $this->assertCount(1, $rels);
        $this->assertSame($targetId, (int) $rels[0]['target_id']);
    }

    public function testResolvePendingTargetsSkipsAlreadyResolved(): void
    {
        $sourceId = $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $targetId = $this->cmd->insertEntity('B', 'B', 'class', null, null, null, []);
        $this->cmd->insertRelationship($sourceId, $targetId, null, 'extends', null);

        $this->cmd->resolvePendingTargets(['B' => $targetId]);

        $rels = $this->query->findRelationshipsBySource($sourceId);
        $this->assertCount(1, $rels);
        $this->assertSame($targetId, (int) $rels[0]['target_id']);
    }

    public function testClearRemovesAllData(): void
    {
        $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $this->cmd->insertNamespace('App', 'App', null, 0);
        $this->cmd->insertFile('/src/A.php', 'A.php', null);

        $this->cmd->clear();

        $this->assertSame(0, $this->query->countEntities());
        $this->assertSame(0, $this->query->countNamespaces());
    }

    public function testBeginTransactionAndCommit(): void
    {
        $this->cmd->beginTransaction();
        $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $this->cmd->commit();
        $this->assertSame(1, $this->query->countEntities());
    }

    public function testRollback(): void
    {
        $this->cmd->insertEntity('A', 'A', 'class', null, null, null, []);
        $this->assertSame(1, $this->query->countEntities());

        $this->cmd->beginTransaction();
        $this->cmd->insertEntity('B', 'B', 'class', null, null, null, []);
        $this->assertSame(2, $this->query->countEntities());
        $this->cmd->rollback();

        $this->assertSame(1, $this->query->countEntities());
    }

    public function testRollbackWhenNotInTransaction(): void
    {
        $this->cmd->rollback();
        $this->assertSame(0, $this->query->countEntities());
    }
}

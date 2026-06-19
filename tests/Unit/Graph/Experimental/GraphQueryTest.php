<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Graph\Experimental;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class GraphQueryTest extends TestCase
{
    private PDO $pdo;
    private GraphQuery $query;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->query = new GraphQuery($this->pdo);
    }

    public function testFindNamespaceIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findNamespaceId('App\\Foo'));
    }

    public function testFindNamespaceIdReturnsIdWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO namespaces (fqn, label, depth) VALUES ('App\\Foo', 'Foo', 1)");
        $id = $this->query->findNamespaceId('App\\Foo');
        $this->assertNotNull($id);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $id);
    }

    public function testFindEntityIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findEntityId('App\\Foo'));
    }

    public function testFindEntityIdReturnsIdWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $id = $this->query->findEntityId('App\\Foo');
        $this->assertNotNull($id);
        $this->assertIsInt($id);
        $this->assertSame(1, $id);
    }

    public function testFindEntityReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findEntity('App\\Foo'));
    }

    public function testFindEntityReturnsRowWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entity = $this->query->findEntity('App\\Foo');
        $this->assertNotNull($entity);
        $this->assertIsArray($entity);
        $this->assertSame('App\\Foo', $entity['fqn']);
        $this->assertSame('Foo', $entity['short_name']);
        $this->assertSame('class', $entity['type']);
    }

    public function testFindAllEntitiesReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->query->findAllEntities());
    }

    public function testFindAllEntitiesReturnsAllOrdered(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B\\Bar', 'Bar', 'class')");
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A\\Foo', 'Foo', 'class')");
        $entities = $this->query->findAllEntities();
        $this->assertCount(2, $entities);
        $this->assertSame('A\\Foo', $entities[0]['fqn']);
        $this->assertSame('B\\Bar', $entities[1]['fqn']);
    }

    public function testFindEntityByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findEntityById(999));
    }

    public function testFindEntityByIdReturnsRowWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $id = (int) $this->pdo->lastInsertId();
        $entity = $this->query->findEntityById($id);
        $this->assertNotNull($entity);
        $this->assertSame('App\\Foo', $entity['fqn']);
        $this->assertSame($id, (int) $entity['id']);
    }

    public function testFindEntitiesByIdsReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame([], $this->query->findEntitiesByIds([]));
    }

    public function testFindEntitiesByIdsReturnsMatchingEntities(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $id1 = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $id2 = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('C', 'C', 'class')");

        $entities = $this->query->findEntitiesByIds([$id1, $id2]);
        $this->assertCount(2, $entities);
    }

    public function testFindMembersByEntityReturnsEmptyWhenNone(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->assertSame([], $this->query->findMembersByEntity($entityId));
    }

    public function testFindMembersByEntityReturnsMembersOrdered(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'bar', 'property')");
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'foo', 'method')");
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertCount(2, $members);
        $this->assertSame('foo', $members[0]['name']);
        $this->assertSame('bar', $members[1]['name']);
    }

    public function testFindMemberIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findMemberId(1, 'foo', 'method'));
    }

    public function testFindMemberIdReturnsIdWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'foo', 'method')");
        $memberId = $this->query->findMemberId($entityId, 'foo', 'method');
        $this->assertNotNull($memberId);
        $this->assertIsInt($memberId);
        $this->assertSame(1, $memberId);
    }

    public function testFindParametersByMembersReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame([], $this->query->findParametersByMembers([]));
    }

    public function testFindParametersByMembersReturnsParameters(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'foo', 'method')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, position) VALUES ($memberId, 'x', 0)");
        $this->pdo->exec("INSERT INTO parameters (member_id, name, position) VALUES ($memberId, 'y', 1)");

        $params = $this->query->findParametersByMembers([$memberId]);
        $this->assertCount(2, $params);
        $this->assertSame('x', $params[0]['name']);
        $this->assertSame('y', $params[1]['name']);
    }

    public function testFindParametersByMemberReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findParametersByMember(999));
    }

    public function testFindParametersByMemberReturnsParametersOrdered(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'foo', 'method')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, position) VALUES ($memberId, 'b', 1)");
        $this->pdo->exec("INSERT INTO parameters (member_id, name, position) VALUES ($memberId, 'a', 0)");

        $params = $this->query->findParametersByMember($memberId);
        $this->assertCount(2, $params);
        $this->assertSame('a', $params[0]['name']);
        $this->assertSame('b', $params[1]['name']);
    }

    public function testFindRelationshipsBySourceReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findRelationshipsBySource(999));
    }

    public function testFindRelationshipsBySourceReturnsRelationships(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES ($sourceId, $targetId, 'extends')");

        $rels = $this->query->findRelationshipsBySource($sourceId);
        $this->assertCount(1, $rels);
        $this->assertSame('extends', $rels[0]['type']);
        $this->assertSame('B', $rels[0]['target_fqn_resolved']);
    }

    public function testFindRelationshipsByTargetReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findRelationshipsByTarget(999));
    }

    public function testFindRelationshipsByTargetReturnsRelationships(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES ($sourceId, $targetId, 'extends')");

        $rels = $this->query->findRelationshipsByTarget($targetId);
        $this->assertCount(1, $rels);
        $this->assertSame('A', $rels[0]['source_fqn']);
    }

    public function testFindParameterSignaturesReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findParameterSignatures(999));
    }

    public function testFindParameterSignaturesReturnsSignatures(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'foo', 'method')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, default_value, position) VALUES ($memberId, 'x', 'int', '0', 0)");

        $sigs = $this->query->findParameterSignatures($memberId);
        $this->assertCount(1, $sigs);
        $this->assertSame('x', $sigs[0]['name']);
        $this->assertSame('int', $sigs[0]['declared_type']);
        $this->assertSame('0', $sigs[0]['default_value']);
    }

    public function testFindRelationshipsByTypeReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findRelationshipsByType('extends'));
    }

    public function testFindRelationshipsByTypeReturnsMatching(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES ($sourceId, $targetId, 'extends')");
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES ($sourceId, $targetId, 'implements')");

        $rels = $this->query->findRelationshipsByType('extends');
        $this->assertCount(1, $rels);
        $this->assertSame('extends', $rels[0]['type']);
    }

    public function testFindTypesByOwnerWithOwnerId(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO types (owner_type, owner_id, name, entity_id, position) VALUES ('param', 1, 'int', NULL, 0)");

        $types = $this->query->findTypesByOwner('param', 1);
        $this->assertCount(1, $types);
        $this->assertSame('int', $types[0]['name']);
    }

    public function testFindTypesByOwnerWithoutOwnerId(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Foo', 'Foo', 'class')");
        $this->pdo->exec("INSERT INTO types (owner_type, owner_id, name, position) VALUES ('param', 1, 'int', 0)");
        $this->pdo->exec("INSERT INTO types (owner_type, owner_id, name, position) VALUES ('param', 2, 'string', 1)");

        $types = $this->query->findTypesByOwner('param');
        $this->assertCount(2, $types);
    }

    public function testFindRelationshipIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findRelationshipId(1, 2, null, 'extends', null));
    }

    public function testFindRelationshipIdReturnsIdWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES ($sourceId, $targetId, 'extends')");

        $id = $this->query->findRelationshipId($sourceId, $targetId, null, 'extends', null);
        $this->assertNotNull($id);
        $this->assertIsInt($id);
        $this->assertSame(1, $id);
    }

    public function testFindRelationshipIdWithTargetFqn(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_fqn, type) VALUES ($sourceId, 'Unknown', 'dependency')");

        $id = $this->query->findRelationshipId($sourceId, null, 'Unknown', 'dependency', null);
        $this->assertNotNull($id);
    }

    public function testFindRelationshipIdWithSourceMemberId(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($sourceId, 'foo', 'method')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type, source_member_id) VALUES ($sourceId, $targetId, 'call_static_strong', $memberId)");

        $id = $this->query->findRelationshipId($sourceId, $targetId, null, 'call_static_strong', $memberId);
        $this->assertNotNull($id);
    }

    public function testFindRelationshipIdWithTargetMemberName(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type, target_member_name) VALUES ($sourceId, $targetId, 'call_static_strong', 'bar')");

        $id = $this->query->findRelationshipId($sourceId, $targetId, null, 'call_static_strong', null, 'bar');
        $this->assertNotNull($id);
    }

    public function testFindAllRelationshipsReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findAllRelationships());
    }

    public function testFindAllRelationshipsReturnsAll(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES ($sourceId, $targetId, 'extends')");

        $rels = $this->query->findAllRelationships();
        $this->assertCount(1, $rels);
    }

    public function testFindAllNamespacesReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findAllNamespaces());
    }

    public function testFindAllNamespacesReturnsAll(): void
    {
        $this->pdo->exec("INSERT INTO namespaces (fqn, label, depth) VALUES ('App', 'App', 0)");
        $this->pdo->exec("INSERT INTO namespaces (fqn, label, depth) VALUES ('App\\\\Foo', 'Foo', 1)");

        $ns = $this->query->findAllNamespaces();
        $this->assertCount(2, $ns);
    }

    public function testFindEntitiesByShortNameReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->query->findEntitiesByShortName('Foo'));
    }

    public function testFindEntitiesByShortNameReturnsMatching(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\\\Foo', 'Foo', 'class')");
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('Bar\\\\Foo', 'Foo', 'class')");
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('Baz', 'Baz', 'class')");

        $entities = $this->query->findEntitiesByShortName('Foo');
        $this->assertCount(2, $entities);
    }

    public function testFindFileByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findFileById(999));
    }

    public function testFindFileByIdReturnsRowWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO files (path, relative_path, hash) VALUES ('/src/Foo.php', 'Foo.php', 'abc123')");
        $id = (int) $this->pdo->lastInsertId();
        $file = $this->query->findFileById($id);
        $this->assertNotNull($file);
        $this->assertSame('/src/Foo.php', $file['path']);
        $this->assertSame('Foo.php', $file['relative_path']);
        $this->assertSame('abc123', $file['hash']);
        $this->assertSame($id, (int) $file['id']);
    }

    public function testFindFileIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->query->findFileId('/nonexistent.php'));
    }

    public function testFindFileIdReturnsIdWhenFound(): void
    {
        $this->pdo->exec("INSERT INTO files (path) VALUES ('/src/Foo.php')");
        $id = $this->query->findFileId('/src/Foo.php');
        $this->assertNotNull($id);
        $this->assertIsInt($id);
        $this->assertSame(1, $id);
    }

    public function testCountEntitiesReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->query->countEntities());
    }

    public function testCountEntitiesReturnsCorrectCount(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $this->assertSame(2, $this->query->countEntities());
        $this->assertIsInt($this->query->countEntities());
    }

    public function testCountRelationshipsReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->query->countRelationships());
    }

    public function testCountRelationshipsReturnsCorrectCount(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $sourceId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('B', 'B', 'class')");
        $targetId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO relationships (source_id, target_id, type) VALUES ($sourceId, $targetId, 'extends')");
        $this->assertSame(1, $this->query->countRelationships());
    }

    public function testCountMembersReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->query->countMembers());
    }

    public function testCountMembersReturnsCorrectCount(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('A', 'A', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'foo', 'method')");
        $this->assertSame(1, $this->query->countMembers());
    }

    public function testCountTypesReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->query->countTypes());
    }

    public function testCountTypesReturnsCorrectCount(): void
    {
        $this->pdo->exec("INSERT INTO types (owner_type, owner_id, name, position) VALUES ('param', 1, 'int', 0)");
        $this->assertSame(1, $this->query->countTypes());
    }

    public function testCountNamespacesReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->query->countNamespaces());
    }

    public function testCountNamespacesReturnsCorrectCount(): void
    {
        $this->pdo->exec("INSERT INTO namespaces (fqn, label, depth) VALUES ('App', 'App', 0)");
        $this->assertSame(1, $this->query->countNamespaces());
    }
}

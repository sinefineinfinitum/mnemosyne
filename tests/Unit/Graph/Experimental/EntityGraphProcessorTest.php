<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Graph\Experimental;

use PDO;
use PHPUnit\Framework\TestCase;
use Ponymator\Parser\Ast\CallNode;
use Ponymator\Parser\Ast\EntityNode;
use Ponymator\Parser\Ast\MemberNode;
use Ponymator\Parser\Ast\ParameterNode;
use SineFine\Ponymator\Graph\Experimental\EntityGraphProcessor;
use SineFine\Ponymator\Graph\Experimental\GraphCommand;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\NamespaceResolver;
use SineFine\Ponymator\Graph\Experimental\Schema;

class EntityGraphProcessorTest extends TestCase
{
    private PDO $pdo;
    private GraphCommand $command;
    private GraphQuery $query;
    private NamespaceResolver $nsResolver;
    private EntityGraphProcessor $processor;

    private int $fileId;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->command = new GraphCommand($this->pdo);
        $this->query = new GraphQuery($this->pdo);
        $this->nsResolver = new NamespaceResolver($this->command, $this->query);
        $this->processor = new EntityGraphProcessor($this->command, $this->nsResolver);
        $this->fileId = $this->command->insertFile('/test.php', 'test.php', null);
    }

    private function makeEntity(string $type, string $name): EntityNode
    {
        return new EntityNode($type, $name);
    }

    private function makeMember(string $name, string $type, EntityNode $parent): MemberNode
    {
        return new MemberNode($name, $type, $parent);
    }

    private function makeParameter(string $name): ParameterNode
    {
        return new ParameterNode($name);
    }

    public function testProcessEntityCreatesEntityInDatabase(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $this->processor->processEntity($entity, $this->fileId);

        $ids = $this->processor->getEntityIds();
        $this->assertArrayHasKey('App\\Foo', $ids);
        $dbEntity = $this->query->findEntity('App\\Foo');
        $this->assertNotNull($dbEntity);
        $this->assertSame('App\\Foo', $dbEntity['fqn']);
        $this->assertSame('Foo', $dbEntity['short_name']);
        $this->assertSame('class', $dbEntity['type']);
    }

    public function testProcessEntityCreatesNamespace(): void
    {
        $entity = $this->makeEntity('class', 'App\\Sub\\Foo');
        $this->processor->processEntity($entity, $this->fileId);

        $ns = $this->query->findNamespaceId('App\\Sub');
        $this->assertNotNull($ns);
        $parentNs = $this->query->findNamespaceId('App');
        $this->assertNotNull($parentNs);
    }

    public function testProcessEntityWithoutNamespace(): void
    {
        $entity = $this->makeEntity('class', 'Foo');
        $this->processor->processEntity($entity, $this->fileId);

        $dbEntity = $this->query->findEntity('Foo');
        $this->assertNotNull($dbEntity);
        $this->assertSame('Foo', $dbEntity['short_name']);
    }

    public function testProcessEntityMapsTypes(): void
    {
        $entity = $this->makeEntity('interface', 'App\\Foo');
        $this->processor->processEntity($entity, $this->fileId);
        $dbEntity = $this->query->findEntity('App\\Foo');
        $this->assertSame('interface', $dbEntity['type']);

        $entity2 = $this->makeEntity('trait', 'App\\Bar');
        $this->processor->processEntity($entity2, 1);
        $dbEntity2 = $this->query->findEntity('App\\Bar');
        $this->assertSame('trait', $dbEntity2['type']);

        $entity3 = $this->makeEntity('enum', 'App\\Baz');
        $this->processor->processEntity($entity3, 1);
        $dbEntity3 = $this->query->findEntity('App\\Baz');
        $this->assertSame('enum', $dbEntity3['type']);
    }

    public function testProcessEntityWithModifiers(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $entity->attributes = ['abstract', 'final', 'readonly'];
        $this->processor->processEntity($entity, $this->fileId);

        $dbEntity = $this->query->findEntity('App\\Foo');
        $this->assertSame(1, (int) $dbEntity['is_abstract']);
        $this->assertSame(1, (int) $dbEntity['is_final']);
        $this->assertSame(1, (int) $dbEntity['is_readonly']);
    }

    public function testProcessEntityWithExtends(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $entity->extends = ['App\\Base'];
        $this->processor->processEntity($entity, $this->fileId);

        $rels = $this->query->findRelationshipsBySource($this->processor->getEntityIds()['App\\Foo']);
        $this->assertCount(1, $rels);
        $this->assertSame('extends', $rels[0]['type']);
        $this->assertSame('App\\Base', $rels[0]['target_fqn']);
    }

    public function testProcessEntityWithImplements(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $entity->implements = ['App\\IFoo', 'App\\IBar'];
        $this->processor->processEntity($entity, $this->fileId);

        $rels = $this->query->findRelationshipsBySource($this->processor->getEntityIds()['App\\Foo']);
        $this->assertCount(2, $rels);
        $types = array_column($rels, 'type');
        $this->assertContains('implements', $types);
    }

    public function testProcessEntityWithTraits(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $entity->traits = ['App\\Loggable'];
        $this->processor->processEntity($entity, $this->fileId);

        $rels = $this->query->findRelationshipsBySource($this->processor->getEntityIds()['App\\Foo']);
        $this->assertCount(1, $rels);
        $this->assertSame('uses_trait', $rels[0]['type']);
        $this->assertSame('App\\Loggable', $rels[0]['target_fqn']);
    }

    public function testProcessEntityWithMembers(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $member->visibility = 'public';
        $member->returnType = 'void';
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertCount(1, $members);
        $this->assertSame('bar', $members[0]['name']);
        $this->assertSame('method', $members[0]['member_type']);
        $this->assertSame('public', $members[0]['visibility']);
        $this->assertSame('void', $members[0]['return_type']);
    }

    public function testProcessEntityWithMemberModifiers(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $member->attributes = ['static', 'abstract', 'final', 'readonly'];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertSame(1, (int) $members[0]['is_static']);
        $this->assertSame(1, (int) $members[0]['is_abstract']);
        $this->assertSame(1, (int) $members[0]['is_final']);
        $this->assertSame(1, (int) $members[0]['is_readonly']);
    }

    public function testProcessEntityWithPropertyType(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('name', 'property', $entity);
        $member->dataType = 'string';
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $types = $this->query->findTypesByOwner('property', $memberId);
        $this->assertCount(1, $types);
        $this->assertSame('string', $types[0]['name']);
    }

    public function testProcessEntityWithNullableType(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('name', 'property', $entity);
        $member->dataType = '?string';
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertSame('string', $members[0]['declared_type']);
    }

    public function testProcessEntityWithReturnType(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $member->returnType = 'App\\Entity\\User';
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $types = $this->query->findTypesByOwner('return', $memberId);
        $this->assertCount(1, $types);
        $this->assertSame('App\\Entity\\User', $types[0]['name']);
    }

    public function testProcessEntityWithParameters(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $param1 = $this->makeParameter('x');
        $param1->type = 'int';
        $param2 = $this->makeParameter('y');
        $param2->type = 'string';
        $param2->value = "'hello'";
        $member->parameters = [$param1, $param2];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $params = $this->query->findParametersByMember($memberId);
        $this->assertCount(2, $params);
        $this->assertSame('x', $params[0]['name']);
        $this->assertSame('int', $params[0]['declared_type']);
        $this->assertSame('y', $params[1]['name']);
        $this->assertSame("'hello'", $params[1]['default_value']);
    }

    public function testProcessEntityWithVariadicAndReferenceParameters(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $param1 = $this->makeParameter('ref');
        $param1->byRef = true;
        $param2 = $this->makeParameter('args');
        $param2->isVariadic = true;
        $member->parameters = [$param1, $param2];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $params = $this->query->findParametersByMember($memberId);
        $this->assertSame(1, (int) $params[0]['is_passed_by_reference']);
        $this->assertSame(0, (int) $params[0]['is_variadic']);
        $this->assertSame(0, (int) $params[1]['is_passed_by_reference']);
        $this->assertSame(1, (int) $params[1]['is_variadic']);
    }

    public function testProcessEntityWithStaticCall(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $call = new CallNode(CallNode::TYPE_STATIC, 'App\\Bar', 'doStuff', 'strong');
        $member->calls = [$call];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertCount(1, $rels);
        $this->assertSame('call_static_strong', $rels[0]['type']);
        $this->assertSame('App\\Bar', $rels[0]['target_fqn']);
    }

    public function testProcessEntityWithDynamicCall(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $call = new CallNode(CallNode::TYPE_DYNAMIC, 'App\\Bar', 'doStuff', 'weak');
        $member->calls = [$call];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertSame('call_dynamic_weak', $rels[0]['type']);
    }

    public function testProcessEntityWithGlobalCall(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $call = new CallNode(CallNode::TYPE_GLOBAL, '', 'strlen', 'strong');
        $member->calls = [$call];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertSame('call_global_strong', $rels[0]['type']);
        $this->assertNull($rels[0]['target_fqn']);
    }

    public function testProcessEntityWithCreates(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $member->creates = ['App\\Bar'];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertCount(1, $rels);
        $this->assertSame('creates', $rels[0]['type']);
        $this->assertSame('App\\Bar', $rels[0]['target_fqn']);
    }

    public function testProcessEntityMapsMemberTypes(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $m1 = $this->makeMember('prop', 'property', $entity);
        $m2 = $this->makeMember('method', 'method', $entity);
        $m3 = $this->makeMember('CONST', 'constant', $entity);
        $m4 = $this->makeMember('Case1', 'enum_case', $entity);
        $entity->members = [$m1, $m2, $m3, $m4];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $types = array_column($members, 'member_type');
        $this->assertContains('property', $types);
        $this->assertContains('method', $types);
        $this->assertContains('constant', $types);
        $this->assertContains('case', $types);
    }

    public function testProcessEntityMapsGlobalVariableToProperty(): void
    {
        $entity = $this->makeEntity('file', 'test.php');
        $m = $this->makeMember('config', 'global_variable', $entity);
        $entity->members = [$m];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['test.php'];
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertSame('property', $members[0]['member_type']);
    }

    public function testProcessEntityMapsFunctionToMethod(): void
    {
        $entity = $this->makeEntity('file', 'test.php');
        $m = $this->makeMember('helper', 'function', $entity);
        $entity->members = [$m];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['test.php'];
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertSame('method', $members[0]['member_type']);
    }

    public function testProcessEntityWithParameterType(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $param = $this->makeParameter('x');
        $param->type = 'App\\Entity\\User';
        $member->parameters = [$param];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $params = $this->query->findParametersByMember($memberId);
        $paramId = (int) $params[0]['id'];
        $types = $this->query->findTypesByOwner('param', $paramId);
        $this->assertCount(1, $types);
        $this->assertSame('App\\Entity\\User', $types[0]['name']);
    }

    public function testProcessEntityWithNullableParameterType(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $param = $this->makeParameter('x');
        $param->type = '?int';
        $member->parameters = [$param];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $params = $this->query->findParametersByMember($memberId);
        $this->assertSame('int', $params[0]['declared_type']);
    }

    public function testGetEntityIdsReturnsEmptyInitially(): void
    {
        $this->assertSame([], $this->processor->getEntityIds());
    }

    public function testGetEntityIdsAfterProcessing(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $this->processor->processEntity($entity, $this->fileId);

        $ids = $this->processor->getEntityIds();
        $this->assertArrayHasKey('App\\Foo', $ids);
        $this->assertIsInt($ids['App\\Foo']);
    }

    public function testProcessEntityWithCallEmptyTargetFqcn(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $call = new CallNode(CallNode::TYPE_GLOBAL, '', 'strlen', 'weak');
        $member->calls = [$call];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertSame('call_global_weak', $rels[0]['type']);
        $this->assertNull($rels[0]['target_fqn']);
    }

    public function testProcessEntityWithCallStaticWeak(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $call = new CallNode(CallNode::TYPE_STATIC, 'App\\Bar', 'baz', 'weak');
        $member->calls = [$call];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertSame('call_static_weak', $rels[0]['type']);
    }

    public function testProcessEntityWithCallDynamicStrong(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $call = new CallNode(CallNode::TYPE_DYNAMIC, 'App\\Bar', 'baz', 'strong');
        $member->calls = [$call];
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertSame('call_dynamic_strong', $rels[0]['type']);
    }

    public function testProcessEntityWithMemberDefaultValue(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('VERSION', 'constant', $entity);
        $member->value = "'1.0'";
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $this->assertSame("'1.0'", $members[0]['default_value']);
    }

    public function testProcessEntityWithPropertyTypeNull(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('data', 'property', $entity);
        $member->dataType = null;
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $types = $this->query->findTypesByOwner('property', $memberId);
        $this->assertCount(0, $types);
    }

    public function testProcessEntityWithReturnTypeNull(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $member = $this->makeMember('bar', 'method', $entity);
        $member->returnType = null;
        $entity->members = [$member];

        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $members = $this->query->findMembersByEntity($entityId);
        $memberId = (int) $members[0]['id'];
        $types = $this->query->findTypesByOwner('return', $memberId);
        $this->assertCount(0, $types);
    }

    public function testProcessEntityWithMultipleRelationships(): void
    {
        $entity = $this->makeEntity('class', 'App\\Foo');
        $entity->extends = ['App\\Base'];
        $entity->implements = ['App\\IFoo'];
        $entity->traits = ['App\\Loggable'];
        $this->processor->processEntity($entity, $this->fileId);

        $entityId = $this->processor->getEntityIds()['App\\Foo'];
        $rels = $this->query->findRelationshipsBySource($entityId);
        $this->assertCount(3, $rels);
        $types = array_column($rels, 'type');
        $this->assertContains('extends', $types);
        $this->assertContains('implements', $types);
        $this->assertContains('uses_trait', $types);
    }
}

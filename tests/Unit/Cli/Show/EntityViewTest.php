<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Cli\Show;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Show\EntityView;
use SineFine\Ponymator\Graph\Experimental\GraphCommand;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class EntityViewTest extends TestCase
{
    private PDO $pdo;
    private GraphQuery $query;
    private GraphCommand $command;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->command = new GraphCommand($this->pdo);
        $this->query = new GraphQuery($this->pdo);
    }

    private function insertEntity(string $fqn, string $type = 'class'): int
    {
        $shortName = substr($fqn, strrpos($fqn, '\\') + 1);
        return $this->command->insertEntity($fqn, $shortName, $type, null, null, null, []);
    }

    private function insertMember(int $entityId, string $name): int
    {
        return $this->command->insertMember($entityId, $name, 'method', 'public', false, false, false, false, null, null, null);
    }

    public function testLoadBasicEntity(): void
    {
        $this->insertEntity('App\\Foo');

        $view = EntityView::load('App\\Foo', $this->query);
        $this->assertSame('App\\Foo', $view->entity['fqn']);
        $this->assertSame('class', $view->entity['type']);
        $this->assertSame([], $view->modifiers);
        $this->assertNull($view->filePath);
    }

    public function testLoadWithModifiers(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type, is_abstract, is_final) VALUES ('App\\Foo', 'Foo', 'class', 1, 1)");

        $view = EntityView::load('App\\Foo', $this->query);
        $this->assertContains('abstract', $view->modifiers);
        $this->assertContains('final', $view->modifiers);
    }

    public function testLoadWithReadonly(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type, is_readonly) VALUES ('App\\Foo', 'Foo', 'class', 1)");

        $view = EntityView::load('App\\Foo', $this->query);
        $this->assertContains('readonly', $view->modifiers);
    }

    public function testLoadWithFilePath(): void
    {
        $this->pdo->exec("INSERT INTO files (relative_path, path, hash) VALUES ('src/Foo.php', '/abs/src/Foo.php', 'abc')");
        $fileId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type, file_id) VALUES ('App\\Foo', 'Foo', 'class', $fileId)");

        $view = EntityView::load('App\\Foo', $this->query);
        $this->assertSame('src/Foo.php', $view->filePath);
    }

    public function testLoadOutgoingStructuralRelations(): void
    {
        $parentId = $this->insertEntity('App\\Parent');
        $childId = $this->insertEntity('App\\Child');
        $this->command->insertRelationship($childId, $parentId, null, 'extends', null);

        $view = EntityView::load('App\\Child', $this->query);
        $this->assertCount(1, $view->outgoingStructural);
        $this->assertSame('extends', $view->outgoingStructural[0]['type']);
    }

    public function testLoadIncomingStructuralRelations(): void
    {
        $parentId = $this->insertEntity('App\\Parent');
        $childId = $this->insertEntity('App\\Child');
        $this->command->insertRelationship($childId, $parentId, null, 'extends', null);

        $view = EntityView::load('App\\Parent', $this->query);
        $this->assertCount(1, $view->structuralIncoming);
        $this->assertSame('extends', $view->structuralIncoming[0]['type']);
    }

    public function testLoadOutgoingCalls(): void
    {
        $aId = $this->insertEntity('App\\A');
        $bId = $this->insertEntity('App\\B');
        $memberId = $this->insertMember($aId, 'doStuff');
        $this->command->insertRelationship($aId, $bId, null, 'call_static_strong', $memberId);

        $view = EntityView::load('App\\A', $this->query);
        $this->assertCount(1, $view->outgoingCalls);
    }

    public function testLoadExternalReferences(): void
    {
        $aId = $this->insertEntity('App\\A');
        $this->command->insertRelationship($aId, null, 'External\\Lib', 'call_static_strong', null);

        $view = EntityView::load('App\\A', $this->query);
        $this->assertContains('External\\Lib', $view->external);
    }

    public function testLoadMembers(): void
    {
        $entityId = $this->insertEntity('App\\Foo');
        $this->insertMember($entityId, 'bar');

        $view = EntityView::load('App\\Foo', $this->query);
        $this->assertCount(1, $view->members);
        $this->assertSame('bar', $view->members[0]['name']);
    }

    public function testLoadDeduplicatesExternal(): void
    {
        $aId = $this->insertEntity('App\\A');
        $this->command->insertRelationship($aId, null, 'External\\Lib', 'call_static_strong', null);
        $this->command->insertRelationship($aId, null, 'External\\Lib', 'call_static_strong', null);

        $view = EntityView::load('App\\A', $this->query);
        $this->assertCount(1, $view->external);
    }
}

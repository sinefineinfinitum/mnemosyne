<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Cli\Show\Views;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Show\EntityView;
use SineFine\Ponymator\Cli\Show\Views\MembersView;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class MembersViewTest extends TestCase
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

    private function makeView(array $members = [], array $outgoingCalls = []): EntityView
    {
        return new EntityView(
            entity: ['id' => 1, 'fqn' => 'App\\Foo', 'short_name' => 'Foo', 'type' => 'class'],
            modifiers: [],
            filePath: null,
            outgoingStructural: [],
            structuralIncoming: [],
            members: $members,
            outgoingCalls: $outgoingCalls,
            callIncoming: [],
            external: [],
            query: $this->query,
        );
    }

    public function testRenderEmptyMembers(): void
    {
        $view = new MembersView($this->makeView());
        $this->assertSame('', $view->render());
    }

    public function testRenderMethod(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('Methods (1):', $output);
        $this->assertStringContainsString('public function bar()', $output);
    }

    public function testRenderMethodWithReturnType(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility, return_type) VALUES ($entityId, 'bar', 'method', 'public', 'string')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => 'string'],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('public function bar(): string', $output);
    }

    public function testRenderMethodWithParameters(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, default_value, position) VALUES ($memberId, 'x', 'int', null, 0)");
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, default_value, position) VALUES ($memberId, 'y', 'string', \"'hello'\", 1)");

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('int $x', $output);
        $this->assertStringContainsString("string \$y = 'hello'", $output);
    }

    public function testRenderMethodWithVariadicAndReference(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, is_passed_by_reference, is_variadic, position) VALUES ($memberId, 'ref', 'int', 1, 0, 0)");
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, is_passed_by_reference, is_variadic, position) VALUES ($memberId, 'args', 'string', 0, 1, 1)");

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('int &$ref', $output);
        $this->assertStringContainsString('string ...$args', $output);
    }

    public function testRenderStaticMethod(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility, is_static) VALUES ($entityId, 'bar', 'method', 'public', 1)");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 1, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('public static function bar()', $output);
    }

    public function testRenderAbstractMethod(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility, is_abstract) VALUES ($entityId, 'bar', 'method', 'public', 1)");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 1, 'is_final' => 0, 'return_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('abstract public function bar()', $output);
    }

    public function testRenderFinalMethod(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility, is_final) VALUES ($entityId, 'bar', 'method', 'public', 1)");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 1, 'return_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('final public function bar()', $output);
    }

    public function testRenderProperty(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility, declared_type) VALUES ($entityId, 'name', 'property', 'public', 'string')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'name', 'member_type' => 'property', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'declared_type' => 'string'],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('Properties (1):', $output);
        $this->assertStringContainsString('public string $name', $output);
    }

    public function testRenderStaticProperty(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility, declared_type, is_static) VALUES ($entityId, 'count', 'property', 'private', 'int', 1)");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'count', 'member_type' => 'property', 'visibility' => 'private', 'is_static' => 1, 'is_abstract' => 0, 'is_final' => 0, 'declared_type' => 'int'],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('private static int $count', $output);
    }

    public function testRenderConstant(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'VERSION', 'constant', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'VERSION', 'member_type' => 'constant', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('Constants (1):', $output);
        $this->assertStringContainsString('public const VERSION', $output);
    }

    public function testRenderCase(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Color', 'Color', 'enum')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'Red', 'case')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'Red', 'member_type' => 'case', 'visibility' => null, 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('Cases (1):', $output);
        $this->assertStringContainsString('case Red', $output);
    }

    public function testRenderWithOutgoingCalls(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $fooId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Bar', 'Bar', 'class')");
        $barId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($fooId, 'doStuff', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'doStuff', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $outgoingCalls = [
            ['source_member_id' => $memberId, 'type' => 'call_static_strong', 'target_fqn_resolved' => 'App\\Bar', 'target_id' => $barId],
        ];
        $view = new MembersView($this->makeView($members, $outgoingCalls));
        $output = $view->render();
        $this->assertStringContainsString('strong App\\Bar', $output);
    }

    public function testRenderWithDynamicCallWeak(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $fooId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($fooId, 'doStuff', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'doStuff', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $outgoingCalls = [
            ['source_member_id' => $memberId, 'type' => 'call_dynamic_weak', 'target_fqn_resolved' => 'App\\Baz'],
        ];
        $view = new MembersView($this->makeView($members, $outgoingCalls));
        $output = $view->render();
        $this->assertStringContainsString('weak App\\Baz', $output);
    }

    public function testRenderWithGlobalCall(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $fooId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($fooId, 'doStuff', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'doStuff', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $outgoingCalls = [
            ['source_member_id' => $memberId, 'type' => 'call_global_strong', 'target_fqn_resolved' => 'strlen'],
        ];
        $view = new MembersView($this->makeView($members, $outgoingCalls));
        $output = $view->render();
        $this->assertStringContainsString('strong strlen', $output);
    }

    public function testRenderWithCreatesCall(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $fooId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($fooId, 'doStuff', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'doStuff', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $outgoingCalls = [
            ['source_member_id' => $memberId, 'type' => 'creates', 'target_fqn_resolved' => 'App\\Bar'],
        ];
        $view = new MembersView($this->makeView($members, $outgoingCalls));
        $output = $view->render();
        $this->assertStringContainsString('create App\\Bar', $output);
    }

    public function testRenderWithUnknownRelationshipType(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $fooId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($fooId, 'doStuff', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'doStuff', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $outgoingCalls = [
            ['source_member_id' => $memberId, 'type' => 'dependency', 'target_fqn_resolved' => 'App\\Bar'],
        ];
        $view = new MembersView($this->makeView($members, $outgoingCalls));
        $output = $view->render();
        $this->assertStringContainsString('[dependency] App\\Bar', $output);
    }

    public function testRenderSectionOrder(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'doStuff', 'method', 'public')");
        $methodId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'name', 'property', 'public')");
        $propId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'VERSION', 'constant', 'public')");
        $constId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $methodId, 'name' => 'doStuff', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
            ['id' => $propId, 'name' => 'name', 'member_type' => 'property', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'declared_type' => null],
            ['id' => $constId, 'name' => 'VERSION', 'member_type' => 'constant', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();

        $propPos = strpos($output, 'Properties');
        $constPos = strpos($output, 'Constants');
        $methodPos = strpos($output, 'Methods');

        $this->assertNotFalse($propPos);
        $this->assertNotFalse($constPos);
        $this->assertNotFalse($methodPos);
        $this->assertLessThan($constPos, $propPos);
        $this->assertLessThan($methodPos, $constPos);
    }

    public function testRenderCallsWithNullSourceMemberId(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $fooId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($fooId, 'doStuff', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'doStuff', 'member_type' => 'method', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $outgoingCalls = [
            ['source_member_id' => null, 'type' => 'call_static_strong', 'target_fqn_resolved' => 'App\\Bar'],
        ];
        $view = new MembersView($this->makeView($members, $outgoingCalls));
        $output = $view->render();
        $this->assertStringNotContainsString('App\\Bar', $output);
    }

    public function testRenderPropertyWithoutType(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'data', 'property', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'data', 'member_type' => 'property', 'visibility' => 'public', 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'declared_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('public $data', $output);
    }

    public function testRenderMethodWithoutVisibility(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type) VALUES ($entityId, 'bar', 'method')");
        $memberId = (int) $this->pdo->lastInsertId();

        $members = [
            ['id' => $memberId, 'name' => 'bar', 'member_type' => 'method', 'visibility' => null, 'is_static' => 0, 'is_abstract' => 0, 'is_final' => 0, 'return_type' => null],
        ];
        $view = new MembersView($this->makeView($members));
        $output = $view->render();
        $this->assertStringContainsString('function bar()', $output);
    }
}

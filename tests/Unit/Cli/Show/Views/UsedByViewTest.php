<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Cli\Show\Views;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Show\EntityView;
use SineFine\Ponymator\Cli\Show\Views\UsedByView;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class UsedByViewTest extends TestCase
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

    private function makeView(array $callIncoming = []): EntityView
    {
        return new EntityView(
            entity: ['id' => 1, 'fqn' => 'App\\Foo', 'short_name' => 'Foo', 'type' => 'class'],
            modifiers: [],
            filePath: null,
            outgoingStructural: [],
            structuralIncoming: [],
            members: [],
            outgoingCalls: [],
            callIncoming: $callIncoming,
            external: [],
            query: $this->query,
        );
    }

    public function testRenderEmptyCallIncoming(): void
    {
        $view = new UsedByView($this->makeView());
        $this->assertSame('', $view->render());
    }

    public function testRenderNonCallType(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'extends',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => null,
                'source_member_name' => null,
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => null,
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('Used by (1):', $output);
        $this->assertStringContainsString('[extends] App\\Bar', $output);
    }

    public function testRenderNonCallTypeWithMemberName(): void
    {
        $callIncoming = [
            [
                'type' => 'creates',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => null,
                'source_member_name' => 'factory',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => null,
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('[creates] App\\Bar +factory', $output);
    }

    public function testRenderDynamicCallStrong(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, default_value, position) VALUES ($memberId, 'x', 'int', null, 0)");

        $callIncoming = [
            [
                'type' => 'call_dynamic_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'doStuff',
                'source_member_return_type' => 'void',
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('called by App\\Bar->doStuff(int $x): void', $output);
    }

    public function testRenderDynamicCallWeak(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'call_dynamic_weak',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'doStuff',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('maybe called by App\\Bar->doStuff()', $output);
    }

    public function testRenderStaticCallStrong(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'call_static_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'create',
                'source_member_return_type' => 'self',
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('called by App\\Bar::create(): self', $output);
    }

    public function testRenderStaticCallWeak(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'call_static_weak',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'create',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('maybe called by App\\Bar::create()', $output);
    }

    public function testRenderGlobalCall(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'call_global_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'helper',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('App\\Bar::helper()', $output);
    }

    public function testRenderPropertyAccess(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility, declared_type) VALUES ($entityId, 'name', 'property', 'public', 'string')");
        $memberId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'call_dynamic_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'name',
                'source_member_return_type' => null,
                'source_member_declared_type' => 'string',
                'source_member_type' => 'property',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('App\\Bar->$name (string )', $output);
    }

    public function testRenderPropertyWithoutType(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'data', 'property', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'call_dynamic_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'data',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'property',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('App\\Bar->$data ()', $output);
    }

    public function testRenderCallWithParametersAndDefaults(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, default_value, position) VALUES ($memberId, 'x', 'int', null, 0)");
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, default_value, position) VALUES ($memberId, 'y', 'string', \"'hello'\", 1)");

        $callIncoming = [
            [
                'type' => 'call_dynamic_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'doStuff',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString("int \$x, string \$y = 'hello'", $output);
    }

    public function testRenderCallWithParameterWithoutType(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO parameters (member_id, name, declared_type, default_value, position) VALUES ($memberId, 'x', null, null, 0)");

        $callIncoming = [
            [
                'type' => 'call_dynamic_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'doStuff',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('$x', $output);
    }

    public function testRenderMultipleCallIncoming(): void
    {
        $this->pdo->exec("INSERT INTO entities (fqn, short_name, type) VALUES ('App\\Foo', 'Foo', 'class')");
        $entityId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO members (entity_id, name, member_type, visibility) VALUES ($entityId, 'bar', 'method', 'public')");
        $memberId = (int) $this->pdo->lastInsertId();

        $callIncoming = [
            [
                'type' => 'call_dynamic_strong',
                'source_fqn' => 'App\\Bar',
                'source_member_id' => $memberId,
                'source_member_name' => 'doStuff',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
            [
                'type' => 'call_static_strong',
                'source_fqn' => 'App\\Baz',
                'source_member_id' => $memberId,
                'source_member_name' => 'create',
                'source_member_return_type' => null,
                'source_member_declared_type' => null,
                'source_member_type' => 'method',
            ],
        ];
        $view = new UsedByView($this->makeView($callIncoming));
        $output = $view->render();
        $this->assertStringContainsString('Used by (2):', $output);
        $this->assertStringContainsString('App\\Bar->doStuff', $output);
        $this->assertStringContainsString('App\\Baz::create', $output);
    }
}

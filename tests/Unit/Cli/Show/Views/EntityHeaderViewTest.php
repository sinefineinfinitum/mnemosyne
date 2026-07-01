<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Cli\Show\Views;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Cli\Show\EntityView;
use SineFine\Mnemosyne\Cli\Show\Views\EntityHeaderView;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

class EntityHeaderViewTest extends TestCase
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

    private function makeView(
        array $entity = ['id' => 1, 'fqn' => 'App\\Foo', 'short_name' => 'Foo', 'type' => 'class'],
        array $modifiers = [],
        ?string $filePath = null,
    ): EntityView {
        return new EntityView(
            entity: $entity,
            modifiers: $modifiers,
            filePath: $filePath,
            outgoingStructural: [],
            structuralIncoming: [],
            members: [],
            outgoingCalls: [],
            callIncoming: [],
            external: [],
            query: $this->query,
        );
    }

    public function testRenderBasic(): void
    {
        $view = new EntityHeaderView($this->makeView());
        $output = $view->render();
        $this->assertStringContainsString('Entity: App\\Foo', $output);
        $this->assertStringContainsString('Type: class', $output);
    }

    public function testRenderWithModifiers(): void
    {
        $view = new EntityHeaderView(
            $this->makeView(
                modifiers: ['abstract', 'final'],
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Type: class [abstract, final]', $output);
    }

    public function testRenderWithFilePath(): void
    {
        $view = new EntityHeaderView(
            $this->makeView(
                filePath: 'src/App/Foo.php',
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('File: src/App/Foo.php', $output);
    }

    public function testRenderWithModifiersAndFilePath(): void
    {
        $view = new EntityHeaderView(
            $this->makeView(
                entity: ['id' => 2, 'fqn' => 'App\\Bar', 'short_name' => 'Bar', 'type' => 'interface'],
                modifiers: ['abstract'],
                filePath: 'src/Bar.php',
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Entity: App\\Bar', $output);
        $this->assertStringContainsString('Type: interface [abstract]', $output);
        $this->assertStringContainsString('File: src/Bar.php', $output);
    }

    public function testRenderNoFilePath(): void
    {
        $view = new EntityHeaderView($this->makeView());
        $output = $view->render();
        $this->assertStringNotContainsString('File:', $output);
    }

    public function testRenderEnumType(): void
    {
        $view = new EntityHeaderView(
            $this->makeView(
                entity: ['id' => 3, 'fqn' => 'App\\Color', 'short_name' => 'Color', 'type' => 'enum'],
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Type: enum', $output);
    }

    public function testRenderTraitType(): void
    {
        $view = new EntityHeaderView(
            $this->makeView(
                entity: ['id' => 4, 'fqn' => 'App\\Loggable', 'short_name' => 'Loggable', 'type' => 'trait'],
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Type: trait', $output);
    }

    public function testRenderSingleModifier(): void
    {
        $view = new EntityHeaderView(
            $this->makeView(
                modifiers: ['readonly'],
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Type: class [readonly]', $output);
    }

    public function testRenderLineOrder(): void
    {
        $view = new EntityHeaderView(
            $this->makeView(
                modifiers: ['final'],
                filePath: 'src/Foo.php',
            )
        );
        $output = $view->render();
        $lines = explode("\n", $output);
        $this->assertStringStartsWith('Entity:', $lines[0]);
        $this->assertStringStartsWith('Type:', $lines[1]);
        $this->assertStringStartsWith('File:', $lines[2]);
    }
}

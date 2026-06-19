<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Cli\Show\Views;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Show\EntityView;
use SineFine\Ponymator\Cli\Show\Views\InheritorsView;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class InheritorsViewTest extends TestCase
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

    private function makeView(array $structuralIncoming = []): EntityView
    {
        return new EntityView(
            entity: ['id' => 1, 'fqn' => 'App\\Foo', 'short_name' => 'Foo', 'type' => 'class'],
            modifiers: [],
            filePath: null,
            outgoingStructural: [],
            structuralIncoming: $structuralIncoming,
            members: [],
            outgoingCalls: [],
            callIncoming: [],
            external: [],
            query: $this->query,
        );
    }

    public function testRenderEmpty(): void
    {
        $view = new InheritorsView($this->makeView());
        $this->assertSame('', $view->render());
    }

    public function testRenderInheritors(): void
    {
        $view = new InheritorsView(
            $this->makeView(
                [
                ['type' => 'extends', 'source_fqn' => 'App\\Child'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Inheritors (1):', $output);
        $this->assertStringContainsString('App\\Child', $output);
    }

    public function testRenderImplementers(): void
    {
        $view = new InheritorsView(
            $this->makeView(
                [
                ['type' => 'implements', 'source_fqn' => 'App\\Impl'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Implementers (1):', $output);
        $this->assertStringContainsString('App\\Impl', $output);
    }

    public function testRenderTraitUsers(): void
    {
        $view = new InheritorsView(
            $this->makeView(
                [
                ['type' => 'uses_trait', 'source_fqn' => 'App\\User'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Used by traits (1):', $output);
        $this->assertStringContainsString('App\\User', $output);
    }

    public function testRenderAllThreeTypes(): void
    {
        $view = new InheritorsView(
            $this->makeView(
                [
                ['type' => 'extends', 'source_fqn' => 'App\\Child'],
                ['type' => 'implements', 'source_fqn' => 'App\\Impl'],
                ['type' => 'uses_trait', 'source_fqn' => 'App\\User'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Inheritors (1):', $output);
        $this->assertStringContainsString('Implementers (1):', $output);
        $this->assertStringContainsString('Used by traits (1):', $output);
    }

    public function testRenderMultipleInheritors(): void
    {
        $view = new InheritorsView(
            $this->makeView(
                [
                ['type' => 'extends', 'source_fqn' => 'App\\ChildA'],
                ['type' => 'extends', 'source_fqn' => 'App\\ChildB'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Inheritors (2):', $output);
    }

    public function testRenderFiltersNonStructuralTypes(): void
    {
        $view = new InheritorsView(
            $this->makeView(
                [
                ['type' => 'call_static_strong', 'source_fqn' => 'App\\Caller'],
                ]
            )
        );
        $this->assertSame('', $view->render());
    }
}

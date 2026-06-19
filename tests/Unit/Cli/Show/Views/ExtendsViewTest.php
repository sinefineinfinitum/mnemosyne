<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Cli\Show\Views;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Show\EntityView;
use SineFine\Ponymator\Cli\Show\Views\ExtendsView;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class ExtendsViewTest extends TestCase
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

    private function makeView(array $outgoingStructural = []): EntityView
    {
        return new EntityView(
            entity: ['id' => 1, 'fqn' => 'App\\Foo', 'short_name' => 'Foo', 'type' => 'class'],
            modifiers: [],
            filePath: null,
            outgoingStructural: $outgoingStructural,
            structuralIncoming: [],
            members: [],
            outgoingCalls: [],
            callIncoming: [],
            external: [],
            query: $this->query,
        );
    }

    public function testRenderEmpty(): void
    {
        $view = new ExtendsView($this->makeView());
        $this->assertSame('', $view->render());
    }

    public function testRenderExtends(): void
    {
        $view = new ExtendsView(
            $this->makeView(
                [
                ['type' => 'extends', 'target_fqn_resolved' => 'App\\Parent'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Extends:', $output);
        $this->assertStringContainsString('App\\Parent', $output);
    }

    public function testRenderImplements(): void
    {
        $view = new ExtendsView(
            $this->makeView(
                [
                ['type' => 'implements', 'target_fqn_resolved' => 'App\\InterfaceA'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Implements:', $output);
        $this->assertStringContainsString('App\\InterfaceA', $output);
    }

    public function testRenderUsesTraits(): void
    {
        $view = new ExtendsView(
            $this->makeView(
                [
                ['type' => 'uses_trait', 'target_fqn_resolved' => 'App\\HelperTrait'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Uses traits:', $output);
        $this->assertStringContainsString('App\\HelperTrait', $output);
    }

    public function testRenderAllThreeTypes(): void
    {
        $view = new ExtendsView(
            $this->makeView(
                [
                ['type' => 'extends', 'target_fqn_resolved' => 'App\\Parent'],
                ['type' => 'implements', 'target_fqn_resolved' => 'App\\InterfaceA'],
                ['type' => 'uses_trait', 'target_fqn_resolved' => 'App\\HelperTrait'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Extends:', $output);
        $this->assertStringContainsString('Implements:', $output);
        $this->assertStringContainsString('Uses traits:', $output);
    }

    public function testRenderMultipleInterfaces(): void
    {
        $view = new ExtendsView(
            $this->makeView(
                [
                ['type' => 'implements', 'target_fqn_resolved' => 'App\\InterfaceA'],
                ['type' => 'implements', 'target_fqn_resolved' => 'App\\InterfaceB'],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Implements:', $output);
    }

    public function testRenderFiltersNonStructuralOutgoing(): void
    {
        $view = new ExtendsView(
            $this->makeView(
                [
                ['type' => 'call_static_strong', 'target_fqn_resolved' => 'App\\Caller'],
                ]
            )
        );
        $this->assertSame('', $view->render());
    }

    public function testRenderHandlesNullTargetFqn(): void
    {
        $view = new ExtendsView(
            $this->makeView(
                [
                ['type' => 'extends', 'target_fqn_resolved' => null],
                ]
            )
        );
        $output = $view->render();
        $this->assertStringContainsString('Extends:', $output);
    }
}

<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Renderer\PSR4Renderer;

final class PSR4RendererTest extends TestCase
{
    private PSR4Renderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PSR4Renderer();
    }

    public function testRenderEntityIncludesFrontmatter(): void
    {
        $entity = $this->makeEntity();
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('psr4: true', $result);
        $this->assertStringContainsString('source_hash: abc123', $result);
    }

    public function testRenderEntityIncludesFqn(): void
    {
        $entity = $this->makeEntity();
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('`App\Service\UserService`', $result);
    }

    public function testRenderEntityIncludesTypeAndModifiers(): void
    {
        $entity = $this->makeEntity();
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('**Type:** `final class`', $result);
        $this->assertStringContainsString('**Modifiers:** `final`', $result);
    }

    public function testRenderEntityIncludesParentAndInterfaces(): void
    {
        $entity = $this->makeEntity();
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('`App\Abstracts\BaseService`', $result);
        $this->assertStringContainsString('`App\Contracts\ServiceInterface`', $result);
    }

    public function testRenderEntityIncludesMethodTable(): void
    {
        $entity = $this->makeEntity();
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('`findById`', $result);
        $this->assertStringContainsString('int $id', $result);
    }

    public function testRenderEntityIncludesDependencies(): void
    {
        $entity = $this->makeEntity(
            [
            'dependencies' => ['Psr\Log\LoggerInterface', 'App\Services\Validator'],
            ]
        );
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('`Psr\Log\LoggerInterface`', $result);
        $this->assertStringContainsString('`App\Services\Validator`', $result);
    }

    public function testRenderEntityWithNoMethods(): void
    {
        $entity = $this->makeEntity(['methods' => []]);
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringNotContainsString('API', $result);
    }

    public function testRenderEntityWithNoModifiers(): void
    {
        $entity = $this->makeEntity(['modifiers' => []]);
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('`none`', $result);
    }

    public function testRenderEntityInterfaceType(): void
    {
        $entity = $this->makeEntity(['type' => 'interface']);
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('`interface`', $result);
    }

    public function testRenderEntityEnumType(): void
    {
        $entity = $this->makeEntity(['type' => 'enum']);
        $result = $this->renderer->renderEntity($entity, 'abc123');

        $this->assertStringContainsString('`enum`', $result);
    }

    private function makeEntity(array $overrides = []): array
    {
        return array_merge(
            [
            'fqn' => 'App\Service\UserService',
            'type' => 'class',
            'modifiers' => ['final'],
            'parentClass' => 'App\Abstracts\BaseService',
            'interfaces' => ['App\Contracts\ServiceInterface'],
            'methods' => [
                [
                    'name' => 'findById',
                    'visibility' => 'public',
                    'isStatic' => false,
                    'isAbstract' => false,
                    'parameters' => [
                        ['name' => 'id', 'type' => 'int', 'typeNullable' => false, 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                    ],
                    'returnType' => '?User',
                    'returnTypeNullable' => true,
                ],
            ],
            'dependencies' => [],
            ], $overrides
        );
    }
}

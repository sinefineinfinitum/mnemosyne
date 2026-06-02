<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;

final class InterfaceRendererTest extends TestCase
{
    private InterfaceRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new InterfaceRenderer(new MarkdownBuilder());
    }

    public function testRenderEntityIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('type: interface', $result);
    }

    public function testRenderEntityIncludesFqn(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('`App\Contracts\ServiceInterface`', $result);
    }

    public function testRenderEntityExtendedInterfaces(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('extends `App\Contracts\BaseInterface`', $result);
    }

    public function testRenderEntityMethodSignatures(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('`public function findById(', $result);
        $this->assertStringContainsString('`int`', $result);
        $this->assertStringContainsString('` $id`', $result);
        $this->assertStringContainsString('`): `', $result);
        $this->assertStringContainsString('`?User`', $result);
    }

    public function testRenderEntityConstants(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'DEFAULT_LIMIT', 'visibility' => 'public', 'type' => 'int', 'value' => '10'],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('DEFAULT_LIMIT', $result);
    }

    public function testRenderEntityKnownImplementations(): void
    {
        $crossRefs = new CrossReference([], ['[App\Service\UserService](UserService.md)', '[App\Service\AdminService](AdminService.md)']);
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('[App\Service\UserService](UserService.md)', $result);
        $this->assertStringContainsString('[App\Service\AdminService](AdminService.md)', $result);
    }

    public function testRenderEntityKnownImplementationsFilteredByFqn(): void
    {
        $crossRefs = new CrossReference([], ['[App\Service\UserService](UserService.md)']);
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('[App\Service\UserService](UserService.md)', $result);
    }

    public function testRenderEntityNoDependenciesSection(): void
    {
        $crossRefs = new CrossReference(['`App\Models\User`']);
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString('### Dependencies', $result);
    }

    public function testRenderEntityNoExtendedInterfaces(): void
    {
        $entity = $this->makeEntity(['interfaces' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('implements ', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeEntity(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('Constants', $result);
    }

    public function testRenderEntityNoKnownImplementations(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('Known implementations', $result);
    }

    public function testRenderEntityNoHeadSection(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('### Head', $result);
    }

    public function testRenderEntityHashIsDeterministic(): void
    {
        $entity = $this->makeEntity();
        $crossRefs = new CrossReference();
        $first = $this->renderer->renderEntity($entity, $crossRefs);
        $second = $this->renderer->renderEntity($entity, $crossRefs);
        $this->assertSame($first, $second);
    }

    public function testRenderEntityNoImplementationsForDifferentInterface(): void
    {
        $crossRefs = new CrossReference();
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString('Known implementations', $result);
    }

    private function makeEntity(array $overrides = []): array
    {
        return array_merge(
            [
                'fqn' => 'App\Contracts\ServiceInterface',
                'type' => 'interface',
                'modifiers' => [],
                'parentClass' => null,
                'interfaces' => ['App\Contracts\BaseInterface'],
                'constants' => [],
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
            ],
            $overrides
        );
    }
}

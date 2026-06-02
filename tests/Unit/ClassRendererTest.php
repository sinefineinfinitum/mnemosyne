<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;

final class ClassRendererTest extends TestCase
{
    private ClassRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ClassRenderer(new MarkdownBuilder());
    }

    public function testRenderEntityIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('type: class', $result);
    }

    public function testRenderEntityIncludesFqn(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('`App\Service\UserService`', $result);
    }

    public function testRenderEntityTypeAndModifiers(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('`final class`', $result);
        $this->assertStringNotContainsString('**Modifiers:**', $result);
    }

    public function testRenderEntityParentAndInterfaces(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('extends `App\Abstracts\BaseService`', $result);
        $this->assertStringContainsString('implements `App\Contracts\ServiceInterface`', $result);
    }

    public function testRenderEntityInlineMethodSignatures(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('`public function findById(', $result);
        $this->assertStringContainsString('`int`', $result);
        $this->assertStringContainsString('` $id`', $result);
        $this->assertStringContainsString('`?bool`', $result);
        $this->assertStringContainsString('` $active = true`', $result);
        $this->assertStringContainsString('`): `', $result);
        $this->assertStringContainsString('`?User`', $result);
    }

    public function testRenderEntityNoDependenciesSection(): void
    {
        $crossRefs = new CrossReference(['`Psr\Log\LoggerInterface`', '`App\Services\Validator`']);
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString('### Dependencies', $result);
    }

    public function testRenderEntityConstants(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'MAX', 'visibility' => 'public', 'type' => 'int', 'value' => '100'],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('MAX', $result);
        $this->assertStringContainsString('100', $result);
    }

    public function testRenderEntityNoMethods(): void
    {
        $entity = $this->makeEntity(['methods' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('API', $result);
    }

    public function testRenderEntityNoModifiers(): void
    {
        $entity = $this->makeEntity(['modifiers' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('`class`', $result);
        $this->assertStringNotContainsString('**Modifiers:**', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeEntity(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('Constants', $result);
    }

    public function testRenderEntityNoHeadSection(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('### Head', $result);
    }

    public function testRenderEntityNoExternalDependencies(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('External Dependencies', $result);
    }

    public function testRenderEntityHashIsDeterministic(): void
    {
        $entity = $this->makeEntity();
        $first = $this->renderer->renderEntity($entity, new CrossReference());
        $second = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertSame($first, $second);
    }

    public function testRenderEntityWithParentNull(): void
    {
        $entity = $this->makeEntity(['parentClass' => null]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('extends', $result);
    }

    public function testRenderEntityWithNoInterfaces(): void
    {
        $entity = $this->makeEntity(['interfaces' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('implements', $result);
    }

    public function testRenderEntityCreatesSectionWithData(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [
            'build' => ['\App\Entity\User'],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('### Creates', $result);
        $this->assertStringContainsString('`build`', $result);
        $this->assertStringContainsString('`\\App\\Entity\\User`', $result);
    }

    public function testRenderEntityNoCreatesSectionWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('### Creates', $result);
    }

    public function testRenderEntityCreatesSectionDeterministic(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [
            'foo' => ['\App\A'],
            'bar' => ['\App\B'],
            ]
        );
        $first = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $second = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertSame($first, $second);
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
                'constants' => [],
                'methods' => [
                    [
                        'name' => 'findById',
                        'visibility' => 'public',
                        'isStatic' => false,
                        'isAbstract' => false,
                        'parameters' => [
                            ['name' => 'id', 'type' => 'int', 'typeNullable' => false, 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                            ['name' => 'active', 'type' => '?bool', 'typeNullable' => true, 'defaultValue' => 'true', 'isVariadic' => false, 'isPassedByReference' => false],
                        ],
                        'returnType' => '?User',
                        'returnTypeNullable' => true,
                    ],
                ],
                'dependencies' => ['App\Models\User'],
            ],
            $overrides
        );
    }
}

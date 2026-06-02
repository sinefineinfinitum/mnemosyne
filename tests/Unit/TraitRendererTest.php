<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Generator\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\TraitRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;

final class TraitRendererTest extends TestCase
{
    private TraitRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TraitRenderer(new MarkdownBuilder());
    }

    public function testRenderEntityIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('type: trait', $result);
    }

    public function testRenderEntityIncludesFqn(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('`App\Traits\LoggableTrait`', $result);
    }

    public function testRenderEntityProtectedMethods(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('`protected function formatMessage(', $result);
        $this->assertStringContainsString('`string`', $result);
        $this->assertStringContainsString('` $msg`', $result);
        $this->assertStringContainsString('`): `', $result);
        $this->assertStringContainsString('`public function log(', $result);
        $this->assertStringContainsString('` $message`', $result);
        $this->assertStringContainsString('`void`', $result);
    }

    public function testRenderEntityConstants(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'LOG_LEVEL', 'visibility' => 'public', 'type' => 'string', 'value' => "'debug'"],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('LOG_LEVEL', $result);
    }

    public function testRenderEntityUsingClasses(): void
    {
        $crossRefs = new CrossReference([], ['[App\Service\UserService](UserService.md)']);
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('[App\Service\UserService](UserService.md)', $result);
    }

    public function testRenderEntityUsingClassesFilteredByFqn(): void
    {
        $crossRefs = new CrossReference([], ['[App\Service\UserService](UserService.md)']);
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('[App\Service\UserService](UserService.md)', $result);
    }

    public function testRenderEntityNoUsingClassesForDifferentTrait(): void
    {
        $crossRefs = new CrossReference();
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString('Classes using this trait', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeEntity(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('Constants', $result);
    }

    public function testRenderEntityNoUsingClasses(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('Classes using this trait', $result);
    }

    public function testRenderEntityNoDependenciesSection(): void
    {
        $crossRefs = new CrossReference(['`Psr\Log\LoggerInterface`']);
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString('### Dependencies', $result);
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

    private function makeEntity(array $overrides = []): array
    {
        return array_merge(
            [
                'fqn' => 'App\Traits\LoggableTrait',
                'type' => 'trait',
                'modifiers' => [],
                'parentClass' => null,
                'interfaces' => [],
                'constants' => [],
                'methods' => [
                    [
                        'name' => 'log',
                        'visibility' => 'public',
                        'isStatic' => false,
                        'isAbstract' => false,
                        'parameters' => [
                            ['name' => 'message', 'type' => 'string', 'typeNullable' => false, 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                        ],
                        'returnType' => 'void',
                        'returnTypeNullable' => false,
                    ],
                    [
                        'name' => 'formatMessage',
                        'visibility' => 'protected',
                        'isStatic' => false,
                        'isAbstract' => false,
                        'parameters' => [
                            ['name' => 'msg', 'type' => 'string', 'typeNullable' => false, 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                        ],
                        'returnType' => 'string',
                        'returnTypeNullable' => false,
                    ],
                ],
                'dependencies' => [],
            ],
            $overrides
        );
    }
}

<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
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
        $result = $this->renderer->renderEntity($this->makeEntity(), []);
        $this->assertStringContainsString('type: trait', $result);
    }

    public function testRenderEntityIncludesFqn(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), []);
        $this->assertStringContainsString('`App\Traits\LoggableTrait`', $result);
    }

    public function testRenderEntityProtectedMethods(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), []);
        $this->assertStringContainsString('protected function formatMessage(string $msg): string', $result);
        $this->assertStringContainsString('public function log(string $message): void', $result);
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
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringContainsString('LOG_LEVEL', $result);
    }

    public function testRenderEntityUsingClasses(): void
    {
        $crossRefs = ['trait_usage' => ['App\Traits\LoggableTrait' => ['App\Service\UserService']]];
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('`App\Service\UserService`', $result);
    }

    public function testRenderEntityUsingClassesFilteredByFqn(): void
    {
        $crossRefs = [
            'trait_usage' => [
                'App\Traits\LoggableTrait' => ['App\Service\UserService'],
                'App\Traits\OtherTrait' => ['App\Service\OtherService'],
            ],
        ];
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('`App\Service\UserService`', $result);
        $this->assertStringNotContainsString('`App\Service\OtherService`', $result);
    }

    public function testRenderEntityNoUsingClassesForDifferentTrait(): void
    {
        $crossRefs = [
            'trait_usage' => [
                'App\Traits\OtherTrait' => ['App\Service\OtherService'],
            ],
        ];
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString('Classes using this trait', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeEntity(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringNotContainsString('Constants', $result);
    }

    public function testRenderEntityNoUsingClasses(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), []);
        $this->assertStringNotContainsString('Classes using this trait', $result);
    }

    public function testRenderEntityIncludesDependencies(): void
    {
        $entity = $this->makeEntity(['dependencies' => ['Psr\Log\LoggerInterface']]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringContainsString('`Psr\Log\LoggerInterface`', $result);
    }

    public function testRenderEntityNoDependencies(): void
    {
        $entity = $this->makeEntity(['dependencies' => []]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringNotContainsString('External Dependencies', $result);
    }

    public function testRenderEntityHashIsDeterministic(): void
    {
        $entity = $this->makeEntity();
        $crossRefs = ['trait_usage' => ['App\Traits\LoggableTrait' => ['App\Service\UserService']]];
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

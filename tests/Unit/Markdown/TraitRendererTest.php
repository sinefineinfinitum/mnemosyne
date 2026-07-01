<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Markdown;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\CallInfo;
use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\TraitRenderer;

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

    public function testRenderEntityCreatesSectionWithData(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [
            'log' => ['\App\Cache\RedisCache'],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('**Creates:**', $result);
        $this->assertStringContainsString('App\Cache\RedisCache', $result);
    }

    public function testRenderEntityNoCreatesSectionWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('**Creates:**', $result);
    }

    public function testRenderEntityCreatesSectionDeterministic(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [
            'foo' => ['\App\A'],
            ]
        );
        $first = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $second = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertSame($first, $second);
    }

    public function testRenderEntityHashIsDeterministic(): void
    {
        $entity = $this->makeEntity();
        $crossRefs = new CrossReference();
        $first = $this->renderer->renderEntity($entity, $crossRefs);
        $second = $this->renderer->renderEntity($entity, $crossRefs);
        $this->assertSame($first, $second);
    }

    public function testRenderEntityReadonlyProperty(): void
    {
        $entity = $this->makeEntity(
            [
            'properties' => [
                ['name' => 'cache', 'visibility' => 'protected', 'type' => 'array', 'defaultValue' => '[]', 'isStatic' => false, 'isReadonly' => true],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('readonly', $result);
    }

    public function testRenderEntitySelfReturnType(): void
    {
        $entity = $this->makeEntity(
            [
            'methods' => [
                [
                    'name' => 'withConfig',
                    'visibility' => 'public',
                    'isStatic' => false,
                    'isAbstract' => false,
                    'parameters' => [],
                    'returnType' => 'self',

                ],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('self', $result);
    }

    public function testRenderEntityCallGraphSectionWithData(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'log' => [
                new CallInfo(CallInfo::KIND_STATIC, 'write', [], 'App\\Logger\\FileWriter::write', CallInfo::STRONG),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('**Calls:**', $result);
        $this->assertStringContainsString('write', $result);
        $this->assertStringContainsString('App\\Logger\\FileWriter', $result);
    }

    public function testRenderEntityNoCallGraphSectionWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('**Calls:**', $result);
    }

    public function testRenderEntityCallGraphUnknownTarget(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'log' => [
                new CallInfo(CallInfo::KIND_GLOBAL, 'externalFunc'),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('**Calls:**', $result);
        $this->assertStringContainsString('weak', $result);
        $this->assertStringContainsString('externalFunc', $result);
        $this->assertStringNotContainsString('Unknown', $result);
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
                            ['name' => 'message', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                        ],
                        'returnType' => 'void',

                    ],
                    [
                        'name' => 'formatMessage',
                        'visibility' => 'protected',
                        'isStatic' => false,
                        'isAbstract' => false,
                        'parameters' => [
                            ['name' => 'msg', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                        ],
                        'returnType' => 'string',

                    ],
                ],
                'dependencies' => [],
            ],
            $overrides
        );
    }
}

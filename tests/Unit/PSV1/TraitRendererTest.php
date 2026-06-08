<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\PSV1;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\CallInfo;
use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\PSV1\Psv1Builder;
use SineFine\Ponymator\Documentation\Renderer\PSV1\TraitRenderer;

final class TraitRendererTest extends TestCase
{
    private TraitRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TraitRenderer(new Psv1Builder());
    }

    public function testSupportsTrait(): void
    {
        $this->assertTrue($this->renderer->supports(['type' => 'trait']));
        $this->assertFalse($this->renderer->supports(['type' => 'class']));
        $this->assertFalse($this->renderer->supports(['type' => 'interface']));
        $this->assertFalse($this->renderer->supports(['type' => 'enum']));
    }

    public function testRenderEntityHeader(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('@trait App\Traits\LoggableTrait', $result);
    }

    public function testRenderEntityTraits(): void
    {
        $entity = $this->makeEntity(['traits' => ['App\TimestampsTrait', 'App\CacheableTrait']]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('%App\TimestampsTrait', $result);
        $this->assertStringContainsString('%App\CacheableTrait', $result);
    }

    public function testRenderEntityNoTraits(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('%', $result);
    }

    public function testRenderEntityTraitsOrder(): void
    {
        $entity = $this->makeEntity(['traits' => ['App\A', 'App\B', 'App\C']]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $lines = explode(PHP_EOL, $result);
        $posA = array_search('%App\A', $lines);
        $posB = array_search('%App\B', $lines);
        $posC = array_search('%App\C', $lines);
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertNotFalse($posC);
        $this->assertLessThan($posB, $posA);
        $this->assertLessThan($posC, $posB);
    }

    public function testRenderEntityConstants(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'LOG_LEVEL', 'visibility' => 'private', 'type' => 'string', 'value' => "'debug'"],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString("!-LOG_LEVEL:string='debug'", $result);
    }

    public function testRenderEntityProperties(): void
    {
        $entity = $this->makeEntity(
            [
            'properties' => [
                ['name' => 'logger', 'visibility' => 'protected', 'type' => 'Psr\Log\LoggerInterface', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('$#logger:Psr\Log\LoggerInterface', $result);
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
        $this->assertStringContainsString('$#readonly cache:array=[]', $result);
    }

    public function testRenderEntityMethods(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('.+log', $result);
        $this->assertStringContainsString('.+setLogger', $result);
    }

    public function testRenderEntityMethodParameters(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('    $message:string', $result);
        $this->assertStringContainsString('    $logger:Psr\Log\LoggerInterface', $result);
    }

    public function testRenderEntityMethodReturnType(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('    :void', $result);
    }

    public function testRenderEntityCreates(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [
            'log' => ['\App\Cache\RedisCache'],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('    ^\App\Cache\RedisCache', $result);
    }

    public function testRenderEntityNoCreatesWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('^', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeEntity(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('!', $result);
    }

    public function testRenderEntityNoProperties(): void
    {
        $entity = $this->makeEntity(['properties' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('$-', $result);
    }

    public function testRenderEntityNoMethods(): void
    {
        $entity = $this->makeEntity(['methods' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('.+', $result);
    }

    public function testRenderEntityDeterministic(): void
    {
        $entity = $this->makeEntity();
        $crossRefs = new CrossReference([], [], null, ['init' => ['\App\C']]);
        $first = $this->renderer->renderEntity($entity, $crossRefs);
        $second = $this->renderer->renderEntity($entity, $crossRefs);
        $this->assertSame($first, $second);
    }

    public function testRenderEntitySelfReturnType(): void
    {
        $entity = $this->makeEntity(
            [
            'methods' => [
                [
                    'name' => 'withConfig',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'parameters' => [],
                    'returnType' => 'self',
                ],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('    :self', $result);
    }

    public function testRenderEntityOrderConstantsPropertiesMethods(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'C', 'visibility' => 'public', 'type' => 'int', 'value' => '1'],
            ],
            'properties' => [
                ['name' => 'p', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false],
            ],
            'methods' => [
                [
                    'name' => 'm',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'parameters' => [],
                    'returnType' => 'void',
                ],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $constPos = strpos($result, '!+C:int=1');
        $propPos = strpos($result, '$+p:string');
        $methodPos = strpos($result, '.+m');
        $this->assertNotFalse($constPos);
        $this->assertNotFalse($propPos);
        $this->assertNotFalse($methodPos);
        $this->assertLessThan($propPos, $constPos);
        $this->assertLessThan($methodPos, $propPos);
    }

    public function testRenderEntityCallGraphEntryEmittedForMethod(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'log' => [
                new CallInfo(CallInfo::KIND_STATIC, 'write', [], 'App\\Logger\\Writer::write', CallInfo::STRONG),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('    *App\\Logger\\Writer::write', $result);
    }

    public function testRenderEntityNoCallGraphWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString(' (static)', $result);
        $this->assertStringNotContainsString(' (dynamic)', $result);
    }

    public function testRenderEntityCallGraphIgnoresUnknownMethods(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'nonExistent' => [
                new CallInfo(CallInfo::KIND_DYNAMIC, 'foo'),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString(' (dynamic)', $result);
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
            'traits' => [],
            'constants' => [],
            'properties' => [],
            'methods' => [
                [
                    'name' => 'log',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'parameters' => [
                        ['name' => 'message', 'type' => 'string', 'defaultValue' => null, 'isPassedByReference' => false, 'isVariadic' => false],
                    ],
                    'returnType' => 'void',
                ],
                [
                    'name' => 'setLogger',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'parameters' => [
                        ['name' => 'logger', 'type' => 'Psr\Log\LoggerInterface', 'defaultValue' => null, 'isPassedByReference' => false, 'isVariadic' => false],
                    ],
                    'returnType' => 'void',
                ],
            ],
            'dependencies' => [],
            ], $overrides
        );
    }
}

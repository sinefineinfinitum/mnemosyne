<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\PSV1;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\InterfaceRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\Msv1Builder;

final class InterfaceRendererTest extends TestCase
{
    private InterfaceRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new InterfaceRenderer(new Msv1Builder());
    }

    public function testSupportsInterface(): void
    {
        $this->assertTrue($this->renderer->supports(['type' => 'interface']));
        $this->assertFalse($this->renderer->supports(['type' => 'class']));
        $this->assertFalse($this->renderer->supports(['type' => 'trait']));
        $this->assertFalse($this->renderer->supports(['type' => 'enum']));
    }

    public function testRenderEntityHeader(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('@interface App\Contracts\ServiceInterface', $result);
    }

    public function testRenderEntityExtendsInterfaces(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('>App\Contracts\BaseInterface', $result);
    }

    public function testRenderEntityNoExtendedInterfaces(): void
    {
        $entity = $this->makeEntity(['interfaces' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('>', $result);
    }

    public function testRenderEntityMultipleExtendedInterfaces(): void
    {
        $entity = $this->makeEntity(
            [
            'interfaces' => ['App\Contracts\BaseInterface', 'App\Contracts\Loggable'],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('>App\Contracts\BaseInterface', $result);
        $this->assertStringContainsString('>App\Contracts\Loggable', $result);
    }

    public function testRenderEntityMethods(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('.+findById', $result);
    }

    public function testRenderEntityMethodParameters(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('    $id:int', $result);
    }

    public function testRenderEntityMethodReturnType(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('    :User|null', $result);
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
        $this->assertStringContainsString('!+DEFAULT_LIMIT:int=10', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeEntity(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('!', $result);
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
        $first = $this->renderer->renderEntity($entity, new CrossReference());
        $second = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertSame($first, $second);
    }

    public function testRenderEntityOrderConstantsBeforeMethods(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'C', 'visibility' => 'public', 'type' => 'int', 'value' => '1'],
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
        $methodPos = strpos($result, '.+m');
        $this->assertNotFalse($constPos);
        $this->assertNotFalse($methodPos);
        $this->assertLessThan($methodPos, $constPos);
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
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'parameters' => [
                        ['name' => 'id', 'type' => 'int', 'defaultValue' => null, 'isPassedByReference' => false, 'isVariadic' => false],
                    ],
                    'returnType' => '?User',
                ],
            ],
            'dependencies' => [],
            ], $overrides
        );
    }
}

<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\PSV1;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\PSV1\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\Psv1Builder;

final class EnumRendererTest extends TestCase
{
    private EnumRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new EnumRenderer(new Psv1Builder());
    }

    public function testSupportsEnum(): void
    {
        $this->assertTrue($this->renderer->supports(['type' => 'enum']));
        $this->assertFalse($this->renderer->supports(['type' => 'class']));
        $this->assertFalse($this->renderer->supports(['type' => 'interface']));
        $this->assertFalse($this->renderer->supports(['type' => 'trait']));
    }

    public function testRenderEntityHeader(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('@enum App\Status', $result);
    }

    public function testRenderEntityCasesBackedInt(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('~Active=1', $result);
        $this->assertStringContainsString('~Inactive=2', $result);
    }

    public function testRenderEntityCasePure(): void
    {
        $result = $this->renderer->renderEntity($this->makePureEnum(), new CrossReference());
        $this->assertStringContainsString('~Active', $result);
        $this->assertStringContainsString('~Pending', $result);
    }

    public function testRenderEntityCaseWithoutValue(): void
    {
        $cases = [
            ['name' => 'Active', 'value' => '1'],
            ['name' => 'Pending'],
        ];
        $entity = $this->makeBackedEnum(['cases' => $cases]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('~Active=1', $result);
        $this->assertStringContainsString('~Pending', $result);
    }

    public function testRenderEntityConstants(): void
    {
        $entity = $this->makeBackedEnum([
            'constants' => [
                ['name' => 'DEFAULT', 'visibility' => 'public', 'type' => 'string', 'value' => "'active'"],
            ],
        ]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString("!+DEFAULT:string='active'", $result);
    }

    public function testRenderEntityProperties(): void
    {
        $entity = $this->makeBackedEnum([
            'properties' => [
                ['name' => 'label', 'visibility' => 'private', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => true],
            ],
        ]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('$-readonly label:string', $result);
    }

    public function testRenderEntityMethods(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('.+isActive', $result);
    }

    public function testRenderEntityMethodParameters(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('    $prefix:string', $result);
    }

    public function testRenderEntityMethodReturnType(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('    :bool', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeBackedEnum(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('!', $result);
    }

    public function testRenderEntityNoProperties(): void
    {
        $entity = $this->makeBackedEnum(['properties' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('$-', $result);
    }

    public function testRenderEntityNoMethods(): void
    {
        $entity = $this->makeBackedEnum(['methods' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('.', $result);
    }

    public function testRenderEntityDeterministic(): void
    {
        $entity = $this->makeBackedEnum();
        $first = $this->renderer->renderEntity($entity, new CrossReference());
        $second = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertSame($first, $second);
    }

    private function makeBackedEnum(array $overrides = []): array
    {
        return array_merge([
            'fqn' => 'App\Status',
            'type' => 'enum',
            'scalarType' => 'int',
            'cases' => [
                ['name' => 'Active', 'value' => '1'],
                ['name' => 'Inactive', 'value' => '2'],
            ],
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => [],
            'constants' => [],
            'properties' => [],
            'methods' => [
                [
                    'name' => 'isActive',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'parameters' => [
                        ['name' => 'prefix', 'type' => 'string', 'defaultValue' => null, 'isPassedByReference' => false, 'isVariadic' => false],
                    ],
                    'returnType' => 'bool',
                ],
            ],
            'dependencies' => [],
        ], $overrides);
    }

    private function makePureEnum(array $overrides = []): array
    {
        return array_merge([
            'fqn' => 'App\Status',
            'type' => 'enum',
            'scalarType' => null,
            'cases' => [
                ['name' => 'Active'],
                ['name' => 'Pending'],
            ],
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => [],
            'constants' => [],
            'properties' => [],
            'methods' => [],
            'dependencies' => [],
        ], $overrides);
    }
}

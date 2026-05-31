<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Renderer\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;

final class EnumRendererTest extends TestCase
{
    private EnumRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new EnumRenderer(new MarkdownBuilder());
    }

    public function testRenderEntityIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), []);
        $this->assertStringContainsString('type: enum', $result);
    }

    public function testRenderEntityIncludesFqn(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), []);
        $this->assertStringContainsString('`App\Enum\Status`', $result);
    }

    public function testRenderEntityBackingType(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), []);
        $this->assertStringContainsString('**Backing type:** `string`', $result);
    }

    public function testRenderEntityCases(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), []);
        $this->assertStringContainsString('`Active`', $result);
        $this->assertStringContainsString("`'active'`", $result);
        $this->assertStringContainsString('`Inactive`', $result);
        $this->assertStringContainsString("`'inactive'`", $result);
    }

    public function testRenderEntityConstants(): void
    {
        $entity = $this->makeBackedEnum(
            [
            'constants' => [
                ['name' => 'DEFAULT', 'visibility' => 'public', 'type' => 'string', 'value' => "'active'"],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringContainsString('DEFAULT', $result);
    }

    public function testRenderEntityMethodSignatures(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), []);
        $this->assertStringContainsString('public function isActive(): bool', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeBackedEnum(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringNotContainsString('Constants', $result);
    }

    public function testRenderEntityNoMethods(): void
    {
        $entity = $this->makeBackedEnum(['methods' => []]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringNotContainsString('Public methods', $result);
    }

    public function testRenderPureEnumNoBackingType(): void
    {
        $entity = $this->makeBackedEnum(['scalarType' => null]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringNotContainsString('Backing type', $result);
    }

    public function testRenderEntityIncludesDependencies(): void
    {
        $entity = $this->makeBackedEnum(['dependencies' => ['App\Models\StatusType']]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringContainsString('`App\Models\StatusType`', $result);
    }

    public function testRenderEntityNoDependencies(): void
    {
        $entity = $this->makeBackedEnum(['dependencies' => []]);
        $result = $this->renderer->renderEntity($entity, []);
        $this->assertStringNotContainsString('External Dependencies', $result);
    }

    public function testRenderEntityHashIsDeterministic(): void
    {
        $entity = $this->makeBackedEnum();
        $first = $this->renderer->renderEntity($entity, []);
        $second = $this->renderer->renderEntity($entity, []);
        $this->assertSame($first, $second);
    }

    private function makeBackedEnum(array $overrides = []): array
    {
        return array_merge(
            [
                'fqn' => 'App\Enum\Status',
                'type' => 'enum',
                'scalarType' => 'string',
                'cases' => [
                    ['name' => 'Active', 'value' => "'active'"],
                    ['name' => 'Inactive', 'value' => "'inactive'"],
                ],
                'modifiers' => [],
                'parentClass' => null,
                'interfaces' => [],
                'constants' => [],
                'methods' => [
                    [
                        'name' => 'isActive',
                        'visibility' => 'public',
                        'isStatic' => false,
                        'isAbstract' => false,
                        'parameters' => [],
                        'returnType' => 'bool',
                        'returnTypeNullable' => false,
                    ],
                ],
                'dependencies' => [],
            ],
            $overrides
        );
    }
}

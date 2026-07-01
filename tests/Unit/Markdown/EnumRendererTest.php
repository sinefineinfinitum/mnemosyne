<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Markdown;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\CallInfo;
use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\EnumRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\MarkdownBuilder;

final class EnumRendererTest extends TestCase
{
    private EnumRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new EnumRenderer(new MarkdownBuilder());
    }

    public function testRenderEntityIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('type: enum', $result);
    }

    public function testRenderEntityIncludesFqn(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('`App\Enum\Status`', $result);
    }

    public function testRenderEntityBackingType(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('`backed enum` of `string`', $result);
    }

    public function testRenderEntityCases(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
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
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('DEFAULT', $result);
    }

    public function testRenderEntityMethodSignatures(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringContainsString('`public function isActive(', $result);
        $this->assertStringContainsString('`): `', $result);
        $this->assertStringContainsString('`bool`', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeBackedEnum(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('Constants', $result);
    }

    public function testRenderEntityNoMethods(): void
    {
        $entity = $this->makeBackedEnum(['methods' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('Public methods', $result);
    }

    public function testRenderPureEnumNoBackingType(): void
    {
        $entity = $this->makeBackedEnum(['scalarType' => null]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('`enum`', $result);
        $this->assertStringNotContainsString('of', $result);
    }

    public function testRenderEntityNoDependenciesSection(): void
    {
        $crossRefs = new CrossReference(['`App\Models\StatusType`']);
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), $crossRefs);
        $this->assertStringNotContainsString('### Dependencies', $result);
    }

    public function testRenderEntityNoHeadSection(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringNotContainsString('### Head', $result);
    }

    public function testRenderEntityHashIsDeterministic(): void
    {
        $entity = $this->makeBackedEnum();
        $first = $this->renderer->renderEntity($entity, new CrossReference());
        $second = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertSame($first, $second);
    }

    public function testRenderEntityCallGraphSectionWithData(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'isActive' => [
                new CallInfo(CallInfo::KIND_STATIC, 'check', [], 'App\\Checker\\StatusChecker::check', CallInfo::STRONG),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), $crossRefs);
        $this->assertStringContainsString('**Calls:**', $result);
        $this->assertStringContainsString('check', $result);
        $this->assertStringContainsString('App\\Checker\\StatusChecker', $result);
    }

    public function testRenderEntityNoCallGraphSectionWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), new CrossReference());
        $this->assertStringNotContainsString('**Calls:**', $result);
    }

    public function testRenderEntityCallGraphUnresolvedShowsCandidates(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'isActive' => [
                new CallInfo(CallInfo::KIND_DYNAMIC, 'check', ['App\\X', 'App\\Y']),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeBackedEnum(), $crossRefs);
        $this->assertStringContainsString('**Calls:**', $result);
        $this->assertStringContainsString('weak', $result);
        $this->assertStringContainsString('check', $result);
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

                    ],
                ],
                'dependencies' => [],
            ],
            $overrides
        );
    }
}

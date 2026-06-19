<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Markdown;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Renderer\Markdown\MarkdownBuilder;

final class MarkdownBuilderTest extends TestCase
{
    private MarkdownBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MarkdownBuilder();
    }

    public function testFrontmatter(): void
    {
        $result = $this->builder->frontmatter(['title' => 'Test', 'type' => 'class']);
        $this->assertSame("---\ntitle: Test\ntype: class\n---\n", $result);
    }

    public function testFrontmatterEmpty(): void
    {
        $result = $this->builder->frontmatter([]);
        $this->assertSame("---\n---\n", $result);
    }

    public function testFrontmatterDeterministic(): void
    {
        $pairs = ['b' => 'y', 'a' => 'x'];
        $first = $this->builder->frontmatter($pairs);
        $second = $this->builder->frontmatter($pairs);
        $this->assertSame($first, $second);
    }

    public function testHeaderLevel1(): void
    {
        $this->assertSame("# Title\n", $this->builder->header(1, 'Title'));
    }

    public function testHeaderLevel2(): void
    {
        $this->assertSame("## Title\n", $this->builder->header(2, 'Title'));
    }

    public function testHeaderLevel6(): void
    {
        $this->assertSame("###### Title\n", $this->builder->header(6, 'Title'));
    }

    /**
     * @dataProvider provideTableData
     */
    public function testTable(array $headers, array $rows, string $expected): void
    {
        $this->assertSame($expected, $this->builder->table($headers, $rows));
    }

    public static function provideTableData(): iterable
    {
        yield 'basic' => [
            ['A', 'B'],
            [['1', '2'], ['3', '4']],
            "|A|B|\n|---|---|\n|1|2|\n|3|4|\n",
        ];

        yield 'single row' => [
            ['H'],
            [['v']],
            "|H|\n|---|\n|v|\n",
        ];

        yield 'pipe in cell' => [
            ['Name'],
            [['a|b']],
            "|Name|\n|---|\n|a\\|b|\n",
        ];

        yield 'backslash in cell' => [
            ['Path'],
            [['a\\b']],
            "|Path|\n|---|\n|a\\\\b|\n",
        ];

        yield 'pipe and backslash' => [
            ['Val'],
            [['a\\|b']],
            "|Val|\n|---|\n|a\\\\\\|b|\n",
        ];

        yield 'multiple pipes' => [
            ['V'],
            [['x|y|z']],
            "|V|\n|---|\n|x\\|y\\|z|\n",
        ];

        yield 'em dash' => [
            ['A'],
            [['—']],
            "|A|\n|---|\n|—|\n",
        ];
    }

    public function testTableEmptyHeaders(): void
    {
        $result = $this->builder->table([], [['a']]);
        $this->assertSame("||\n||\n|a|\n", $result);
    }

    public function testTableDeterministic(): void
    {
        $h = ['A', 'B'];
        $r = [['1', '2']];
        $first = $this->builder->table($h, $r);
        $second = $this->builder->table($h, $r);
        $this->assertSame($first, $second);
    }

    /**
     * @dataProvider provideCodeBlockData
     */
    public function testCodeBlock(string $code, string $lang, string $expected): void
    {
        $this->assertSame($expected, $this->builder->codeBlock($code, $lang));
    }

    public static function provideCodeBlockData(): iterable
    {
        yield 'no lang' => ['echo "hi";', '', "```\necho \"hi\";\n```\n"];
        yield 'with lang' => ['$x = 1;', 'php', "```php\n\$x = 1;\n```\n"];
        yield 'empty code' => ['', '', "```\n\n```\n"];
        yield 'backticks in code' => ['x`y', '', "```\nx`y\n```\n"];
    }

    public function testListItemDefault(): void
    {
        $this->assertSame("- foo\n", $this->builder->listItem('foo'));
    }

    public function testListItemCustomPrefix(): void
    {
        $this->assertSame("* foo\n", $this->builder->listItem('foo', '*'));
    }

    public function testListItemWithSpecialChars(): void
    {
        $this->assertSame("- `a|b`\n", $this->builder->listItem('`a|b`'));
    }

    /**
     * @dataProvider provideKvListData
     */
    public function testKvList(array $pairs, string $expected): void
    {
        $this->assertSame($expected, $this->builder->kvList($pairs));
    }

    public static function provideKvListData(): iterable
    {
        yield 'basic' => [['key' => 'value'], "- **key:** value\n"];
        yield 'multiple' => [['a' => '1', 'b' => '2'], "- **a:** 1\n- **b:** 2\n"];
        yield 'empty' => [[], ''];
    }

    public function testSectionWithContent(): void
    {
        $result = $this->builder->section('Title', 2, "content\n");
        $this->assertSame("## Title\n\ncontent\n\n", $result);
    }

    public function testSectionEmptyContent(): void
    {
        $this->assertSame('', $this->builder->section('Title', 2, ''));
    }

    /**
     * @dataProvider providePropertiesTableData
     */
    public function testPropertiesTable(array $properties, string $expected): void
    {
        $this->assertSame($expected, $this->builder->propertiesTable($properties));
    }

    public static function providePropertiesTableData(): iterable
    {
        yield 'basic' => [
            [['name' => 'foo', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false]],
            "|Property|Visibility|Type|Default|\n|---|---|---|---|\n|`\$foo`|public|string|—|\n",
        ];

        yield 'with default' => [
            [['name' => 'count', 'visibility' => 'private', 'type' => 'int', 'defaultValue' => '0', 'isStatic' => false, 'isReadonly' => false]],
            "|Property|Visibility|Type|Default|\n|---|---|---|---|\n|`\$count`|private|int|`0`|\n",
        ];

        yield 'static' => [
            [['name' => 'cache', 'visibility' => 'protected', 'type' => 'array', 'defaultValue' => null, 'isStatic' => true, 'isReadonly' => false]],
            "|Property|Visibility|Type|Default|\n|---|---|---|---|\n|static `\$cache`|protected|array|—|\n",
        ];

        yield 'readonly' => [
            [['name' => 'id', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => true]],
            "|Property|Visibility|Type|Default|\n|---|---|---|---|\n|readonly `\$id`|public|string|—|\n",
        ];

        yield 'without type' => [
            [['name' => 'mixed', 'visibility' => 'public', 'type' => 'mixed', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false]],
            "|Property|Visibility|Type|Default|\n|---|---|---|---|\n|`\$mixed`|public|mixed|—|\n",
        ];

        yield 'default with pipe' => [
            [['name' => 'p', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => "a|b", 'isStatic' => false, 'isReadonly' => false]],
            "|Property|Visibility|Type|Default|\n|---|---|---|---|\n|`\$p`|public|string|`a\\|b`|\n",
        ];

        yield 'default with backtick' => [
            [['name' => 'q', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => "a`b", 'isStatic' => false, 'isReadonly' => false]],
            "|Property|Visibility|Type|Default|\n|---|---|---|---|\n|`\$q`|public|string|``a`b``|\n",
        ];

        yield 'empty' => [[], "|Property|Visibility|Type|Default|\n|---|---|---|---|\n"];
    }

    public function testPropertiesTableDeterministic(): void
    {
        $p = [['name' => 'x', 'visibility' => 'public', 'type' => 'int', 'defaultValue' => '1', 'isStatic' => false, 'isReadonly' => false]];
        $first = $this->builder->propertiesTable($p);
        $second = $this->builder->propertiesTable($p);
        $this->assertSame($first, $second);
    }

    /**
     * @dataProvider provideMethodSignatureData
     */
    public function testMethodSignature(array $method, string $expected): void
    {
        $this->assertSame($expected, $this->builder->methodSignature($method));
    }

    public static function provideMethodSignatureData(): iterable
    {
        yield 'no params no return' => [
            ['name' => 'foo', 'visibility' => 'public', 'parameters' => []],
            'public function foo()',
        ];

        yield 'with params' => [
            ['name' => 'bar', 'visibility' => 'public', 'parameters' => [['name' => 'x', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false]]],
            'public function bar(int $x)',
        ];

        yield 'with return type' => [
            ['name' => 'baz', 'visibility' => 'public', 'parameters' => [], 'returnType' => 'string'],
            'public function baz(): string',
        ];

        yield 'no visibility' => [
            ['name' => 'x', 'visibility' => '', 'parameters' => []],
            'function x()',
        ];

        yield 'variadic' => [
            ['name' => 'f', 'visibility' => 'public', 'parameters' => [['name' => 'args', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => true, 'isPassedByReference' => false]]],
            'public function f(string ...$args)',
        ];

        yield 'by reference' => [
            ['name' => 'f', 'visibility' => 'public', 'parameters' => [['name' => 'ref', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => true]]],
            'public function f(int &$ref)',
        ];

        yield 'with default' => [
            ['name' => 'f', 'visibility' => 'public', 'parameters' => [['name' => 'flag', 'type' => 'bool', 'defaultValue' => 'true', 'isVariadic' => false, 'isPassedByReference' => false]]],
            'public function f(bool $flag = true)',
        ];
    }

    /**
     * @dataProvider provideParameterStringData
     */
    public function testParameterString(array $param, string $expected): void
    {
        $this->assertSame($expected, $this->builder->parameterString($param));
    }

    public static function provideParameterStringData(): iterable
    {
        yield 'simple' => [['name' => 'x', 'type' => 'mixed', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false], 'mixed $x'];
        yield 'typed' => [['name' => 'x', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false], 'int $x'];
        yield 'nullable' => [['name' => 'x', 'type' => '?string', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false], '?string $x'];
        yield 'variadic' => [['name' => 'items', 'type' => 'array', 'defaultValue' => null, 'isVariadic' => true, 'isPassedByReference' => false], 'array ...$items'];
        yield 'by ref' => [['name' => 'out', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => true], 'int &$out'];
        yield 'default value' => [['name' => 'age', 'type' => 'int', 'defaultValue' => '0', 'isVariadic' => false, 'isPassedByReference' => false], 'int $age = 0'];
        yield 'all options' => [['name' => 'x', 'type' => 'string', 'defaultValue' => "'hi'", 'isVariadic' => false, 'isPassedByReference' => false], "string \$x = 'hi'"];
    }

    /**
     * @dataProvider provideConstantsTableData
     */
    public function testConstantsTable(array $constants, string $expected): void
    {
        $this->assertSame($expected, $this->builder->constantsTable($constants));
    }

    public static function provideConstantsTableData(): iterable
    {
        yield 'basic' => [
            [['name' => 'MAX', 'visibility' => 'public', 'type' => 'int', 'value' => '100']],
            "|Constant|Visibility|Type|Value|\n|---|---|---|---|\n|`MAX`|public|int|`100`|\n",
        ];

        yield 'no type' => [
            [['name' => 'NAME', 'visibility' => 'public', 'type' => null, 'value' => '"test"']],
            "|Constant|Visibility|Type|Value|\n|---|---|---|---|\n|`NAME`|public|—|`\"test\"`|\n",
        ];

        yield 'value with backtick' => [
            [['name' => 'S', 'visibility' => 'public', 'type' => 'string', 'value' => "a`b"]],
            "|Constant|Visibility|Type|Value|\n|---|---|---|---|\n|`S`|public|string|``a`b``|\n",
        ];

        yield 'empty' => [[], "|Constant|Visibility|Type|Value|\n|---|---|---|---|\n"];
    }

    public function testMethodsList(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $methods = [
            ['name' => 'foo', 'visibility' => 'public', 'isStatic' => false, 'isAbstract' => false, 'parameters' => [], 'returnType' => null],
            ['name' => 'bar', 'visibility' => 'protected', 'isStatic' => false, 'isAbstract' => false, 'parameters' => [['name' => 'x', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false]], 'returnType' => null],
        ];
        $expected = "- `public function foo(``)`\n- `protected function bar(``int`` \$x``)`\n";
        $this->assertSame($expected, $this->builder->methodsList($methods, $noLink));
    }

    public function testMethodsListEmpty(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $this->assertSame('', $this->builder->methodsList([], $noLink));
    }

    public function testMethodsListDeterministic(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $m = [['name' => 'f', 'visibility' => 'public', 'isStatic' => false, 'isAbstract' => false, 'parameters' => [], 'returnType' => null]];
        $first = $this->builder->methodsList($m, $noLink);
        $second = $this->builder->methodsList($m, $noLink);
        $this->assertSame($first, $second);
    }

    public function testMethodsListWithLinkableType(): void
    {
        $linkResolver = fn(string $fqn): ?string => $fqn === 'App\Entity\User' ? 'user.md' : null;
        $methods = [
            ['name' => 'getUser', 'visibility' => 'public', 'isStatic' => false, 'isAbstract' => false, 'parameters' => [['name' => 'id', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false]], 'returnType' => 'App\Entity\User'],
        ];
        $result = $this->builder->methodsList($methods, $linkResolver);
        $this->assertStringContainsString('[App\Entity\User](user.md)', $result);
        $this->assertStringContainsString('`int`', $result);
    }

    public function testClassList(): void
    {
        $classes = ['App\Foo', 'App\Bar'];
        $expected = "- `App\Foo`\n- `App\Bar`\n";
        $this->assertSame($expected, $this->builder->classList($classes));
    }

    public function testClassListEmpty(): void
    {
        $this->assertSame('', $this->builder->classList([]));
    }

    public function testItemList(): void
    {
        $items = ['alpha', 'beta'];
        $expected = "- `alpha`\n- `beta`\n";
        $this->assertSame($expected, $this->builder->itemList($items));
    }

    public function testItemListEmpty(): void
    {
        $this->assertSame('', $this->builder->itemList([]));
    }

    public function testItemListWithBackticks(): void
    {
        $items = ['a`b', 'c``d', '``e'];
        $expected = "- ``a`b``\n- ```c``d```\n- ``` ``e ```\n";
        $this->assertSame($expected, $this->builder->itemList($items));
    }

    public function testItemListDeterministic(): void
    {
        $items = ['x', 'y'];
        $first = $this->builder->itemList($items);
        $second = $this->builder->itemList($items);
        $this->assertSame($first, $second);
    }

    /**
     * @dataProvider provideInlineCodeEdgeCases
     */
    public function testInlineCodeViaItemList(string $value, string $expected): void
    {
        $this->assertSame("- $expected\n", $this->builder->itemList([$value]));
    }

    public static function provideInlineCodeEdgeCases(): iterable
    {
        yield 'plain' => ['hello', '`hello`'];
        yield 'single backtick' => ['a`b', '``a`b``'];
        yield 'leading backtick' => ['`a', '`` `a ``'];
        yield 'trailing backtick' => ['a`', '`` a` ``'];
        yield 'two consecutive' => ['a``b', '```a``b```'];
        yield 'three consecutive' => ['a```b', '````a```b````'];
        yield 'only backticks' => ['``', '``` `` ```'];
        yield 'backtick at both ends' => ['`x`', '`` `x` ``'];
    }

    public function testCreatesSectionEmpty(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $this->assertSame('', $this->builder->createsSection([], $noLink));
    }

    public function testCreatesSectionRendersBasicFormat(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $creates = [
            'build' => ['\App\Entity\User'],
        ];
        $expected = "- `build`: `\\App\\Entity\\User`\n";
        $this->assertSame($expected, $this->builder->createsSection($creates, $noLink));
    }

    public function testCreatesSectionRendersMultipleMethods(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $creates = [
            'build' => ['\App\Entity\User'],
            'setup' => ['\App\Config\Config'],
        ];
        $result = $this->builder->createsSection($creates, $noLink);
        $this->assertStringContainsString('`build`', $result);
        $this->assertStringContainsString('`setup`', $result);
        $this->assertStringContainsString('`\\App\\Entity\\User`', $result);
        $this->assertStringContainsString('`\\App\\Config\\Config`', $result);
    }

    public function testCreatesSectionWithLinkableType(): void
    {
        $linkResolver = fn(string $fqn): ?string => $fqn === '\App\Entity\User' ? 'user.md' : null;
        $creates = [
            'build' => ['\App\Entity\User'],
        ];
        $result = $this->builder->createsSection($creates, $linkResolver);
        $this->assertStringContainsString('[\\App\\Entity\\User](user.md)', $result);
        $this->assertStringContainsString('`build`', $result);
    }

    public function testCreatesSectionDeterministic(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $creates = [
            'foo' => ['\App\A', '\App\B'],
            'bar' => ['\App\C'],
        ];
        $first = $this->builder->createsSection($creates, $noLink);
        $second = $this->builder->createsSection($creates, $noLink);
        $this->assertSame($first, $second);
    }

    public function testUsedBySectionWithLinks(): void
    {
        $links = ['[App\Service\UserService](UserService.md)'];
        $expected = "### Used By\n\n- [App\\Service\\UserService](UserService.md)\n\n";
        $this->assertSame($expected, $this->builder->usedBySection($links));
    }

    public function testUsedBySectionEmpty(): void
    {
        $this->assertSame('', $this->builder->usedBySection([]));
    }

    public function testUsedBySectionMultipleLinks(): void
    {
        $links = [
            '[App\Service\AdminService](AdminService.md)',
            '[App\Service\UserService](UserService.md)',
        ];
        $result = $this->builder->usedBySection($links);
        $this->assertStringContainsString('[App\\Service\\AdminService](AdminService.md)', $result);
        $this->assertStringContainsString('[App\\Service\\UserService](UserService.md)', $result);
    }

    public function testUsedBySectionDeterministic(): void
    {
        $links = [
            '[App\Service\AdminService](AdminService.md)',
            '[App\Service\UserService](UserService.md)',
        ];
        $first = $this->builder->usedBySection($links);
        $second = $this->builder->usedBySection($links);
        $this->assertSame($first, $second);
    }

    public function testUsedBySectionSortingNotApplied(): void
    {
        $linksUnsorted = [
            '[B](B.md)',
            '[A](A.md)',
        ];
        $result = $this->builder->usedBySection($linksUnsorted);
        $expectedPosB = strpos($result, 'B](B.md)');
        $expectedPosA = strpos($result, 'A](A.md)');
        $this->assertTrue($expectedPosB < $expectedPosA, 'List preserves input order');
    }

    /**
     * @dataProvider provideRenderTypeData
     */
    public function testRenderType(string $type, callable $linkResolver, string $expected): void
    {
        $this->assertSame($expected, $this->builder->renderType($type, $linkResolver));
    }

    public static function provideRenderTypeData(): iterable
    {
        $noLink = fn(string $fqn): ?string => null;
        $linkSome = fn(string $fqn): ?string => $fqn === 'App\Entity\User' ? 'user.md' : null;

        yield 'primitive' => ['string', $noLink, '`string`'];
        yield 'nullable primitive' => ['?int', $noLink, '`?int`'];
        yield 'project entity' => ['App\Entity\User', $linkSome, '[App\Entity\User](user.md)'];
        yield 'nullable project entity' => ['?App\Entity\User', $linkSome, '?[App\Entity\User](user.md)'];
        yield 'non-project entity' => ['SomeVendor\Class', $noLink, '`SomeVendor\Class`'];
        yield 'union all primitives' => ['string|int', $noLink, '`string`|`int`'];
        yield 'union mixed' => ['App\Entity\User|string', $linkSome, '[App\Entity\User](user.md)|`string`'];
        yield 'intersection' => ['Countable&Stringable', $noLink, '`Countable`&`Stringable`'];
        yield 'empty string' => ['', $noLink, ''];
    }

    /**
     * @dataProvider providePropertiesListData
     */
    public function testPropertiesList(array $properties, callable $linkResolver, string $expected): void
    {
        $this->assertSame($expected, $this->builder->propertiesList($properties, $linkResolver));
    }

    public static function providePropertiesListData(): iterable
    {
        $noLink = fn(string $fqn): ?string => null;
        $linkSome = fn(string $fqn): ?string => $fqn === 'App\Entity\UuidInterface' ? 'uuid-interface.md' : null;

        yield 'basic' => [
            [['name' => 'foo', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false]],
            $noLink,
            "- `public` `string` `\$foo`\n",
        ];

        yield 'with default' => [
            [['name' => 'count', 'visibility' => 'private', 'type' => 'int', 'defaultValue' => '0', 'isStatic' => false, 'isReadonly' => false]],
            $noLink,
            "- `private` `int` `\$count = 0`\n",
        ];

        yield 'static' => [
            [['name' => 'cache', 'visibility' => 'protected', 'type' => 'array', 'defaultValue' => null, 'isStatic' => true, 'isReadonly' => false]],
            $noLink,
            "- `protected` static `array` `\$cache`\n",
        ];

        yield 'readonly' => [
            [['name' => 'id', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => true]],
            $noLink,
            "- `public` readonly `string` `\$id`\n",
        ];

        yield 'project entity type' => [
            [['name' => 'uuid', 'visibility' => 'public', 'type' => 'App\Entity\UuidInterface', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false]],
            $linkSome,
            "- `public` [App\Entity\UuidInterface](uuid-interface.md) `\$uuid`\n",
        ];

        yield 'without type' => [
            [['name' => 'mixed', 'visibility' => 'public', 'type' => 'mixed', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false]],
            $noLink,
            "- `public` `mixed` `\$mixed`\n",
        ];

        yield 'empty' => [[], $noLink, ''];
    }

    public function testPropertiesListDeterministic(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $p = [['name' => 'x', 'visibility' => 'public', 'type' => 'int', 'defaultValue' => '1', 'isStatic' => false, 'isReadonly' => false]];
        $first = $this->builder->propertiesList($p, $noLink);
        $second = $this->builder->propertiesList($p, $noLink);
        $this->assertSame($first, $second);
    }

    /**
     * @dataProvider provideDeclarationLineData
     */
    public function testDeclarationLine(string $typeLabel, ?string $parentFqn, ?string $parentLink, array $interfaceFqns, array $interfaceLinks, ?string $backingType, string $expected): void
    {
        $this->assertSame($expected, $this->builder->declarationLine($typeLabel, $parentFqn, $parentLink, $interfaceFqns, $interfaceLinks, $backingType));
    }

    public static function provideDeclarationLineData(): iterable
    {
        yield 'plain class' => ['class', null, null, [], [], null, "`class`\n"];

        yield 'with parent' => ['final class', 'App\Abstracts\Base', null, [], [], null, "`final class` extends `App\\Abstracts\\Base`\n"];

        yield 'with interfaces' => ['class', null, null, ['App\Contracts\A'], ['a.md'], null, "`class` implements [App\\Contracts\\A](a.md)\n"];

        yield 'full' => ['final class', 'App\Abstracts\Base', 'base.md', ['App\Contracts\A'], ['a.md'], null, "`final class` extends [App\\Abstracts\\Base](base.md) implements [App\\Contracts\\A](a.md)\n"];

        yield 'backed enum' => ['backed enum', null, null, [], [], 'int', "`backed enum` of `int`\n"];

        yield 'no inheritance for interface' => ['interface', null, null, [], [], null, "`interface`\n"];
    }

    public function testOverallDeterminism(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $entities = [
            ['name' => 'foo', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false],
        ];
        $constants = [
            ['name' => 'X', 'visibility' => 'public', 'type' => 'int', 'value' => '1'],
        ];
        $methods = [
            ['name' => 'f', 'visibility' => 'public', 'isStatic' => false, 'isAbstract' => false, 'parameters' => [], 'returnType' => null],
        ];
        $classes = ['A', 'B'];
        $items = ['x', 'y'];

        $block = fn(): string =>
            $this->builder->propertiesTable($entities)
            . $this->builder->constantsTable($constants)
            . $this->builder->methodsList($methods, $noLink)
            . $this->builder->classList($classes)
            . $this->builder->itemList($items)
            . $this->builder->frontmatter(['k' => 'v'])
            . $this->builder->table(['H'], [['v']])
            . $this->builder->codeBlock('c', 'php')
            . $this->builder->kvList(['k' => 'v']);

        $this->assertSame($block(), $block(), 'Full output must be deterministic');
    }

    public function testTableSeparatorColumnCount(): void
    {
        $headers = ['A', 'B', 'C', 'D'];
        $rows = [['1', '2', '3', '4']];
        $result = $this->builder->table($headers, $rows);
        $this->assertStringContainsString('|---|---|---|---|', $result);
    }

    public function testMethodSignatureMultipleParametersCommaSeparated(): void
    {
        $method = [
            'name' => 'test',
            'visibility' => 'public',
            'parameters' => [
                ['name' => 'a', 'type' => 'int', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                ['name' => 'b', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
                ['name' => 'c', 'type' => 'bool', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false],
            ],
        ];
        $result = $this->builder->methodSignature($method);
        $this->assertSame('public function test(int $a, string $b, bool $c)', $result);
    }

    public function testRenderTypeStripsLeadingBackslash(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $result = $this->builder->renderType('\\App\\Entity\\User', $noLink);
        $this->assertSame('`App\\Entity\\User`', $result);
    }

    public function testRenderTypeNullableStripsLeadingBackslash(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $result = $this->builder->renderType('?\\App\\Entity\\User', $noLink);
        $this->assertSame('`?App\\Entity\\User`', $result);
    }

    public function testCreatesSectionAccumulatesMultipleMethods(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $creates = [
            'method1' => ['\\App\\A'],
            'method2' => ['\\App\\B'],
            'method3' => ['\\App\\C'],
        ];
        $result = $this->builder->createsSection($creates, $noLink);
        $this->assertStringContainsString('`method1`', $result);
        $this->assertStringContainsString('`method2`', $result);
        $this->assertStringContainsString('`method3`', $result);
        $lines = explode("\n", trim($result));
        $this->assertCount(3, $lines);
    }

    public function testCreatesSectionAccumulatesMultipleFqcnsPerMethod(): void
    {
        $noLink = fn(string $fqn): ?string => null;
        $creates = [
            'build' => ['\\App\\A', '\\App\\B', '\\App\\C'],
        ];
        $result = $this->builder->createsSection($creates, $noLink);
        $this->assertStringContainsString('`\\App\\A`', $result);
        $this->assertStringContainsString('`\\App\\B`', $result);
        $this->assertStringContainsString('`\\App\\C`', $result);
    }

    public function testUsedBySectionAccumulatesMultipleLinks(): void
    {
        $links = [
            '[App\\A](A.md)',
            '[App\\B](B.md)',
            '[App\\C](C.md)',
        ];
        $result = $this->builder->usedBySection($links);
        $this->assertStringContainsString('[App\\A](A.md)', $result);
        $this->assertStringContainsString('[App\\B](B.md)', $result);
        $this->assertStringContainsString('[App\\C](C.md)', $result);
    }

    public function testDeclarationLineWithLeadingBackslashInParent(): void
    {
        $result = $this->builder->declarationLine('class', '\\App\\Base', null, [], [], null);
        $this->assertSame("`class` extends `\\App\\Base`\n", $result);
    }

    public function testDeclarationLineWithLeadingBackslashInInterfaces(): void
    {
        $result = $this->builder->declarationLine('class', null, null, ['\\App\\Contract'], ['contract.md'], null);
        $this->assertSame("`class` implements [\\App\\Contract](contract.md)\n", $result);
    }
}

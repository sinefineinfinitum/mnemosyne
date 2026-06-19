<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\Extractor\AstHelper;

final class AstHelperTest extends TestCase
{
    private AstHelper $astHelper;

    protected function setUp(): void
    {
        $this->astHelper = new AstHelper();
    }

    private function parseFirstClassLike(string $code): ClassLike
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $finder = new class extends NodeVisitorAbstract {
            public ?ClassLike $classLike = null;
            public function enterNode(Node $node)
            {
                if ($node instanceof ClassLike && $this->classLike === null) {
                    $this->classLike = $node;
                }
                return null;
            }
        };
        $traverser->addVisitor($finder);
        $traverser->traverse($ast);

        return $finder->classLike;
    }

    public function testResolveFqnWithNamespace(): void
    {
        $this->assertSame('App\\Service\\Foo', $this->astHelper->resolveFqn('App\\Service', 'Foo'));
    }

    public function testResolveFqnWithoutNamespace(): void
    {
        $this->assertSame('Foo', $this->astHelper->resolveFqn('', 'Foo'));
    }

    // ---- extractConstants ----

    public function testExtractConstantsWithValues(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const PUB = 1;
                protected const PROT = "str";
                private const PRIV = null;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);

        $this->assertCount(3, $constants);
        $this->assertSame('PUB', $constants[0]['name']);
        $this->assertSame('public', $constants[0]['visibility']);
        $this->assertSame('1', $constants[0]['value']);
        $this->assertSame('PROT', $constants[1]['name']);
        $this->assertSame('protected', $constants[1]['visibility']);
        $this->assertSame("'str'", $constants[1]['value']);
        $this->assertSame('PRIV', $constants[2]['name']);
        $this->assertSame('private', $constants[2]['visibility']);
    }

    public function testExtractConstantsWithType(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const int BAR = 42;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertCount(1, $constants);
        $this->assertSame('int', $constants[0]['type']);
    }

    public function testExtractConstantsWithoutType(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const BAR = 1;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertCount(1, $constants);
        $this->assertNull($constants[0]['type']);
    }

    public function testExtractConstantsEmpty(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {}
        '
        );
        $this->assertSame([], $this->astHelper->extractConstants($class));
    }

    // ---- extractMethods ----

    public function testExtractMethodsWithParams(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(int $x, string $y = "hi"): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertCount(1, $methods);
        $this->assertSame('bar', $methods[0]['name']);
        $this->assertSame('public', $methods[0]['visibility']);
        $this->assertCount(2, $methods[0]['parameters']);
        $this->assertSame('x', $methods[0]['parameters'][0]['name']);
        $this->assertSame('int', $methods[0]['parameters'][0]['type']);
        $this->assertNull($methods[0]['parameters'][0]['defaultValue']);
        $this->assertSame('y', $methods[0]['parameters'][1]['name']);
        $this->assertSame('string', $methods[0]['parameters'][1]['type']);
        $this->assertSame("'hi'", $methods[0]['parameters'][1]['defaultValue']);
        $this->assertSame('void', $methods[0]['returnType']);
    }

    public function testExtractMethodsVariadicAndByRef(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(string ...$items): void {}
                public function baz(int &$ref): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertCount(2, $methods);

        $barParams = $methods[0]['parameters'];
        $this->assertTrue($barParams[0]['isVariadic']);
        $this->assertFalse($barParams[0]['isPassedByReference']);

        $bazParams = $methods[1]['parameters'];
        $this->assertFalse($bazParams[0]['isVariadic']);
        $this->assertTrue($bazParams[0]['isPassedByReference']);
    }

    public function testExtractMethodsModifiers(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            abstract class Foo {
                abstract public function bar(): void;
                final public function baz(): void {}
                public static function qux(): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertCount(3, $methods);

        $this->assertFalse($methods[0]['isStatic']);
        $this->assertTrue($methods[0]['isAbstract']);
        $this->assertFalse($methods[0]['isFinal']);

        $this->assertFalse($methods[1]['isStatic']);
        $this->assertFalse($methods[1]['isAbstract']);
        $this->assertTrue($methods[1]['isFinal']);

        $this->assertTrue($methods[2]['isStatic']);
        $this->assertFalse($methods[2]['isAbstract']);
        $this->assertFalse($methods[2]['isFinal']);
    }

    public function testExtractMethodsWithoutReturnType(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar() {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertNull($methods[0]['returnType']);
    }

    public function testExtractMethodsWithoutParams(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertCount(1, $methods);
        $this->assertSame([], $methods[0]['parameters']);
    }

    public function testExtractMethodsEmpty(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {}
        '
        );
        $this->assertSame([], $this->astHelper->extractMethods($class));
    }

    // ---- extractProperties ----

    public function testExtractProperties(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public int $id;
                protected string $name = "test";
                private static bool $active = true;
                public readonly float $rate;
            }
        '
        );
        $properties = $this->astHelper->extractProperties($class);
        $this->assertCount(4, $properties);

        $this->assertSame('id', $properties[0]['name']);
        $this->assertSame('public', $properties[0]['visibility']);
        $this->assertSame('int', $properties[0]['type']);
        $this->assertNull($properties[0]['defaultValue']);
        $this->assertFalse($properties[0]['isStatic']);
        $this->assertFalse($properties[0]['isReadonly']);

        $this->assertSame('name', $properties[1]['name']);
        $this->assertSame('protected', $properties[1]['visibility']);
        $this->assertSame('string', $properties[1]['type']);
        $this->assertSame("'test'", $properties[1]['defaultValue']);

        $this->assertSame('active', $properties[2]['name']);
        $this->assertSame('private', $properties[2]['visibility']);
        $this->assertSame('bool', $properties[2]['type']);
        $this->assertTrue($properties[2]['isStatic']);

        $this->assertSame('rate', $properties[3]['name']);
        $this->assertSame('public', $properties[3]['visibility']);
        $this->assertSame('float', $properties[3]['type']);
        $this->assertTrue($properties[3]['isReadonly']);
    }

    public function testExtractPropertiesEmpty(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {}
        '
        );
        $this->assertSame([], $this->astHelper->extractProperties($class));
    }

    public function testExtractPropertiesPromotedConstructor(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function __construct(
                    public int $id,
                    protected string $name = "default",
                    private ?float $rate = null,
                ) {}
            }
        '
        );
        $properties = $this->astHelper->extractProperties($class);
        $this->assertCount(3, $properties);

        $this->assertSame('id', $properties[0]['name']);
        $this->assertSame('public', $properties[0]['visibility']);
        $this->assertSame('int', $properties[0]['type']);
        $this->assertFalse($properties[0]['isStatic']);
        $this->assertFalse($properties[0]['isReadonly']);

        $this->assertSame('name', $properties[1]['name']);
        $this->assertSame('protected', $properties[1]['visibility']);
        $this->assertSame('string', $properties[1]['type']);
        $this->assertSame("'default'", $properties[1]['defaultValue']);

        $this->assertSame('rate', $properties[2]['name']);
        $this->assertSame('private', $properties[2]['visibility']);
        $this->assertSame('?float', $properties[2]['type']);
    }

    public function testExtractPropertiesPromotedConstructorReadonly(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function __construct(
                    public readonly int $id,
                ) {}
            }
        '
        );
        $properties = $this->astHelper->extractProperties($class);
        $this->assertCount(1, $properties);
        $this->assertTrue($properties[0]['isReadonly']);
    }

    public function testExtractPropertiesPromotedIgnoresNonConstructor(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(
                    public int $id,
                ) {}
            }
        '
        );
        $properties = $this->astHelper->extractProperties($class);
        $this->assertSame([], $properties);
    }

    public function testExtractPropertiesFromInterface(): void
    {
        $iface = $this->parseFirstClassLike(
            '
            namespace App;
            interface Foo {
                public const int BAR = 1;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($iface);
        $this->assertCount(1, $constants);
        $this->assertSame('BAR', $constants[0]['name']);

        $this->assertSame([], $this->astHelper->extractProperties($iface));
    }

    // ---- resolveVisibility ----

    public function testResolveVisibilityPrivate(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                private function bar(): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('private', $methods[0]['visibility']);
    }

    public function testResolveVisibilityProtected(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                protected function bar(): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('protected', $methods[0]['visibility']);
    }

    public function testResolveVisibilityPublic(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('public', $methods[0]['visibility']);
    }

    // ---- resolveType ----

    public function testResolveTypeNullable(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(?int $x): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('?int', $methods[0]['parameters'][0]['type']);
    }

    public function testResolveTypeUnion(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(int|string|null $x): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('int|string|null', $methods[0]['parameters'][0]['type']);
    }

    public function testResolveTypeIntersection(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(\ArrayAccess&\Countable $x): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('\\ArrayAccess&\\Countable', $methods[0]['parameters'][0]['type']);
    }

    public function testResolveTypeName(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(\App\Entity\User $x): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('\\App\\Entity\\User', $methods[0]['parameters'][0]['type']);
    }

    public function testResolveTypeIdentifier(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(int $x): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('int', $methods[0]['parameters'][0]['type']);
    }

    public function testResolveTypeNullableClassType(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public function bar(?\App\Entity\User $x): void {}
            }
        '
        );
        $methods = $this->astHelper->extractMethods($class);
        $this->assertSame('?\\App\\Entity\\User', $methods[0]['parameters'][0]['type']);
    }

    // ---- resolveDefault ----

    public function testResolveDefaultConstFetch(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const BAR = PHP_INT_MAX;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertSame('PHP_INT_MAX', $constants[0]['value']);
    }

    public function testResolveDefaultString(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const BAR = "hello";
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertSame("'hello'", $constants[0]['value']);
    }

    public function testResolveDefaultInt(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const BAR = 42;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertSame('42', $constants[0]['value']);
    }

    public function testResolveDefaultFloat(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const BAR = 3.14;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertSame('3.14', $constants[0]['value']);
    }

    public function testResolveDefaultArray(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const BAR = [];
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertSame('[]', $constants[0]['value']);
    }

    public function testResolveDefaultUnaryMinus(): void
    {
        $class = $this->parseFirstClassLike(
            '
            namespace App;
            class Foo {
                public const BAR = -42;
            }
        '
        );
        $constants = $this->astHelper->extractConstants($class);
        $this->assertSame('-42', $constants[0]['value']);
    }
}

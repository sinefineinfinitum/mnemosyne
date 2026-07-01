<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\Extractor\AstHelper;
use SineFine\Mnemosyne\Analyzer\Extractor\ClassExtractor;

final class ClassExtractorTest extends TestCase
{
    private ClassExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ClassExtractor('App', new AstHelper());
    }

    private function extractFirstClass(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $finder = new class extends NodeVisitorAbstract {
            public ?Node\Stmt\Class_ $class = null;
            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_ && $this->class === null) {
                    $this->class = $node;
                }
                return null;
            }
        };
        $traverser->addVisitor($finder);
        $traverser->traverse($ast);

        return $this->extractor->extract($finder->class);
    }

    public function testSupportsClass(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php class Foo {}');
        $this->assertTrue($this->extractor->supports($ast[0]));
    }

    public function testSupportsAnonymousClassReturnsFalse(): void
    {
        $anon = new Node\Stmt\Class_(null);
        $this->assertFalse($this->extractor->supports($anon));
    }

    public function testExtractSimpleClass(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            class Foo {}
        '
        );
        $this->assertSame('App\Foo', $result['fqn']);
        $this->assertSame('class', $result['type']);
        $this->assertSame([], $result['modifiers']);
        $this->assertNull($result['parentClass']);
        $this->assertSame([], $result['interfaces']);
        $this->assertSame([], $result['traits']);
    }

    public function testExtractClassWithParent(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            class Foo extends \App\Base {}
        '
        );
        $this->assertSame('App\Base', $result['parentClass']);
    }

    public function testExtractClassWithInterfaces(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            class Foo implements \App\A, \App\B {}
        '
        );
        $this->assertSame(['App\A', 'App\B'], $result['interfaces']);
    }

    public function testExtractClassWithTraits(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            class Foo {
                use \App\LoggableTrait;
                use \App\CacheTrait;
            }
        '
        );
        $this->assertSame(['App\CacheTrait', 'App\LoggableTrait'], $result['traits']);
    }

    public function testExtractClassWithModifiers(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            abstract class Foo {}
        '
        );
        $this->assertContains('abstract', $result['modifiers']);
    }

    public function testExtractClassWithConstants(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            class Foo {
                public const BAR = 1;
                protected const BAZ = 2;
            }
        '
        );
        $this->assertCount(2, $result['constants']);
        $this->assertSame('BAR', $result['constants'][0]['name']);
        $this->assertSame('BAZ', $result['constants'][1]['name']);
    }

    public function testExtractClassWithProperties(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            class Foo {
                public int $id;
                protected string $name;
            }
        '
        );
        $this->assertCount(2, $result['properties']);
        $this->assertSame('id', $result['properties'][0]['name']);
        $this->assertSame('name', $result['properties'][1]['name']);
    }

    public function testExtractClassWithMethods(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            class Foo {
                public function bar(): void {}
                protected function baz(): void {}
            }
        '
        );
        $this->assertCount(2, $result['methods']);
        $this->assertSame('bar', $result['methods'][0]['name']);
        $this->assertSame('baz', $result['methods'][1]['name']);
    }

    public function testExtractClassReadonly(): void
    {
        $result = $this->extractFirstClass(
            '
            namespace App;
            readonly class Foo {}
        '
        );
        $this->assertSame(['readonly'], $result['modifiers']);
    }

    public function testExtractClassNoNamespace(): void
    {
        $this->extractor = new ClassExtractor('', new AstHelper());
        $result = $this->extractFirstClass('class Foo {}');
        $this->assertSame('Foo', $result['fqn']);
    }
}

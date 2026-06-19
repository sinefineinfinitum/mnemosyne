<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\Extractor\AstHelper;
use SineFine\Ponymator\Analyzer\Extractor\InterfaceExtractor;

final class InterfaceExtractorTest extends TestCase
{
    private InterfaceExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new InterfaceExtractor('App', new AstHelper());
    }

    private function extractFirstInterface(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $finder = new class extends NodeVisitorAbstract {
            public ?Node\Stmt\Interface_ $iface = null;
            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Interface_ && $this->iface === null) {
                    $this->iface = $node;
                }
                return null;
            }
        };
        $traverser->addVisitor($finder);
        $traverser->traverse($ast);

        return $this->extractor->extract($finder->iface);
    }

    public function testSupportsInterface(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php interface Foo {}');
        $this->assertTrue($this->extractor->supports($ast[0]));
    }

    public function testSupportsClassReturnsFalse(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php class Foo {}');
        $this->assertFalse($this->extractor->supports($ast[0]));
    }

    public function testExtractSimpleInterface(): void
    {
        $result = $this->extractFirstInterface(
            '
            namespace App;
            interface Foo {}
        '
        );
        $this->assertSame('App\Foo', $result['fqn']);
        $this->assertSame('interface', $result['type']);
        $this->assertSame([], $result['modifiers']);
        $this->assertNull($result['parentClass']);
        $this->assertSame([], $result['interfaces']);
        $this->assertSame([], $result['properties']);
    }

    public function testExtractInterfaceWithExtends(): void
    {
        $result = $this->extractFirstInterface(
            '
            namespace App;
            interface Foo extends \App\Bar, \App\Baz {}
        '
        );
        $this->assertSame(['App\Bar', 'App\Baz'], $result['interfaces']);
    }

    public function testExtractInterfaceWithConstants(): void
    {
        $result = $this->extractFirstInterface(
            '
            namespace App;
            interface Foo {
                public const BAR = 1;
            }
        '
        );
        $this->assertCount(1, $result['constants']);
        $this->assertSame('BAR', $result['constants'][0]['name']);
    }

    public function testExtractInterfaceWithMethods(): void
    {
        $result = $this->extractFirstInterface(
            '
            namespace App;
            interface Foo {
                public function bar(): void;
            }
        '
        );
        $this->assertCount(1, $result['methods']);
        $this->assertSame('bar', $result['methods'][0]['name']);
    }

    public function testExtractInterfaceNoNamespace(): void
    {
        $this->extractor = new InterfaceExtractor('', new AstHelper());
        $result = $this->extractFirstInterface('interface Foo {}');
        $this->assertSame('Foo', $result['fqn']);
    }
}

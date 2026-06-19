<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\Extractor\AstHelper;
use SineFine\Ponymator\Analyzer\Extractor\TraitExtractor;

final class TraitExtractorTest extends TestCase
{
    private TraitExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new TraitExtractor('App', new AstHelper());
    }

    private function extractFirstTrait(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $finder = new class extends NodeVisitorAbstract {
            public ?Node\Stmt\Trait_ $trait = null;
            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Trait_ && $this->trait === null) {
                    $this->trait = $node;
                }
                return null;
            }
        };
        $traverser->addVisitor($finder);
        $traverser->traverse($ast);

        return $this->extractor->extract($finder->trait);
    }

    public function testSupportsTrait(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php trait Foo {}');
        $this->assertTrue($this->extractor->supports($ast[0]));
    }

    public function testSupportsClassReturnsFalse(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php class Foo {}');
        $this->assertFalse($this->extractor->supports($ast[0]));
    }

    public function testExtractSimpleTrait(): void
    {
        $result = $this->extractFirstTrait(
            '
            namespace App;
            trait Foo {}
        '
        );
        $this->assertSame('App\Foo', $result['fqn']);
        $this->assertSame('trait', $result['type']);
        $this->assertSame([], $result['modifiers']);
        $this->assertNull($result['parentClass']);
        $this->assertSame([], $result['interfaces']);
        $this->assertSame([], $result['traits']);
    }

    public function testExtractTraitWithUse(): void
    {
        $result = $this->extractFirstTrait(
            '
            namespace App;
            trait Foo {
                use \App\LoggableTrait;
                use \App\CacheTrait;
            }
        '
        );
        $this->assertSame(['App\CacheTrait', 'App\LoggableTrait'], $result['traits']);
    }

    public function testExtractTraitWithConstants(): void
    {
        $result = $this->extractFirstTrait(
            '
            namespace App;
            trait Foo {
                public const BAR = 1;
            }
        '
        );
        $this->assertCount(1, $result['constants']);
        $this->assertSame('BAR', $result['constants'][0]['name']);
    }

    public function testExtractTraitWithProperties(): void
    {
        $result = $this->extractFirstTrait(
            '
            namespace App;
            trait Foo {
                public int $id;
            }
        '
        );
        $this->assertCount(1, $result['properties']);
        $this->assertSame('id', $result['properties'][0]['name']);
    }

    public function testExtractTraitWithMethods(): void
    {
        $result = $this->extractFirstTrait(
            '
            namespace App;
            trait Foo {
                public function bar(): void {}
            }
        '
        );
        $this->assertCount(1, $result['methods']);
        $this->assertSame('bar', $result['methods'][0]['name']);
    }

    public function testExtractTraitNoNamespace(): void
    {
        $this->extractor = new TraitExtractor('', new AstHelper());
        $result = $this->extractFirstTrait('trait Foo {}');
        $this->assertSame('Foo', $result['fqn']);
    }
}

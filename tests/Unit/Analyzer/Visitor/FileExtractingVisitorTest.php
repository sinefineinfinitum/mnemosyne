<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Analyzer\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\Visitor\FileExtractingVisitor;

final class FileExtractingVisitorTest extends TestCase
{
    private FileExtractingVisitor $visitor;

    private NodeTraverser $traverser;

    protected function setUp(): void
    {
        $this->visitor = new FileExtractingVisitor();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->visitor);
    }

    private function traverseCode(string $code): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);
        $this->traverser->traverse($ast);
    }

    public function testCollectsGlobals(): void
    {
        $this->traverseCode(
            '
            $x = 1;
            $y = 2;
        '
        );
        $this->assertSame(['x', 'y'], $this->visitor->globals());
    }

    public function testSkipsFunctionScope(): void
    {
        $this->traverseCode(
            '
            $a = 1;
            function foo() {
                $b = 2;
            }
        '
        );
        $this->assertSame(['a'], $this->visitor->globals());
    }

    public function testSkipsClassScope(): void
    {
        $this->traverseCode(
            '
            $a = 1;
            class Foo {
                public $prop;
            }
        '
        );
        $this->assertSame(['a'], $this->visitor->globals());
    }

    public function testSkipsSuperGlobals(): void
    {
        $this->traverseCode(
            '
            $_GET;
            $_POST;
            $x = 1;
        '
        );
        $this->assertSame(['x'], $this->visitor->globals());
    }

    public function testEmptyWhenNoGlobals(): void
    {
        $this->traverseCode('class Foo {}');
        $this->assertSame([], $this->visitor->globals());
    }

    public function testDeduplicatesGlobals(): void
    {
        $this->traverseCode(
            '
            $x = 1;
            $x = 2;
        '
        );
        $this->assertCount(1, $this->visitor->globals());
    }
}

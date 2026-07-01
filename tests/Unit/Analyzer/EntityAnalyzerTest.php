<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Analyzer;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\EntityAnalyzer;

final class EntityAnalyzerTest extends TestCase
{
    private EntityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new EntityAnalyzer();
    }

    private function parseCode(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        return $parser->parse('<?php ' . $code);
    }

    public function testAnalyzeClass(): void
    {
        $ast = $this->parseCode(
            '
            namespace App;
            class Foo {
                public function bar(): void {}
            }
        '
        );
        $result = $this->analyzer->analyze($ast);
        $entities = $result->getEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('App\Foo', $entities[0]['fqn']);
        $this->assertSame('class', $entities[0]['type']);
        $this->assertCount(1, $entities[0]['methods']);
    }

    public function testAnalyzeInterface(): void
    {
        $ast = $this->parseCode(
            '
            namespace App;
            interface Foo {
                public function bar(): void;
            }
        '
        );
        $result = $this->analyzer->analyze($ast);
        $entities = $result->getEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('App\Foo', $entities[0]['fqn']);
        $this->assertSame('interface', $entities[0]['type']);
    }

    public function testAnalyzeTrait(): void
    {
        $ast = $this->parseCode(
            '
            namespace App;
            trait Foo {
                public function bar(): void {}
            }
        '
        );
        $result = $this->analyzer->analyze($ast);
        $entities = $result->getEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('App\Foo', $entities[0]['fqn']);
        $this->assertSame('trait', $entities[0]['type']);
    }

    public function testAnalyzeEmptyAst(): void
    {
        $result = $this->analyzer->analyze([]);
        $this->assertSame([], $result->getEntities());
        $this->assertSame([], $result->getDependencies());
    }

    public function testAnalyzeCollectsDependencies(): void
    {
        $ast = $this->parseCode(
            '
            namespace App;
            class Foo extends \App\Base implements \App\Contracts\Bar {
                public function run(\App\Entity\User $user): void {}
            }
        '
        );
        $result = $this->analyzer->analyze($ast);
        $deps = $result->getDependencies();
        $this->assertContains('\\App\\Base', $deps);
        $this->assertContains('\\App\\Contracts\\Bar', $deps);
        $this->assertContains('\\App\\Entity\\User', $deps);
    }

    public function testAnalyzeCollectsCreations(): void
    {
        $ast = $this->parseCode(
            '
            namespace App;
            class Foo {
                public function build(): void {
                    $obj = new \App\Entity\User();
                }
            }
        '
        );
        $result = $this->analyzer->analyze($ast);
        $creations = $result->getCreations();
        $this->assertArrayHasKey('App\Foo', $creations);
        $this->assertArrayHasKey('build', $creations['App\Foo']);
        $this->assertSame(['App\Entity\User'], $creations['App\Foo']['build']);
    }
}

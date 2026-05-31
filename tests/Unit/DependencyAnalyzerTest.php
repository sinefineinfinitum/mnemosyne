<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use SineFine\Ponymator\Analyzer\DependencyAnalyzer;

final class DependencyAnalyzerTest extends TestCase
{
    private DependencyAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new DependencyAnalyzer();
    }

    /**
     * @return string[]
     */
    private function extractDepsFromCode(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->traverse($ast);

        return $this->analyzer->extractDependencies($ast);
    }

    public function testSimpleParamType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(\App\Baz $baz): void {} }'
        );
        $this->assertSame(['\App\Baz'], $deps);
    }

    public function testBuiltinParamTypeNotAdded(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(int $i): void {} }'
        );
        $this->assertSame([], $deps);
    }

    public function testUnionParamType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(\App\Baz|\App\Qux $v): void {} }'
        );
        $this->assertSame(['\App\Baz', '\App\Qux'], $deps);
    }

    public function testUnionParamWithBuiltins(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(\App\Baz|int|null $v): void {} }'
        );
        $this->assertSame(['\App\Baz'], $deps);
    }

    public function testIntersectionParamType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(\App\Baz&\App\Qux $v): void {} }'
        );
        $this->assertSame(['\App\Baz', '\App\Qux'], $deps);
    }

    public function testNullableParamType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(?\App\Baz $v): void {} }'
        );
        $this->assertSame(['\App\Baz'], $deps);
    }

    public function testNullableBuiltinParamNotAdded(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(?int $v): void {} }'
        );
        $this->assertSame([], $deps);
    }

    public function testSimpleReturnType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(): \App\Baz {} }'
        );
        $this->assertSame(['\App\Baz'], $deps);
    }

    public function testUnionReturnType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(): \App\Baz|\App\Qux {} }'
        );
        $this->assertSame(['\App\Baz', '\App\Qux'], $deps);
    }

    public function testIntersectionReturnType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(): \App\Baz&\App\Qux {} }'
        );
        $this->assertSame(['\App\Baz', '\App\Qux'], $deps);
    }

    public function testNullableReturnType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(): ?\App\Baz {} }'
        );
        $this->assertSame(['\App\Baz'], $deps);
    }

    public function testNullableBuiltinReturnNotAdded(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public function bar(): ?string {} }'
        );
        $this->assertSame([], $deps);
    }

    public function testPropertyType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public \App\Baz $prop; }'
        );
        $this->assertSame(['\App\Baz'], $deps);
    }

    public function testPropertyUnionType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public \App\Baz|\App\Qux $prop; }'
        );
        $this->assertSame(['\App\Baz', '\App\Qux'], $deps);
    }

    public function testPropertyNullableType(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public ?\App\Baz $prop; }'
        );
        $this->assertSame(['\App\Baz'], $deps);
    }

    public function testPropertyBuiltinTypeNotAdded(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo { public int $prop; }'
        );
        $this->assertSame([], $deps);
    }

    public function testClassExtends(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo extends \App\Bar {}'
        );
        $this->assertSame(['\App\Bar'], $deps);
    }

    public function testClassImplements(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App; class Foo implements \App\Bar {}'
        );
        $this->assertSame(['\App\Bar'], $deps);
    }

    public function testUnionInParamAndReturnAndProperty(): void
    {
        $deps = $this->extractDepsFromCode(
            '<?php namespace App;
            class Foo {
                public \App\A $prop;
                public function bar(\App\B $p): \App\C {}
            }'
        );
        $this->assertSame(['\App\A', '\App\B', '\App\C'], $deps);
    }
}

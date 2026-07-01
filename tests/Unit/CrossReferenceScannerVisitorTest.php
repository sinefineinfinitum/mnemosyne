<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use SineFine\Mnemosyne\Analyzer\Visitor\CrossReferenceScannerVisitor;

final class CrossReferenceScannerVisitorTest extends TestCase
{
    private function scanCode(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $scanner = new CrossReferenceScannerVisitor();
        $traverser->addVisitor($scanner);
        $traverser->traverse($ast);

        return [
            'pairs' => $scanner->getPairs(),
            'fqns' => $scanner->getEntityFqns(),
        ];
    }

    public function testClassExtends(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo extends \App\Bar {}'
        );

        $this->assertSame(['App\Bar', 'App\Foo'], $result['pairs'][0]);
    }

    public function testClassImplements(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo implements \App\Bar {}'
        );

        $this->assertSame(['App\Bar', 'App\Foo'], $result['pairs'][0]);
    }

    public function testInterfaceExtends(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; interface Foo extends \App\Bar {}'
        );

        $this->assertSame(['App\Bar', 'App\Foo'], $result['pairs'][0]);
    }

    public function testTraitUse(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { use \App\LoggableTrait; }'
        );

        $this->assertSame(['App\LoggableTrait', 'App\Foo'], $result['pairs'][0]);
    }

    public function testParamType(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public function bar(\App\Baz $baz): void {} }'
        );

        $this->assertSame(['App\Baz', 'App\Foo'], $result['pairs'][0]);
    }

    public function testReturnType(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public function bar(): \App\Baz {} }'
        );

        $this->assertSame(['App\Baz', 'App\Foo'], $result['pairs'][0]);
    }

    public function testPropertyType(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public \App\Baz $prop; }'
        );

        $this->assertSame(['App\Baz', 'App\Foo'], $result['pairs'][0]);
    }

    public function testBuiltinTypesExcluded(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public function bar(int $i, string $s): void {} }'
        );

        $this->assertSame([], $result['pairs']);
    }

    public function testUnionTypeAllBuiltinsExcluded(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public function bar(int|string|null $v): void {} }'
        );

        $this->assertSame([], $result['pairs']);
    }

    public function testUnionTypeWithClass(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public function bar(\App\Baz|int|null $v): void {} }'
        );

        $this->assertSame(['App\Baz', 'App\Foo'], $result['pairs'][0]);
    }

    public function testNullableType(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public function bar(?\App\Baz $v): void {} }'
        );

        $this->assertSame(['App\Baz', 'App\Foo'], $result['pairs'][0]);
    }

    public function testEntityFqnsExtracted(): void
    {
        $result = $this->scanCode('<?php namespace App; class Foo {}');

        $this->assertSame(['App\Foo'], $result['fqns']);
    }

    public function testMultipleEntitiesInFile(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo {} class Bar {}'
        );

        $this->assertSame(['App\Foo', 'App\Bar'], $result['fqns']);
    }

    public function testInterfaceEntityFqn(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; interface ServiceInterface {}'
        );

        $this->assertSame(['App\ServiceInterface'], $result['fqns']);
    }

    public function testTraitEntityFqn(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; trait LoggableTrait {}'
        );

        $this->assertSame(['App\LoggableTrait'], $result['fqns']);
    }

    public function testEnumEntityFqn(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; enum Status {}'
        );

        $this->assertSame(['App\Status'], $result['fqns']);
    }

    public function testEnumImplements(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; enum Status: int implements \App\StatusInterface {}'
        );

        $this->assertSame(['App\StatusInterface', 'App\Status'], $result['pairs'][0]);
    }

    public function testMultipleReferencesInClass(): void
    {
        $result = $this->scanCode(
            '<?php namespace App;
            class Foo extends \App\Base implements \App\A, \App\B {
                public \App\C $prop;
                public function bar(\App\D $d): \App\E {}
                use \App\LoggableTrait;
            }'
        );

        $pairs = $result['pairs'];
        $this->assertCount(7, $pairs);
        $referenced = array_map(fn(array $p) => $p[0], $pairs);
        $this->assertContains('App\Base', $referenced);
        $this->assertContains('App\A', $referenced);
        $this->assertContains('App\B', $referenced);
        $this->assertContains('App\C', $referenced);
        $this->assertContains('App\D', $referenced);
        $this->assertContains('App\E', $referenced);
        $this->assertContains('App\LoggableTrait', $referenced);
    }

    public function testAnonymousClassExcluded(): void
    {
        $result = $this->scanCode(
            '<?php namespace App; class Foo { public function bar(): object { return new class {}; } }'
        );

        $this->assertSame(['App\Foo'], $result['fqns']);
    }
}

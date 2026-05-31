<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

final class EntityExtractorTest extends TestCase
{
    private EntityExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new EntityExtractor();
    }

    private function parseAndResolve(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        return $traverser->traverse($ast);
    }

    public function testExtractClass(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; class UserService extends BaseService implements ServiceInterface {
            public function findById(int $id): ?User {}
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertSame('App\UserService', $entities[0]['fqn']);
        $this->assertSame('class', $entities[0]['type']);
        $this->assertSame('App\BaseService', $entities[0]['parentClass']);
        $this->assertContains('App\ServiceInterface', $entities[0]['interfaces']);
    }

    public function testExtractInterface(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; interface ServiceInterface {
            public function findById(int $id): ?object;
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertSame('App\ServiceInterface', $entities[0]['fqn']);
        $this->assertSame('interface', $entities[0]['type']);
    }

    public function testInterfaceExtends(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; interface Sortable extends \App\Contracts\Comparable, \App\Contracts\Iterable {
            public function sort(): void;
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertCount(2, $entities[0]['interfaces']);
        $this->assertContains('App\Contracts\Comparable', $entities[0]['interfaces']);
        $this->assertContains('App\Contracts\Iterable', $entities[0]['interfaces']);
    }

    public function testInterfaceNoExtends(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; interface ServiceInterface {
            public function findById(int $id): ?object;
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertSame([], $entities[0]['interfaces']);
    }

    public function testExtractTrait(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; trait LoggableTrait {
            public function log(string $msg): void {}
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertSame('App\LoggableTrait', $entities[0]['fqn']);
        $this->assertSame('trait', $entities[0]['type']);
    }

    public function testExtractEnum(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; enum Status: string {
            case Active = "active";
            case Inactive;
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertSame('App\Status', $entities[0]['fqn']);
        $this->assertSame('enum', $entities[0]['type']);
        $this->assertSame('string', $entities[0]['scalarType']);
        $this->assertCount(2, $entities[0]['cases']);
        $this->assertSame('Active', $entities[0]['cases'][0]['name']);
        $this->assertSame("'active'", $entities[0]['cases'][0]['value']);
        $this->assertSame('Inactive', $entities[0]['cases'][1]['name']);
        $this->assertNull($entities[0]['cases'][1]['value']);
    }

    public function testPureEnum(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; enum Status {
            case Active;
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertNull($entities[0]['scalarType']);
        $this->assertCount(1, $entities[0]['cases']);
        $this->assertNull($entities[0]['cases'][0]['value']);
    }

    public function testExtractConstants(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; class Config {
            const VERSION = "1.0";
            public const MAX = 100;
            protected const TMP = "x";
            private const SECRET = "hidden";
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $constants = $entities[0]['constants'];
        $this->assertCount(4, $constants);
        $this->assertSame('MAX', $constants[0]['name']);
        $this->assertSame('public', $constants[0]['visibility']);
        $this->assertSame('SECRET', $constants[1]['name']);
        $this->assertSame('private', $constants[1]['visibility']);
        $this->assertSame('TMP', $constants[2]['name']);
        $this->assertSame('protected', $constants[2]['visibility']);
        $this->assertSame('VERSION', $constants[3]['name']);
        $this->assertSame('public', $constants[3]['visibility']);
    }

    public function testInterfaceConstants(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; interface Sortable {
            const ORDER_ASC = "asc";
            public function sort(): void;
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertCount(1, $entities[0]['constants']);
        $this->assertSame('ORDER_ASC', $entities[0]['constants'][0]['name']);
    }

    public function testTraitConstants(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; trait Loggable {
            private const LOG_LEVEL = "debug";
            public const PREFIX = "app";
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertCount(2, $entities[0]['constants']);
        $this->assertSame('LOG_LEVEL', $entities[0]['constants'][0]['name']);
        $this->assertSame('PREFIX', $entities[0]['constants'][1]['name']);
    }

    public function testReadonlyClass(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; readonly class Config {
            public function get(): string { return ""; }
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertContains('readonly', $entities[0]['modifiers']);
    }

    public function testAbstractClass(): void
    {
        $ast = $this->parseAndResolve('<?php namespace App; abstract class Base {}');
        $entities = $this->extractor->extractEntities($ast);
        $this->assertContains('abstract', $entities[0]['modifiers']);
    }

    public function testMultiEntityFile(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; interface Sortable { public function sort(array $items): array; }
            class ArraySorter implements Sortable { public function sort(array $items): array { return $items; } }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(2, $entities);
        $this->assertSame('App\ArraySorter', $entities[0]['fqn']);
        $this->assertSame('App\Sortable', $entities[1]['fqn']);
    }

    public function testMethodsParameterExtraction(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; class UserService {
            public function findById(int $id, ?bool $active = true): ?User {}
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $methods = $entities[0]['methods'];
        $this->assertCount(1, $methods);
        $this->assertSame('findById', $methods[0]['name']);
        $this->assertCount(2, $methods[0]['parameters']);
        $this->assertSame('int', $methods[0]['parameters'][0]['type']);
        $this->assertSame('active', $methods[0]['parameters'][1]['name']);
        $this->assertSame('true', $methods[0]['parameters'][1]['defaultValue']);
    }

    public function testEmptyPublicMethods(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; class EmptyClass {
            protected function hidden() {} private function secret() {}
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertCount(2, $entities[0]['methods']);
        $this->assertSame('hidden', $entities[0]['methods'][0]['name']);
        $this->assertSame('secret', $entities[0]['methods'][1]['name']);
    }

    public function testExtractProperties(): void
    {
        $ast = $this->parseAndResolve(
            '<?php namespace App; class User {
            public string $name;
            protected int $age = 18;
            private bool $isAdmin = false;
            public static int $count = 0;
            public readonly string $uuid;
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $properties = $entities[0]['properties'];
        $this->assertCount(5, $properties);

        $this->assertSame('age', $properties[0]['name']);
        $this->assertSame('protected', $properties[0]['visibility']);
        $this->assertSame('18', $properties[0]['defaultValue']);

        $this->assertSame('count', $properties[1]['name']);
        $this->assertTrue($properties[1]['isStatic']);

        $this->assertSame('isAdmin', $properties[2]['name']);
        $this->assertSame('private', $properties[2]['visibility']);

        $this->assertSame('name', $properties[3]['name']);
        $this->assertSame('public', $properties[3]['visibility']);

        $this->assertSame('uuid', $properties[4]['name']);
        $this->assertTrue($properties[4]['isReadonly']);
    }

    public function testFileExtractorExtractConstants(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse(
            '<?php
            const GLOBAL_CONST = 42;
            define("DYNAMIC_CONST", "value");
            define("ANOTHER", 3.14);
            '
        );
        $extractor = new FileExtractor();
        $constants = $extractor->extractConstants($ast);
        $this->assertCount(3, $constants);
        $this->assertSame('ANOTHER', $constants[0]['name']);
        $this->assertSame('3.14', $constants[0]['value']);
        $this->assertSame('DYNAMIC_CONST', $constants[1]['name']);
        $this->assertSame("'value'", $constants[1]['value']);
        $this->assertSame('GLOBAL_CONST', $constants[2]['name']);
        $this->assertSame('42', $constants[2]['value']);
    }

    public function testFileExtractorExtractGlobalsScope(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse(
            '<?php
            $globalVar = "visible";
            function test() {
                $localVar = "hidden";
            }
            '
        );
        $extractor = new FileExtractor();
        $globals = $extractor->extractGlobals($ast);
        $this->assertCount(1, $globals);
        $this->assertSame('globalVar', $globals[0]);
    }
}

<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class EntityExtractorTest extends TestCase
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
        }'
        );
        $entities = $this->extractor->extractEntities($ast);
        $this->assertCount(1, $entities);
        $this->assertSame('App\Status', $entities[0]['fqn']);
        $this->assertSame('enum', $entities[0]['type']);
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
        $this->assertSame([], $entities[0]['methods']);
    }
}

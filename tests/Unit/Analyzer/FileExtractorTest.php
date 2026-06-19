<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\FileExtractor;

final class FileExtractorTest extends TestCase
{
    private FileExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new FileExtractor();
    }

    private function parseCode(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        return $parser->parse('<?php ' . $code);
    }

    // ---- extractFunctions ----

    public function testExtractFunctionsSimple(): void
    {
        $ast = $this->parseCode(
            '
            function foo(int $x, string $y = "hi"): void {}
        '
        );
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertCount(1, $functions);
        $this->assertSame('foo', $functions[0]['name']);
        $this->assertCount(2, $functions[0]['parameters']);
        $this->assertSame('x', $functions[0]['parameters'][0]['name']);
        $this->assertSame('int', $functions[0]['parameters'][0]['type']);
        $this->assertSame('y', $functions[0]['parameters'][1]['name']);
        $this->assertSame('string', $functions[0]['parameters'][1]['type']);
        $this->assertSame("'hi'", $functions[0]['parameters'][1]['defaultValue']);
        $this->assertSame('void', $functions[0]['returnType']);
    }

    public function testExtractFunctionsNoReturnType(): void
    {
        $ast = $this->parseCode('function foo() {}');
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertNull($functions[0]['returnType']);
    }

    public function testExtractFunctionsMixedType(): void
    {
        $ast = $this->parseCode('function foo($x) {}');
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertSame('mixed', $functions[0]['parameters'][0]['type']);
    }

    public function testExtractFunctionsVariadicAndByRef(): void
    {
        $ast = $this->parseCode(
            '
            function foo(string ...$items): void {}
            function bar(int &$ref): void {}
        '
        );
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertCount(2, $functions);
        $this->assertTrue($functions[0]['parameters'][0]['isVariadic']);
        $this->assertFalse($functions[0]['parameters'][0]['isPassedByReference']);
        $this->assertFalse($functions[1]['parameters'][0]['isVariadic']);
        $this->assertTrue($functions[1]['parameters'][0]['isPassedByReference']);
    }

    public function testExtractFunctionsEmpty(): void
    {
        $ast = $this->parseCode('');
        $this->assertSame([], $this->extractor->extractFunctions($ast));
    }

    // ---- extractGlobals ----

    public function testExtractGlobals(): void
    {
        $ast = $this->parseCode(
            '
            $x = 1;
            $y = 2;
            function foo() {
                return $z;
            }
        '
        );
        $globals = $this->extractor->extractGlobals($ast);
        $this->assertSame(['x', 'y'], $globals);
    }

    public function testExtractGlobalsEmpty(): void
    {
        $ast = $this->parseCode('class Foo {}');
        $this->assertSame([], $this->extractor->extractGlobals($ast));
    }

    public function testExtractGlobalsSkipsSuperGlobals(): void
    {
        $ast = $this->parseCode(
            '
            $_GET;
            $_POST;
            $x = 1;
        '
        );
        $globals = $this->extractor->extractGlobals($ast);
        $this->assertSame(['x'], $globals);
    }

    // ---- extractConstants ----

    public function testExtractConstantsFromConst(): void
    {
        $ast = $this->parseCode(
            '
            const FOO = 1;
            const BAR = "hello";
        '
        );
        $constants = $this->extractor->extractConstants($ast);
        $this->assertCount(2, $constants);
        $this->assertSame('BAR', $constants[0]['name']);
        $this->assertSame("'hello'", $constants[0]['value']);
        $this->assertSame('FOO', $constants[1]['name']);
        $this->assertSame('1', $constants[1]['value']);
    }

    public function testExtractConstantsFromDefine(): void
    {
        $ast = $this->parseCode(
            '
            define("MY_CONST", 42);
            define("OTHER", "val");
        '
        );
        $constants = $this->extractor->extractConstants($ast);
        $this->assertCount(2, $constants);
        $this->assertSame('MY_CONST', $constants[0]['name']);
        $this->assertSame('42', $constants[0]['value']);
        $this->assertSame('OTHER', $constants[1]['name']);
        $this->assertSame("'val'", $constants[1]['value']);
    }

    public function testExtractConstantsFromExpressionStatement(): void
    {
        $ast = $this->parseCode('define("FOO", 1);');
        $constants = $this->extractor->extractConstants($ast);
        $this->assertCount(1, $constants);
        $this->assertSame('FOO', $constants[0]['name']);
        $this->assertSame('1', $constants[0]['value']);
    }

    public function testExtractConstantsEmpty(): void
    {
        $ast = $this->parseCode('');
        $this->assertSame([], $this->extractor->extractConstants($ast));
    }

    public function testExtractConstantsSkipsNonDefineCalls(): void
    {
        $ast = $this->parseCode('strlen("x");');
        $constants = $this->extractor->extractConstants($ast);
        $this->assertSame([], $constants);
    }

    // ---- resolveType ----

    public function testResolveTypeNullable(): void
    {
        $ast = $this->parseCode('function foo(?int $x): void {}');
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertSame('?int', $functions[0]['parameters'][0]['type']);
    }

    public function testResolveTypeUnion(): void
    {
        $ast = $this->parseCode('function foo(int|string $x): void {}');
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertSame('int|string', $functions[0]['parameters'][0]['type']);
    }

    public function testResolveTypeIntersection(): void
    {
        $ast = $this->parseCode('function foo(\ArrayAccess&\Countable $x): void {}');
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertSame('\\ArrayAccess&\\Countable', $functions[0]['parameters'][0]['type']);
    }

    public function testResolveTypeName(): void
    {
        $ast = $this->parseCode('function foo(\App\Entity\User $x): void {}');
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertSame('\\App\\Entity\\User', $functions[0]['parameters'][0]['type']);
    }

    public function testResolveTypeIdentifier(): void
    {
        $ast = $this->parseCode('function foo(int $x): void {}');
        $functions = $this->extractor->extractFunctions($ast);
        $this->assertSame('int', $functions[0]['parameters'][0]['type']);
    }
}

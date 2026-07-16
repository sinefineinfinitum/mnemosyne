<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Ast\Document;
use SineFine\Mnemosyne\Msv1Parser\Ast\EntityNode;
use SineFine\Mnemosyne\Msv1Parser\Parser;
use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

if (!trait_exists(TestTrait::class)) {
    return;
}

/**
 * @requires PHP >= 8.1
 */
class ParserPropertyTest extends TestCase
{
    use TestTrait;

    private const VALID_CONTENTS = [
        "@class App\\Service\n",
        "@interface App\\Contract\n",
        "@trait App\\Loggable\n",
        "@enum App\\Status\n~Active\n",
        "@file f.php\n",
        "@class A\n>B\n<C\n%D\n",
        "@class E final\n",
        "@class F\n\$x:int\n\$y:string\n",
        "@class G\n!X:int=1\n!Y:string=hello\n",
        "@class H\n.-method\n    \$p:int\n    :void\n    ^App\\Result\n    *App\\Util::helper\n",
        "@class I\n.-m static\n    &\$p:string\n    :bool\n",
    ];

    private const EMPTY_CONTENTS = ['', "\n", "\n\n", "\r\n", "  ", "\t"];

    private const UNKNOWN_ENTITIES = [
        '@unknown Foo',
        '@mixin App\Foo',
        '@ Foo',
        '@123',
        '@',
    ];

    private const FILE_VISIBILITY = [
        "@file f.php\n\$+prop:int",
        "@file f.php\n\$#prop:int",
        "@file f.php\n!+CONST:int=1",
        "@file f.php\n.+\n    \$x:int\n    :void",
    ];

    private const INVALID_INDENTS = [
        "@class A\n  \$p:int",
        "@class A\n   \$p:int",
        "@class A\n     \$p:int",
        "@class A\n\t\$p:int",
        "@class A\n.-m\n    \$p:int\n      \$q:int",
    ];

    public function testFuzzParserNeverCrashes(): void
    {
        $this->forAll(Generators::string())
            ->withMaxSize(200)
            ->then(function (string $content): void {
                $parser = new Parser();
                try {
                    $doc = $parser->parse($content);
                    $this->assertInstanceOf(Document::class, $doc);
                    $this->assertIsArray($doc->entities);
                    $this->assertSame(Parser::VERSION, $doc->parserVersion);
                    foreach ($doc->entities as $entity) {
                        $this->assertInstanceOf(EntityNode::class, $entity);
                        $this->assertContains($entity->type, EntityNode::TYPES);
                        $this->assertNotEmpty($entity->name);
                    }
                } catch (SyntaxException) {
                    $this->assertTrue(true);
                }
            });
    }

    public function testDeterministicParseOfValidInputs(): void
    {
        $this->forAll(Generators::elements(self::VALID_CONTENTS))
            ->then(function (string $content): void {
                $parser = new Parser();
                $doc1 = $parser->parse($content);
                $doc2 = $parser->parse($content);

                $this->assertCount(count($doc1->entities), $doc2->entities);
                foreach ($doc1->entities as $i => $e1) {
                    $e2 = $doc2->entities[$i];
                    $this->assertSame($e1->name, $e2->name);
                    $this->assertSame($e1->type, $e2->type);
                    $this->assertSame($e1->attributes, $e2->attributes);
                    $this->assertCount(count($e1->members), $e2->members);
                    $this->assertSame($e1->extends, $e2->extends);
                    $this->assertSame($e1->implements, $e2->implements);
                    $this->assertSame($e1->traits, $e2->traits);
                }
            });
    }

    public function testEmptyContentYieldsEmptyDocument(): void
    {
        $this->forAll(Generators::elements(self::EMPTY_CONTENTS))
            ->then(function (string $content): void {
                $parser = new Parser();
                $doc = $parser->parse($content);
                $this->assertEmpty($doc->entities);
                $this->assertSame(Parser::VERSION, $doc->parserVersion);
            });
    }

    public function testUnknownEntityTypeThrowsSyntaxException(): void
    {
        $this->forAll(Generators::elements(self::UNKNOWN_ENTITIES))
            ->then(function (string $content): void {
                try {
                    (new Parser())->parse($content);
                    $this->fail('Expected SyntaxException');
                } catch (SyntaxException) {
                    $this->assertTrue(true);
                }
            });
    }

    public function testVisibilityModifiersInFileContextThrowSyntaxException(): void
    {
        $this->forAll(Generators::elements(self::FILE_VISIBILITY))
            ->then(function (string $content): void {
                try {
                    (new Parser())->parse($content);
                    $this->fail('Expected SyntaxException');
                } catch (SyntaxException) {
                    $this->assertTrue(true);
                }
            });
    }

    public function testInvalidIndentationThrowsSyntaxException(): void
    {
        $this->forAll(Generators::elements(self::INVALID_INDENTS))
            ->then(function (string $content): void {
                try {
                    (new Parser())->parse($content);
                    $this->fail('Expected SyntaxException');
                } catch (SyntaxException) {
                    $this->assertTrue(true);
                }
            });
    }

    public function testAllFixturesParseWithValidInvariants(): void
    {
        $files = $this->fixtureFiles();
        $this->assertNotEmpty($files);

        $this->forAll(Generators::elements($files))
            ->then(function (string $filePath): void {
                $parser = new Parser();
                $doc = $parser->parse(file_get_contents($filePath));

                $this->assertNotEmpty($doc->entities);
                $this->assertSame(Parser::VERSION, $doc->parserVersion);

                foreach ($doc->entities as $entity) {
                    $this->assertContains($entity->type, EntityNode::TYPES);
                    $this->assertNotEmpty($entity->name);

                    foreach ($entity->attributes as $attr) {
                        $this->assertContains($attr, ['final', 'abstract', 'static', 'readonly']);
                    }

                    foreach ($entity->members as $member) {
                        $this->assertNotEmpty($member->name);
                        $this->assertContains(
                            $member->type,
                            ['property', 'constant', 'method', 'function', 'global_variable', 'enum_case'],
                        );

                        if (in_array($member->type, ['method', 'function'], true)) {
                            foreach ($member->calls as $call) {
                                $this->assertContains($call->type, ['static', 'dynamic', 'global']);
                                $this->assertContains($call->marker, ['strong', 'weak']);
                                $this->assertNotEmpty($call->targetMethod);
                            }
                            foreach ($member->creates as $create) {
                                $this->assertNotEmpty($create);
                            }
                        }
                    }
                }
            });
    }

    public function testAllFixturesDeterministicParse(): void
    {
        $files = $this->fixtureFiles();
        $this->assertNotEmpty($files);

        $this->forAll(Generators::elements($files))
            ->then(function (string $filePath): void {
                $parser = new Parser();
                $content = file_get_contents($filePath);
                $doc1 = $parser->parse($content);
                $doc2 = $parser->parse($content);

                $this->assertCount(count($doc1->entities), $doc2->entities);
                foreach ($doc1->entities as $i => $e1) {
                    $e2 = $doc2->entities[$i];
                    $this->assertSame($e1->name, $e2->name);
                    $this->assertSame($e1->type, $e2->type);
                    $this->assertCount(count($e1->members), $e2->members);
                }
            });
    }

    /** @return string[] */
    private function fixtureFiles(): array
    {
        $dir = __DIR__ . '/../../Fixtures/docs';
        return array_merge(
            glob($dir . '/*.msv1') ?: [],
            glob($dir . '/Analyzer/*.msv1') ?: [],
            glob($dir . '/Analyzer/**/*.msv1') ?: [],
        );
    }
}

<?php

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Ast\CallNode;
use SineFine\Mnemosyne\Msv1Parser\Parser;
use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

class ParserTest extends TestCase
{
    public function testParseEmptyContentReturnsEmptyDocument(): void
    {
        $parser = new Parser();
        $doc = $parser->parse("");
        $this->assertEmpty($doc->entities);
    }

    public function testParseClassDefinitionReturnsDocumentWithEntity(): void
    {
        $parser = new Parser();
        $content = "@class TestClass";
        $doc = $parser->parse($content);
        $this->assertCount(1, $doc->entities);
        $this->assertEquals("TestClass", $doc->entities[0]->name);
        $this->assertEquals("class", $doc->entities[0]->type);
    }

    /**
     * @dataProvider entityDefinitionProvider
     */
    public function testParseEntityDefinitionReturnsDocumentWithEntity(string $content, string $expectedType, string $expectedName): void
    {
        $parser = new Parser();

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEquals($expectedName, $doc->entities[0]->name);
        $this->assertEquals($expectedType, $doc->entities[0]->type);
    }

    public static function entityDefinitionProvider(): array
    {
        return [
            'interface' => [
                '@interface App\Contracts\SearchInterface',
                'interface',
                'App\Contracts\SearchInterface',
            ],
            'trait' => [
                '@trait App\Traits\LoggableTrait',
                'trait',
                'App\Traits\LoggableTrait',
            ],
            'file' => [
                '@file src/functions.php',
                'file',
                'src/functions.php',
            ],
            'enum' => [
                '@enum App\Status',
                'enum',
                'App\Status',
            ],
        ];
    }

    public function testParseClassExtendsParentClass(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '>App\Core\BaseService',
            ]
        );

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEquals(['App\Core\BaseService'], $doc->entities[0]->extends);
        $this->assertEmpty($doc->entities[0]->implements);
    }

    public function testParseClassImplementsInterfaces(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '<App\Contracts\SearchInterface',
            '<App\Contracts\LoggerAwareInterface',
            ]
        );

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEmpty($doc->entities[0]->extends);
        $this->assertEquals(
            [
            'App\Contracts\SearchInterface',
            'App\Contracts\LoggerAwareInterface',
            ], $doc->entities[0]->implements
        );
    }

    public function testParseClassExtendsAndImplements(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '>App\Core\BaseService',
            '<App\Contracts\SearchInterface',
            '<App\Contracts\LoggerAwareInterface',
            ]
        );

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEquals(['App\Core\BaseService'], $doc->entities[0]->extends);
        $this->assertEquals(
            [
            'App\Contracts\SearchInterface',
            'App\Contracts\LoggerAwareInterface',
            ], $doc->entities[0]->implements
        );
    }

    public function testParseMultipleRootDocumentationFiles(): void
    {
        $parser = new Parser();
        $files = glob(__DIR__ . '/../../Fixtures/docs/*.msv1');

        $this->assertNotEmpty($files);

        $parsedDocuments = [];
        foreach ($files as $file) {
            $doc = $parser->parse(file_get_contents($file));

            $this->assertCount(1, $doc->entities, basename($file));
            $this->assertEquals('class', $doc->entities[0]->type, basename($file));

            $parsedDocuments[] = $doc;
        }

        $this->assertGreaterThanOrEqual(2, count($parsedDocuments));
    }

    public function testParseMultipleDocumentationFilesFromNestedFolder(): void
    {
        $parser = new Parser();
        $files = glob(__DIR__ . '/../../Fixtures/docs/Analyzer/*.msv1');

        $this->assertNotEmpty($files);

        $entityNames = [];
        foreach ($files as $file) {
            $doc = $parser->parse(file_get_contents($file));

            $this->assertCount(1, $doc->entities, basename($file));
            $this->assertEquals('class', $doc->entities[0]->type, basename($file));

            $entityNames[] = $doc->entities[0]->name;
        }

        $this->assertContains('SineFine\Ponymator\Analyzer\Parser', $entityNames);
        $this->assertContains('SineFine\Ponymator\Analyzer\CallAssociationResolver', $entityNames);
        $this->assertGreaterThanOrEqual(2, count($entityNames));
    }

    public function testParseComplexClassWithAllFeatures(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService final',
            '>App\Core\BaseService',
            '<App\Contracts\SearchInterface',
            '%App\LoggableTrait',
            '',
            '$-vectorStore readonly:App\Storage\VectorStore',
            '$-mixedResult:int|string|null',
            '',
            '!+DEFAULT_LIMIT:int=25',
            '',
            '.+search final',
            '    $query:App\Query\SearchQuery',
            '    :App\Search\SearchResult|null',
            '    ^App\Search\SearchResult',
            '',
            '.+merge static',
            '    &$source:array',
            '    $limit:int=10',
            '    :array',
            ]
        );

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $entity = $doc->entities[0];
        $this->assertEquals('class', $entity->type);
        $this->assertEquals('App\Service\SearchService', $entity->name);
        $this->assertEquals(['final'], $entity->attributes);
        $this->assertEquals(['App\Core\BaseService'], $entity->extends);
        $this->assertEquals(['App\Contracts\SearchInterface'], $entity->implements);
        $this->assertEquals(['App\LoggableTrait'], $entity->traits);

        $this->assertCount(5, $entity->members);

        // 1. $-vectorStore readonly:App\Storage\VectorStore
        $prop1 = $entity->members[0];
        $this->assertEquals('vectorStore', $prop1->name);
        $this->assertEquals('property', $prop1->type);
        $this->assertEquals('private', $prop1->visibility);
        $this->assertEquals(['readonly'], $prop1->attributes);
        $this->assertEquals('App\Storage\VectorStore', $prop1->dataType);
        $this->assertNull($prop1->value);

        // 2. $-mixedResult:int|string|null
        $prop2 = $entity->members[1];
        $this->assertEquals('mixedResult', $prop2->name);
        $this->assertEquals('property', $prop2->type);
        $this->assertEquals('private', $prop2->visibility);
        $this->assertEquals('int|string|null', $prop2->dataType);

        // 3. !+DEFAULT_LIMIT:int=25
        $const1 = $entity->members[2];
        $this->assertEquals('DEFAULT_LIMIT', $const1->name);
        $this->assertEquals('constant', $const1->type);
        $this->assertEquals('public', $const1->visibility);
        $this->assertEquals('int', $const1->dataType);
        $this->assertEquals('25', $const1->value);

        // 4. .+search final
        $method1 = $entity->members[3];
        $this->assertEquals('search', $method1->name);
        $this->assertEquals('method', $method1->type);
        $this->assertEquals('public', $method1->visibility);
        $this->assertEquals(['final'], $method1->attributes);
        $this->assertEquals('App\Search\SearchResult|null', $method1->returnType);
        $this->assertEquals(['App\Search\SearchResult'], $method1->creates);
        $this->assertCount(1, $method1->parameters);

        $param1 = $method1->parameters[0];
        $this->assertEquals('query', $param1->name);
        $this->assertEquals('App\Query\SearchQuery', $param1->type);
        $this->assertFalse($param1->byRef);
        $this->assertNull($param1->value);

        // 5. .+merge static
        $method2 = $entity->members[4];
        $this->assertEquals('merge', $method2->name);
        $this->assertEquals('method', $method2->type);
        $this->assertEquals('public', $method2->visibility);
        $this->assertEquals(['static'], $method2->attributes);
        $this->assertEquals('array', $method2->returnType);
        $this->assertCount(2, $method2->parameters);

        $param2_1 = $method2->parameters[0];
        $this->assertEquals('source', $param2_1->name);
        $this->assertEquals('array', $param2_1->type);
        $this->assertTrue($param2_1->byRef);

        $param2_2 = $method2->parameters[1];
        $this->assertEquals('limit', $param2_2->name);
        $this->assertEquals('int', $param2_2->type);
        $this->assertEquals('10', $param2_2->value);
    }

    public function testParseEnumWithCases()
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@enum App\Status',
            '~Active=1',
            '~Inactive:int=2',
            '~Pending',
            ]
        );

        $doc = $parser->parse($content);
        $this->assertCount(1, $doc->entities);
        $entity = $doc->entities[0];
        $this->assertEquals('enum', $entity->type);
        $this->assertCount(3, $entity->members);

        $case1 = $entity->members[0];
        $this->assertEquals('Active', $case1->name);
        $this->assertEquals('enum_case', $case1->type);
        $this->assertEquals('1', $case1->value);
        $this->assertNull($case1->dataType);

        $case2 = $entity->members[1];
        $this->assertEquals('Inactive', $case2->name);
        $this->assertEquals('enum_case', $case2->type);
        $this->assertEquals('2', $case2->value);
        $this->assertEquals('int', $case2->dataType);

        $case3 = $entity->members[2];
        $this->assertEquals('Pending', $case3->name);
        $this->assertEquals('enum_case', $case3->type);
        $this->assertNull($case3->value);
    }

    public function testParseProceduralFile()
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@file src/functions.php',
            '',
            '.getUser',
            '    $id:int',
            '    :App\Entity\User|null',
            '',
            '!MAX_RETRIES:int=3',
            '',
            '$debugMode:bool=false',
            ]
        );

        $doc = $parser->parse($content);
        $this->assertCount(1, $doc->entities);
        $entity = $doc->entities[0];
        $this->assertEquals('file', $entity->type);
        $this->assertCount(3, $entity->members);

        $func = $entity->members[0];
        $this->assertEquals('getUser', $func->name);
        $this->assertEquals('function', $func->type);
        $this->assertNull($func->visibility);
        $this->assertEquals('App\Entity\User|null', $func->returnType);
        $this->assertCount(1, $func->parameters);
        $this->assertEquals('id', $func->parameters[0]->name);
        $this->assertEquals('int', $func->parameters[0]->type);

        $const = $entity->members[1];
        $this->assertEquals('MAX_RETRIES', $const->name);
        $this->assertEquals('constant', $const->type);
        $this->assertNull($const->visibility);
        $this->assertEquals('int', $const->dataType);
        $this->assertEquals('3', $const->value);

        $globalVar = $entity->members[2];
        $this->assertEquals('debugMode', $globalVar->name);
        $this->assertEquals('global_variable', $globalVar->type);
        $this->assertNull($globalVar->visibility);
        $this->assertEquals('bool', $globalVar->dataType);
        $this->assertEquals('false', $globalVar->value);
    }

    public function testSyntaxExceptionOnInvalidIndentation()
    {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@class Test\n  \$prop:int"); // 2 spaces is invalid
    }

    public function testSyntaxExceptionOnTabsIndentation()
    {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@class Test\n\t\$prop:int");
    }

    public function testSyntaxExceptionOnUnknownEntity()
    {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@unknown App\\SomeClass");
    }

    public function testSyntaxExceptionOnVisibilityInFileContext()
    {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@file src/functions.php\n!+MAX_RETRIES:int=3");
    }

    public function testParseSetsParserVersionFromConstant()
    {
        $parser = new Parser();
        $doc = $parser->parse("@class Foo");

        $this->assertEquals(Parser::VERSION, $doc->parserVersion);
    }

    public function testParseDoesNotSetSourcePathOrHash()
    {
        $parser = new Parser();
        $doc = $parser->parse("@class Foo");

        $this->assertNull($doc->sourcePath);
        $this->assertNull($doc->sourceHash);
    }

    public function testParseReturnsDocumentWithoutSourceMetadata(): void
    {
        $parser = new Parser();
        $content = file_get_contents(__DIR__ . '/../../Fixtures/docs/Ponymator.msv1');
        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertNull($doc->sourcePath);
        $this->assertNull($doc->sourceHash);
    }

    public function testParseCallGraphStaticCall(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    *App\Repository\SearchRepository::find',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(1, $method->calls);
        $this->assertSame('static', $method->calls[0]->type);
        $this->assertSame('App\Repository\SearchRepository', $method->calls[0]->targetFQCN);
        $this->assertSame('find', $method->calls[0]->targetMethod);
        $this->assertSame('strong', $method->calls[0]->marker);
    }

    public function testParseCallGraphDynamicCall(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    ?App\Service\Handler->process',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(1, $method->calls);
        $this->assertSame('dynamic', $method->calls[0]->type);
        $this->assertSame('App\Service\Handler', $method->calls[0]->targetFQCN);
        $this->assertSame('process', $method->calls[0]->targetMethod);
        $this->assertSame('weak', $method->calls[0]->marker);
    }

    public function testParseCallGraphGlobalCall(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    *App\Util\formatDate',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(1, $method->calls);
        $this->assertSame('global', $method->calls[0]->type);
        $this->assertSame('', $method->calls[0]->targetFQCN);
        $this->assertSame('App\Util\formatDate', $method->calls[0]->targetMethod);
        $this->assertSame('strong', $method->calls[0]->marker);
    }

    public function testParseCallGraphGlobalCallWithoutNamespace(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    *global_call',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(1, $method->calls);
        $this->assertSame('global', $method->calls[0]->type);
        $this->assertSame('', $method->calls[0]->targetFQCN);
        $this->assertSame('global_call', $method->calls[0]->targetMethod);
        $this->assertSame('strong', $method->calls[0]->marker);
    }

    public function testParseCallGraphPreservesDuplicates(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    *App\Repository\SearchRepository::find',
            '    *App\Repository\SearchRepository::find',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(2, $method->calls);
    }

    public function testParseCallGraphPreservesOrder(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    *App\Repository\SearchRepository::find',
            '    ?App\Service\Handler->process',
            '    *App\Util\formatDate',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(3, $method->calls);
        $this->assertSame('static', $method->calls[0]->type);
        $this->assertSame('dynamic', $method->calls[1]->type);
        $this->assertSame('global', $method->calls[2]->type);
    }

    public function testParseCallGraphMixedWithParametersAndReturnType(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    $query:string',
            '    :void',
            '    *App\Repository\SearchRepository::find',
            '    ^App\Search\Result',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(1, $method->parameters);
        $this->assertSame('void', $method->returnType);
        $this->assertCount(1, $method->calls);
        $this->assertCount(1, $method->creates);
    }


    public function testParseCallGraphEmptyMethod(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+search',
            '    :void',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertEmpty($method->calls);
    }

    public function testParsePonymatorFixtureRunMethodCalls(): void
    {
        $parser = new Parser();
        $content = file_get_contents(__DIR__ . '/../../Fixtures/docs/Ponymator.msv1');
        $doc = $parser->parse($content);

        $entity = $doc->entities[0];
        $this->assertSame('SineFine\Ponymator\Ponymator', $entity->name);

        $runMethod = null;
        foreach ($entity->members as $member) {
            if ($member->name === 'run') {
                $runMethod = $member;
                break;
            }
        }
        $this->assertNotNull($runMethod);
        $this->assertCount(13, $runMethod->creates);
        $this->assertNotEmpty($runMethod->calls);

        $staticCalls = array_filter($runMethod->calls, fn(CallNode $c) => $c->type === 'static');
        $dynamicCalls = array_filter($runMethod->calls, fn(CallNode $c) => $c->type === 'dynamic');
        $this->assertNotEmpty($staticCalls);
        $this->assertNotEmpty($dynamicCalls);

        $argParseCall = null;
        foreach ($runMethod->calls as $call) {
            if ($call->targetFQCN === 'SineFine\Ponymator\Cli\ArgumentParser' && $call->targetMethod === 'parse') {
                $argParseCall = $call;
                break;
            }
        }
        $this->assertNotNull($argParseCall);
        $this->assertSame('static', $argParseCall->type);
        $this->assertSame('strong', $argParseCall->marker);
    }

    public function testParseConfigFixtureHasNoCalls(): void
    {
        $parser = new Parser();
        $content = file_get_contents(__DIR__ . '/../../Fixtures/docs/Config.msv1');
        $doc = $parser->parse($content);

        $entity = $doc->entities[0];
        $this->assertSame('SineFine\Ponymator\Config', $entity->name);

        foreach ($entity->members as $member) {
            if ($member->type === 'method') {
                $this->assertEmpty($member->calls);
            }
        }
    }

    public function testParseCallAssociationResolverEnterNodeCalls(): void
    {
        $parser = new Parser();
        $content = file_get_contents(__DIR__ . '/../../Fixtures/docs/Analyzer/CallAssociationResolver.msv1');
        $doc = $parser->parse($content);

        $entity = $doc->entities[0];
        $enterNode = null;
        foreach ($entity->members as $member) {
            if ($member->name === 'enterNode') {
                $enterNode = $member;
                break;
            }
        }
        $this->assertNotNull($enterNode);
        $this->assertCount(10, $enterNode->calls);

        foreach ($enterNode->calls as $call) {
            $this->assertSame('dynamic', $call->type);
            $this->assertSame('strong', $call->marker);
        }
    }

    public function testParseDependencyCollectingVisitorHasWeakCall(): void
    {
        $parser = new Parser();
        $content = file_get_contents(__DIR__ . '/../../Fixtures/docs/Analyzer/Visitor/DependencyCollectingVisitor.msv1');
        $doc = $parser->parse($content);

        $entity = $doc->entities[0];
        $enterNode = null;
        foreach ($entity->members as $member) {
            if ($member->name === 'enterNode') {
                $enterNode = $member;
                break;
            }
        }
        $this->assertNotNull($enterNode);

        $weakCalls = array_filter($enterNode->calls, fn(CallNode $c) => $c->marker === 'weak');
        $this->assertNotEmpty($weakCalls);

        $weakCall = array_values($weakCalls)[0];
        $this->assertSame('dynamic', $weakCall->type);
        $this->assertSame('PhpParser\Node', $weakCall->targetFQCN);
        $this->assertSame('toCodeString', $weakCall->targetMethod);
    }

    public function testParseAstHelperFixtureRecursiveCalls(): void
    {
        $parser = new Parser();
        $content = file_get_contents(__DIR__ . '/../../Fixtures/docs/Analyzer/Extractor/AstHelper.msv1');
        $doc = $parser->parse($content);

        $entity = $doc->entities[0];
        $this->assertSame('SineFine\Ponymator\Analyzer\Extractor\AstHelper', $entity->name);

        $resolveType = null;
        foreach ($entity->members as $member) {
            if ($member->name === 'resolveType') {
                $resolveType = $member;
                break;
            }
        }
        $this->assertNotNull($resolveType);

        $selfCalls = array_filter(
            $resolveType->calls,
            fn(CallNode $c) => $c->targetFQCN === 'SineFine\Ponymator\Analyzer\Extractor\AstHelper'
        );
        $this->assertNotEmpty($selfCalls);
    }

    public function testParseAllFixturesCallGraphIntegrity(): void
    {
        $parser = new Parser();
        $rootFiles = glob(__DIR__ . '/../../Fixtures/docs/*.msv1') ?: [];
        $analyzerTopFiles = glob(__DIR__ . '/../../Fixtures/docs/Analyzer/*.msv1') ?: [];
        $analyzerNestedFiles = glob(__DIR__ . '/../../Fixtures/docs/Analyzer/**/*.msv1') ?: [];
        $allFiles = array_merge($rootFiles, $analyzerTopFiles, $analyzerNestedFiles);

        $this->assertNotEmpty($allFiles);

        foreach ($allFiles as $file) {
            $content = file_get_contents($file);
            $doc = $parser->parse($content);
            foreach ($doc->entities as $entity) {
                foreach ($entity->members as $member) {
                    if ($member->type !== 'method' && $member->type !== 'function') {
                        continue;
                    }
                    foreach ($member->calls as $call) {
                        $this->assertContains($call->type, ['static', 'dynamic', 'global']);
                        $this->assertContains($call->marker, ['strong', 'weak']);
                        $this->assertNotEmpty($call->targetMethod);
                    }
                }
            }
        }
    }

    public function testParseCallGraphDynamicCallMultipleCandidates(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@class App\Service\SearchService',
            '.+parse',
            '    ?App\Service\Handler1->process',
            '    ?App\Service\Handler2->process',
            ]
        );

        $doc = $parser->parse($content);
        $method = $doc->entities[0]->members[0];
        $this->assertCount(2, $method->calls);
        $this->assertSame('App\Service\Handler1', $method->calls[0]->targetFQCN);
        $this->assertSame('App\Service\Handler2', $method->calls[1]->targetFQCN);
        $this->assertSame('weak', $method->calls[0]->marker);
        $this->assertSame('weak', $method->calls[1]->marker);
    }

    public function testParseCallGraphInFileContext(): void
    {
        $parser = new Parser();
        $content = implode(
            "\n", [
            '@file src/helpers.php',
            '.processData',
            '    $data:array',
            '    :array',
            '    *App\Util\formatDate',
            '    *App\Service\Logger->log',
            ]
        );

        $doc = $parser->parse($content);
        $entity = $doc->entities[0];
        $this->assertSame('file', $entity->type);
        $func = $entity->members[0];
        $this->assertSame('function', $func->type);
        $this->assertNull($func->visibility);
        $this->assertCount(2, $func->calls);
        $this->assertSame('global', $func->calls[0]->type);
        $this->assertSame('dynamic', $func->calls[1]->type);
    }

    public function testParseFileFunctionPreservesAllCalls(): void
    {
        $parser = new Parser();
        $content = implode("\n", [
            '@file src/helpers.php',
            '.processData',
            '    $data:array',
            '    :array',
            '    *strlen',
            '    *array_map',
            '    *App\Service\Logger->log',
        ]);

        $doc = $parser->parse($content);
        $func = $doc->entities[0]->members[0];
        $this->assertSame('function', $func->type);
        $this->assertCount(3, $func->calls);
        $this->assertSame('global', $func->calls[0]->type);
        $this->assertSame('strlen', $func->calls[0]->targetMethod);
        $this->assertSame('global', $func->calls[1]->type);
        $this->assertSame('array_map', $func->calls[1]->targetMethod);
        $this->assertSame('dynamic', $func->calls[2]->type);
    }

    public function testParseFileMultipleFunctionsWithCalls(): void
    {
        $parser = new Parser();
        $content = implode("\n", [
            '@file src/helpers.php',
            '.formatName',
            '    $name:string',
            '    :string',
            '    *App\Util\StringUtils::normalize',
            '.validateEmail',
            '    $email:string',
            '    :bool',
            '    *App\Service\Validator->check',
        ]);

        $doc = $parser->parse($content);
        $this->assertCount(2, $doc->entities[0]->members);

        $func1 = $doc->entities[0]->members[0];
        $this->assertSame('formatName', $func1->name);
        $this->assertSame('function', $func1->type);
        $this->assertCount(1, $func1->calls);
        $this->assertSame('static', $func1->calls[0]->type);

        $func2 = $doc->entities[0]->members[1];
        $this->assertSame('validateEmail', $func2->name);
        $this->assertSame('function', $func2->type);
        $this->assertCount(1, $func2->calls);
        $this->assertSame('dynamic', $func2->calls[0]->type);
    }

    public function testParseStaticPropertyWithKeywordLikeName(): void
    {
        $parser = new Parser();
        $content = implode("\n", [
            '@class App\Config',
            '$-final static:array=[]',
            '$-finalMethods static:array=[]',
            '$-deprecated static:array=[]',
            '$-internal static:array=[]',
            '$-caseCheck readonly:int',
        ]);

        $doc = $parser->parse($content);
        $entity = $doc->entities[0];

        $prop1 = $entity->members[0];
        $this->assertSame('final', $prop1->name);
        $this->assertSame('property', $prop1->type);
        $this->assertSame('private', $prop1->visibility);
        $this->assertSame(['static'], $prop1->attributes);
        $this->assertSame('array', $prop1->dataType);
        $this->assertSame('[]', $prop1->value);

        $prop2 = $entity->members[1];
        $this->assertSame('finalMethods', $prop2->name);
        $this->assertSame(['static'], $prop2->attributes);

        $prop3 = $entity->members[2];
        $this->assertSame('deprecated', $prop3->name);
        $this->assertSame(['static'], $prop3->attributes);

        $prop4 = $entity->members[3];
        $this->assertSame('internal', $prop4->name);
        $this->assertSame(['static'], $prop4->attributes);

        $prop5 = $entity->members[4];
        $this->assertSame('caseCheck', $prop5->name);
        $this->assertSame('private', $prop5->visibility);
        $this->assertSame(['readonly'], $prop5->attributes);
        $this->assertSame('int', $prop5->dataType);
    }

    public function testParseFileFunctionWithDuplicateCalls(): void
    {
        $parser = new Parser();
        $content = implode("\n", [
            '@file src/helpers.php',
            '.processData',
            '    $data:array',
            '    :array',
            '    *App\Service\Logger->log',
            '    *App\Service\Logger->log',
            '    *App\Service\Cache->get',
        ]);

        $doc = $parser->parse($content);
        $func = $doc->entities[0]->members[0];
        $this->assertCount(3, $func->calls);
    }
}

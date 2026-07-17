<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\PSV1;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\CallInfo;
use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\ClassRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\Msv1Builder;

final class ClassRendererTest extends TestCase
{
    private ClassRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ClassRenderer(new Msv1Builder());
    }

    public function testSupportsClass(): void
    {
        $this->assertTrue($this->renderer->supports(['type' => 'class']));
        $this->assertFalse($this->renderer->supports(['type' => 'interface']));
        $this->assertFalse($this->renderer->supports(['type' => 'trait']));
        $this->assertFalse($this->renderer->supports(['type' => 'enum']));
    }

    public function testRenderEntityHeader(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('@class final App\Service\SearchService', $result);
    }

    public function testRenderEntityExtends(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('>App\Core\BaseService', $result);
    }

    public function testRenderEntityImplements(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('<App\Contracts\SearchInterface', $result);
    }

    public function testRenderEntityNoExtends(): void
    {
        $entity = $this->makeEntity(['parentClass' => null]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('>', $result);
    }

    public function testRenderEntityNoImplements(): void
    {
        $entity = $this->makeEntity(['interfaces' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('<', $result);
    }

    public function testRenderEntityTraits(): void
    {
        $entity = $this->makeEntity(['traits' => ['App\LoggableTrait', 'App\SerializableTrait']]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('%App\LoggableTrait', $result);
        $this->assertStringContainsString('%App\SerializableTrait', $result);
    }

    public function testRenderEntityNoTraits(): void
    {
        $entity = $this->makeEntity(['traits' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('%', $result);
    }

    public function testRenderEntityTraitsBetweenInterfacesAndMembers(): void
    {
        $entity = $this->makeEntity(
            [
            'traits' => ['App\LoggableTrait'],
            'constants' => [
                ['name' => 'X', 'visibility' => 'public', 'type' => 'int', 'value' => '1'],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $lines = explode(PHP_EOL, $result);
        $ifacePos = array_search('<App\Contracts\SearchInterface', $lines);
        $traitPos = array_search('%App\LoggableTrait', $lines);
        $constPos = array_search('!+X:int=1', $lines);
        $this->assertNotFalse($ifacePos);
        $this->assertNotFalse($traitPos);
        $this->assertNotFalse($constPos);
        $this->assertLessThan($traitPos, $ifacePos);
        $this->assertLessThan($constPos, $traitPos);
    }

    public function testRenderEntityMethod(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('.+search final', $result);
    }

    public function testRenderEntityMethodParameters(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('    $query:App\Query\SearchQuery', $result);
    }

    public function testRenderEntityMethodReturnType(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringContainsString('    :App\Search\SearchResult|null', $result);
    }

    public function testRenderEntityMethodCreates(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [
            'search' => ['App\Search\SearchResult'],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('    ^App\Search\SearchResult', $result);
    }

    public function testRenderEntityNoCreatesWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $this->assertStringNotContainsString('^', $result);
    }

    public function testRenderEntityConstants(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'DEFAULT_LIMIT', 'visibility' => 'public', 'type' => 'int', 'value' => '25'],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('!+DEFAULT_LIMIT:int=25', $result);
    }

    public function testRenderEntityProperties(): void
    {
        $entity = $this->makeEntity(
            [
            'properties' => [
                ['name' => 'vectorStore', 'visibility' => 'private', 'type' => 'App\Storage\VectorStore', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => true],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('$-readonly vectorStore:App\Storage\VectorStore', $result);
    }

    public function testRenderEntityNoModifiers(): void
    {
        $entity = $this->makeEntity(['modifiers' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('@class App\Service\SearchService', $result);
    }

    public function testRenderEntityNoMethods(): void
    {
        $entity = $this->makeEntity(['methods' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('.+', $result);
    }

    public function testRenderEntityNoConstants(): void
    {
        $entity = $this->makeEntity(['constants' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('!', $result);
    }

    public function testRenderEntityNoProperties(): void
    {
        $entity = $this->makeEntity(['properties' => []]);
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringNotContainsString('$-', $result);
    }

    public function testRenderEntityStaticMethod(): void
    {
        $entity = $this->makeEntity(
            [
            'methods' => [
                [
                    'name' => 'merge',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => true,
                    'parameters' => [
                        ['name' => 'source', 'type' => 'array', 'defaultValue' => null, 'isPassedByReference' => true, 'isVariadic' => false],
                        ['name' => 'limit', 'type' => 'int', 'defaultValue' => '10', 'isPassedByReference' => false, 'isVariadic' => false],
                    ],
                    'returnType' => 'array',
                ],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('.+merge static', $result);
        $this->assertStringContainsString('    &$source:array', $result);
        $this->assertStringContainsString('    $limit:int=10', $result);
        $this->assertStringContainsString('    :array', $result);
    }

    public function testRenderEntityStaticReturnType(): void
    {
        $entity = $this->makeEntity(
            [
            'methods' => [
                [
                    'name' => 'create',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => true,
                    'parameters' => [],
                    'returnType' => 'static',
                ],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('    :static', $result);
    }

    public function testRenderEntityPromotedProperties(): void
    {
        $entity = $this->makeEntity(
            [
            'properties' => [
                ['name' => 'id', 'visibility' => 'private', 'type' => 'int', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => true],
                ['name' => 'name', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertStringContainsString('$-readonly id:int', $result);
        $this->assertStringContainsString('$+name:string', $result);
    }

    public function testRenderEntityDeterministic(): void
    {
        $entity = $this->makeEntity();
        $first = $this->renderer->renderEntity($entity, new CrossReference());
        $second = $this->renderer->renderEntity($entity, new CrossReference());
        $this->assertSame($first, $second);
    }

    public function testRenderEntityOrderingConstantsMethodsProperties(): void
    {
        $entity = $this->makeEntity(
            [
            'constants' => [
                ['name' => 'C', 'visibility' => 'public', 'type' => 'int', 'value' => '1'],
            ],
            'properties' => [
                ['name' => 'p', 'visibility' => 'public', 'type' => 'string', 'defaultValue' => null, 'isStatic' => false, 'isReadonly' => false],
            ],
            'methods' => [
                [
                    'name' => 'm',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => false,
                    'isStatic' => false,
                    'parameters' => [],
                    'returnType' => 'void',
                ],
            ],
            ]
        );
        $result = $this->renderer->renderEntity($entity, new CrossReference());
        $lines = explode(PHP_EOL, $result);
        $constLine = array_search('!+C:int=1', $lines);
        $propLine = array_search('$+p:string', $lines);
        $methodLine = array_search('.+m', $lines);
        $this->assertNotFalse($constLine);
        $this->assertNotFalse($propLine);
        $this->assertNotFalse($methodLine);
        $this->assertLessThan($propLine, $constLine);
        $this->assertLessThan($methodLine, $propLine);
    }

    public function testRenderEntityCallGraphEntryEmittedForMethod(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'search' => [
                new CallInfo(CallInfo::KIND_STATIC, 'execute', [], 'App\\Search\\Executor::execute', CallInfo::STRONG),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('    *App\\Search\\Executor::execute', $result);
    }

    public function testRenderEntityCallGraphWeakAssociation(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'search' => [
                new CallInfo(CallInfo::KIND_DYNAMIC, 'process', ['App\\A', 'App\\B']),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringContainsString('    ?App\\A->process', $result);
        $this->assertStringContainsString('    ?App\\B->process', $result);
    }

    public function testRenderEntityNoCallGraphWhenEmpty(): void
    {
        $result = $this->renderer->renderEntity($this->makeEntity(), new CrossReference());
        $lines = explode(PHP_EOL, $result);
        foreach ($lines as $line) {
            $this->assertStringNotContainsString(' (static)', $line);
            $this->assertStringNotContainsString(' (dynamic)', $line);
            $this->assertStringNotContainsString(' (global)', $line);
        }
    }

    public function testRenderEntityCallGraphUnknownTarget(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'search' => [
                new CallInfo(CallInfo::KIND_GLOBAL, 'doIt'),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        // Global без resolvedTargetFqcn и candidateTypes не выводится
        $this->assertStringNotContainsString('doIt', $result);
    }

    public function testRenderEntityCallGraphIgnoresUnknownMethods(): void
    {
        $crossRefs = new CrossReference(
            [], [], null, [], [
            'nonExistent' => [
                new CallInfo(CallInfo::KIND_DYNAMIC, 'foo'),
            ],
            ]
        );
        $result = $this->renderer->renderEntity($this->makeEntity(), $crossRefs);
        $this->assertStringNotContainsString(' (dynamic)', $result);
        $this->assertStringNotContainsString('->', $result);
    }

    private function makeEntity(array $overrides = []): array
    {
        return array_merge(
            [
            'fqn' => 'App\Service\SearchService',
            'type' => 'class',
            'modifiers' => ['final'],
            'parentClass' => 'App\Core\BaseService',
            'interfaces' => ['App\Contracts\SearchInterface'],
            'traits' => [],
            'constants' => [],
            'properties' => [],
            'methods' => [
                [
                    'name' => 'search',
                    'visibility' => 'public',
                    'isAbstract' => false,
                    'isFinal' => true,
                    'isStatic' => false,
                    'parameters' => [
                        ['name' => 'query', 'type' => 'App\Query\SearchQuery', 'defaultValue' => null, 'isPassedByReference' => false, 'isVariadic' => false],
                    ],
                    'returnType' => '?App\Search\SearchResult',
                ],
            ],
            'dependencies' => [],
            ], $overrides
        );
    }
}

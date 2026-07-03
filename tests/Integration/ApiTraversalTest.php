<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Parser;

class ApiTraversalTest extends TestCase
{
    public function testApiTraversalOfComplexClass(): void
    {
        $parser = new Parser();
        $msv1Content = <<<MSV1
@class App\Service\SearchService final
>App\Core\BaseService
<App\Contracts\SearchInterface
%App\LoggableTrait

$-vectorStore readonly:App\Storage\VectorStore

.+search final
    \$query:App\Query\SearchQuery
    :App\Search\SearchResult|null
    ^App\Search\SearchResult
MSV1;

        $doc = $parser->parse($msv1Content);

        // Verify Document properties
        $this->assertEquals('1.0', $doc->parserVersion);
        $this->assertCount(1, $doc->entities);

        // Traverse EntityNode
        $entity = $doc->entities[0];
        $this->assertEquals('class', $entity->type);
        $this->assertEquals('App\Service\SearchService', $entity->name);
        $this->assertEquals(['final'], $entity->attributes);
        $this->assertEquals(['App\Core\BaseService'], $entity->extends);
        $this->assertEquals(['App\Contracts\SearchInterface'], $entity->implements);
        $this->assertEquals(['App\LoggableTrait'], $entity->traits);
        $this->assertNull($entity->parent);
        $this->assertCount(2, $entity->members);

        // Traverse Property MemberNode
        $property = $entity->members[0];
        $this->assertEquals('vectorStore', $property->name);
        $this->assertEquals('property', $property->type);
        $this->assertEquals('private', $property->visibility);
        $this->assertEquals(['readonly'], $property->attributes);
        $this->assertEquals('App\Storage\VectorStore', $property->dataType);
        $this->assertEquals('App\Storage\VectorStore', $property->returnType);
        $this->assertNull($property->value);
        $this->assertSame($entity, $property->parent);

        // Traverse Method MemberNode
        $method = $entity->members[1];
        $this->assertEquals('search', $method->name);
        $this->assertEquals('method', $method->type);
        $this->assertEquals('public', $method->visibility);
        $this->assertEquals(['final'], $method->attributes);
        $this->assertEquals('App\Search\SearchResult|null', $method->returnType);
        $this->assertEquals(['App\Search\SearchResult'], $method->creates);
        $this->assertSame($entity, $method->parent);

        // Traverse ParameterNode
        $this->assertCount(1, $method->parameters);
        $parameter = $method->parameters[0];
        $this->assertEquals('query', $parameter->name);
        $this->assertEquals('App\Query\SearchQuery', $parameter->type);
        $this->assertFalse($parameter->byRef);
        $this->assertNull($parameter->value);
    }
}

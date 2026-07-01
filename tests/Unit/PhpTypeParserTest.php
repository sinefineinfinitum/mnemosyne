<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Graph\Experimental\PhpTypeParser;

final class PhpTypeParserTest extends TestCase
{
    private PhpTypeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PhpTypeParser();
    }

    public function testParseAtomicComponentsSimple(): void
    {
        $result = $this->parser->parseAtomicTypes('string');
        $this->assertCount(1, $result);
        $this->assertSame('string', $result[0]['name']);
        $this->assertFalse($result[0]['is_union']);
        $this->assertFalse($result[0]['is_intersection']);
        $this->assertSame(0, $result[0]['position']);
    }

    public function testParseAtomicTypesUnion(): void
    {
        $result = $this->parser->parseAtomicTypes('string|int|null');
        $this->assertCount(3, $result);
        $this->assertSame('string', $result[0]['name']);
        $this->assertSame('int', $result[1]['name']);
        $this->assertSame('null', $result[2]['name']);
        foreach ($result as $component) {
            $this->assertTrue($component['is_union']);
            $this->assertFalse($component['is_intersection']);
        }
    }

    public function testParseAtomicComponentsIntersection(): void
    {
        $result = $this->parser->parseAtomicTypes('Countable&ArrayAccess');
        $this->assertCount(2, $result);
        $this->assertSame('Countable', $result[0]['name']);
        $this->assertSame('ArrayAccess', $result[1]['name']);
        foreach ($result as $component) {
            $this->assertTrue($component['is_intersection']);
            $this->assertFalse($component['is_union']);
        }
    }

    public function testParseAtomicComponentsClassType(): void
    {
        $result = $this->parser->parseAtomicTypes('App\Entity\User');
        $this->assertCount(1, $result);
        $this->assertSame('App\Entity\User', $result[0]['name']);
        $this->assertFalse($result[0]['is_union']);
        $this->assertFalse($result[0]['is_intersection']);
    }

    public function testParseAtomicTypesNullableShorthand(): void
    {
        $result = $this->parser->parseAtomicTypes('?string');
        $this->assertCount(2, $result);
        $this->assertSame('string', $result[0]['name']);
        $this->assertSame('null', $result[1]['name']);
        $this->assertTrue($result[0]['is_union']);
        $this->assertTrue($result[1]['is_union']);
        $this->assertSame(0, $result[0]['position']);
        $this->assertSame(1, $result[1]['position']);
    }

    public function testParseAtomicTypesNullableClass(): void
    {
        $result = $this->parser->parseAtomicTypes('?App\Entity\User');
        $this->assertCount(2, $result);
        $this->assertSame('App\Entity\User', $result[0]['name']);
        $this->assertSame('null', $result[1]['name']);
    }

    public function testParseAtomicTypesNullableAlreadyUnion(): void
    {
        $result = $this->parser->parseAtomicTypes('?string|int');
        $this->assertCount(3, $result);
        $this->assertSame('string', $result[0]['name']);
        $this->assertSame('int', $result[1]['name']);
        $this->assertSame('null', $result[2]['name']);
    }

    public function testExtractClassTypes(): void
    {
        $types = $this->parser->extractClassTypes('string|int|App\Entity\User');
        $this->assertSame(['App\Entity\User'], $types);
    }

    public function testExtractClassTypesFromSingle(): void
    {
        $types = $this->parser->extractClassTypes('App\Entity\User');
        $this->assertSame(['App\Entity\User'], $types);
    }

    public function testExtractClassTypesNone(): void
    {
        $types = $this->parser->extractClassTypes('string|int|null');
        $this->assertSame([], $types);
    }
}

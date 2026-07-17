<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser\Internal;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Internal\NameAndAttributes;
use SineFine\Mnemosyne\Msv1Parser\Internal\TokenParser;
use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

final class NameAndAttributesTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string[]}>
     */
    public static function nameAndKeywordCases(): array
    {
        return [
            'plain name' => ['foo', 'foo', []],
            'name with one keyword after' => ['foo final', 'foo', ['final']],
            'name with static after' => ['foo static', 'foo', ['static']],
            'name with abstract after' => ['foo abstract', 'foo', ['abstract']],
            'name with readonly after' => ['foo readonly', 'foo', ['readonly']],
            'name with multiple keywords after' => ['foo final static', 'foo', ['final', 'static']],
            'name with all four keywords after' => ['foo final abstract static readonly', 'foo', ['final', 'abstract', 'static', 'readonly']],
            'single keyword as name' => ['final', 'final', []],
            'keywords that look like names' => ['static final', 'static', ['final']],
            'all four keywords as name and attrs' => ['final abstract static readonly', 'final', ['abstract', 'static', 'readonly']],
        ];
    }

    /**
     * @dataProvider nameAndKeywordCases
     */
    public function testSplitsNameAndAttributes(string $input, string $expectedName, array $expectedAttributes): void
    {
        $result = TokenParser::splitNameAndAttributes($input);

        $this->assertSame($expectedName, $result->name);
        $this->assertSame($expectedAttributes, $result->attributes);
    }

    public function testReturnsNameAndAttributesInstance(): void
    {
        $this->assertInstanceOf(
            NameAndAttributes::class,
            TokenParser::splitNameAndAttributes('foo'),
        );
    }

    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Name cannot be empty');
        TokenParser::splitNameAndAttributes('');
    }

    public function testWhitespaceStringThrowsException(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Name cannot be empty');
        TokenParser::splitNameAndAttributes('   ');
    }

    public function testNonKeywordAfterNameThrowsException(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unknown attribute "volatile"');
        TokenParser::splitNameAndAttributes('foo volatile');
    }

    public function testUnknownBeforeKnownKeywordThrows(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unknown attribute "unknown"');
        TokenParser::splitNameAndAttributes('foo unknown final');
    }
}

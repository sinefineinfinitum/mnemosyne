<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser\Internal;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Internal\TokenParser;
use SineFine\Mnemosyne\Msv1Parser\Internal\TypedDeclaration;
use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

final class TypedDeclarationTest extends TestCase
{
    /**
     * @return array<string, array{string, string, ?string, ?string}>
     */
    public static function validDeclarations(): array
    {
        return [
            'name only' => ['foo', 'foo', null, null],
            'name with type' => ['foo:string', 'foo', 'string', null],
            'name with value' => ['foo=42', 'foo', null, '42'],
            'name with type and value' => ['foo:string=42', 'foo', 'string', '42'],
            'name with generic type' => ['foo:Collection<User>', 'foo', 'Collection<User>', null],
            'name with nested generic' => ['foo:array<string,int>', 'foo', 'array<string,int>', null],
            'name with generic type and value' => ['foo:Collection<User>=null', 'foo', 'Collection<User>', 'null'],
        ];
    }

    /**
     * @dataProvider validDeclarations
     */
    public function testParsesValidDeclarations(
        string $input,
        string $expectedName,
        ?string $expectedType,
        ?string $expectedValue,
    ): void {
        $result = TokenParser::parseTypedDeclaration($input);

        $this->assertSame($expectedName, $result->nameAndKeywords);
        $this->assertSame($expectedType, $result->dataType);
        $this->assertSame($expectedValue, $result->value);
    }

    public function testReturnsTypedDeclarationInstance(): void
    {
        $this->assertInstanceOf(
            TypedDeclaration::class,
            TokenParser::parseTypedDeclaration('foo:string'),
        );
    }

    public function testTrimsWhitespaceFromName(): void
    {
        $result = TokenParser::parseTypedDeclaration('  foo  :string');
        $this->assertSame('foo', $result->nameAndKeywords);
    }

    public function testTrimsWhitespaceFromType(): void
    {
        $result = TokenParser::parseTypedDeclaration('foo:  string  =42');
        $this->assertSame('string', $result->dataType);
    }

    public function testTrimsWhitespaceFromValue(): void
    {
        $result = TokenParser::parseTypedDeclaration('foo:string=  42  ');
        $this->assertSame('42', $result->value);
    }

    public function testRejectsUnclosedGeneric(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unbalanced angle brackets');
        TokenParser::parseTypedDeclaration('foo:Collection<User');
    }

    public function testRejectsExtraClosingGeneric(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unbalanced angle brackets');
        TokenParser::parseTypedDeclaration('foo:Collection<User>>');
    }
}

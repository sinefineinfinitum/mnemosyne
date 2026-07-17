<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser\Internal;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Internal\Lexer;
use SineFine\Mnemosyne\Msv1Parser\Internal\Line;
use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

final class LexerTest extends TestCase
{
    public function testIndentWidthIsFour(): void
    {
        $this->assertSame(4, Lexer::INDENT_WIDTH);
    }

    public function testEmptyContentReturnsEmptyArray(): void
    {
        $this->assertSame([], (new Lexer())->tokenize(''));
    }

    public function testWhitespaceOnlyContentReturnsEmptyArray(): void
    {
        $this->assertSame([], (new Lexer())->tokenize("   \n\t  \n  \n"));
    }

    public function testTokenizesSingleLine(): void
    {
        $lines = (new Lexer())->tokenize('@class Foo');

        $this->assertCount(1, $lines);
        $this->assertSame('@class Foo', $lines[0]->trimmed);
        $this->assertSame('@class Foo', $lines[0]->raw);
        $this->assertSame(0, $lines[0]->indentation);
        $this->assertSame(0, $lines[0]->number);
    }

    public function testSkipsBlankLines(): void
    {
        $lines = (new Lexer())->tokenize("@class Foo\n\n\n.method\n");

        $this->assertCount(2, $lines);
        $this->assertSame('@class Foo', $lines[0]->trimmed);
        $this->assertSame('.method', $lines[1]->trimmed);
    }

    public function testTrimsCarriageReturnFromCrlf(): void
    {
        $lines = (new Lexer())->tokenize("@class Foo\r\n.method");

        $this->assertCount(2, $lines);
        $this->assertSame('@class Foo', $lines[0]->raw);
        $this->assertNotSame(str_contains($lines[0]->raw, "\r"), true);
    }

    public function testDetectsFourSpaceIndentation(): void
    {
        $lines = (new Lexer())->tokenize("@class Foo\n    \$name");

        $this->assertCount(2, $lines);
        $this->assertSame(0, $lines[0]->indentation);
        $this->assertSame(4, $lines[1]->indentation);
    }

    public function testAssignsSequentialLineNumbers(): void
    {
        $lines = (new Lexer())->tokenize("@class Foo\n>Bar\n<Interface");

        $this->assertSame(0, $lines[0]->number);
        $this->assertSame(1, $lines[1]->number);
        $this->assertSame(2, $lines[2]->number);
    }

    public function testThrowsOnTabIndentation(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid indentation or tabs');
        (new Lexer())->tokenize("@class Foo\n\t\$name");
    }

    public function testThrowsOnTwoSpaceIndentation(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid indentation or tabs');
        (new Lexer())->tokenize("@class Foo\n  \$name");
    }

    public function testThrowsOnEightSpaceIndentation(): void
    {
        $this->expectException(SyntaxException::class);
        (new Lexer())->tokenize("@class Foo\n        \$name");
    }

    public function testThrowsOnTabAnywhereInLine(): void
    {
        $this->expectException(SyntaxException::class);
        (new Lexer())->tokenize("@class Foo\tBar");
    }

    public function testReturnsLineInstances(): void
    {
        $lines = (new Lexer())->tokenize('@class Foo');
        $this->assertInstanceOf(Line::class, $lines[0]);
    }
}

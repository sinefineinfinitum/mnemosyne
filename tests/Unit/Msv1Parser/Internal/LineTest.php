<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser\Internal;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Internal\Line;

final class LineTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function startsWithCases(): array
    {
        return [
            'matches at' => ['@class Foo', '@', true],
            'matches dollar' => ['$name', '$', true],
            'matches bang' => ['!CONST', '!', true],
            'matches dot' => ['.method()', '.', true],
            'matches tilde' => ['~CASE', '~', true],
            'matches caret' => ['^ClassName', '^', true],
            'matches colon' => [':ReturnType', ':', true],
            'matches ampersand' => ['&$ref', '&', true],
            'matches plus' => ['+public $x', '+', true],
            'matches arrow' => ['>ParentClass', '>', true],
            'no match on plain text' => ['hello world', '@', false],
            'no match on different sigil' => ['$name', '!', false],
        ];
    }

    /**
     * @dataProvider startsWithCases
     */
    public function testStartsWith(string $trimmed, string $prefix, bool $expected): void
    {
        $line = new Line(0, $trimmed, $trimmed, 0);
        $this->assertSame($expected, $line->startsWith($prefix));
    }

    public function testExposesAllConstructorArgs(): void
    {
        $line = new Line(7, '  @class Foo  ', '@class Foo', 2);

        $this->assertSame(7, $line->number);
        $this->assertSame('  @class Foo  ', $line->raw);
        $this->assertSame('@class Foo', $line->trimmed);
        $this->assertSame(2, $line->indentation);
    }

    public function testPropertiesArePublicAndMutable(): void
    {
        $line = new Line(0, 'x', 'x', 0);

        $line->indentation = 4;
        $this->assertSame(4, $line->indentation);
    }
}

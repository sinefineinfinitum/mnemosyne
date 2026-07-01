<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Comparator\HashGenerator;

final class HashGeneratorTest extends TestCase
{
    public function testShortHashReturns12Chars(): void
    {
        $hash = HashGenerator::shortHash('hello');
        $this->assertSame(12, strlen($hash));
    }

    public function testShortHashIsDeterministic(): void
    {
        $this->assertSame(
            HashGenerator::shortHash('same content'),
            HashGenerator::shortHash('same content')
        );
    }

    public function testShortHashDifferentForDifferentInput(): void
    {
        $this->assertNotSame(
            HashGenerator::shortHash('content a'),
            HashGenerator::shortHash('content b')
        );
    }
}

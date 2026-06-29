<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Flyweight;

final class FlyweightTest extends TestCase
{
    private Flyweight $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Flyweight();
    }

    public function testName(): void
    {
        $this->assertSame('flyweight', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Flyweight', 'FlyweightFactory'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

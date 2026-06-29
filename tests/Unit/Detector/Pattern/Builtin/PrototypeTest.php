<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Prototype;

final class PrototypeTest extends TestCase
{
    private Prototype $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Prototype();
    }

    public function testName(): void
    {
        $this->assertSame('prototype', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Prototype'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Bridge;

final class BridgeTest extends TestCase
{
    private Bridge $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Bridge();
    }

    public function testName(): void
    {
        $this->assertSame('bridge', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Abstraction', 'Implementor', 'ConcreteImplementor'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Strategy;

final class StrategyTest extends TestCase
{
    private Strategy $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Strategy();
    }

    public function testName(): void
    {
        $this->assertSame('strategy', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Strategy', 'ConcreteStrategy', 'Context'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

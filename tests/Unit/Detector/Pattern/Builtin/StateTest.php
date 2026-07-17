<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\State;

final class StateTest extends TestCase
{
    private State $pattern;

    protected function setUp(): void
    {
        $this->pattern = new State();
    }

    public function testName(): void
    {
        $this->assertSame('state', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['State', 'ConcreteState', 'Context'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

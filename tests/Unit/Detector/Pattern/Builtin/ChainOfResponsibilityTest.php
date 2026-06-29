<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\ChainOfResponsibility;

final class ChainOfResponsibilityTest extends TestCase
{
    private ChainOfResponsibility $pattern;

    protected function setUp(): void
    {
        $this->pattern = new ChainOfResponsibility();
    }

    public function testName(): void
    {
        $this->assertSame('chain_of_responsibility', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Handler', 'ConcreteHandler'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

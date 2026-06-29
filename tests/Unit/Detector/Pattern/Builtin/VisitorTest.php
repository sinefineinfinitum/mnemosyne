<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Visitor;

final class VisitorTest extends TestCase
{
    private Visitor $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Visitor();
    }

    public function testName(): void
    {
        $this->assertSame('visitor', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Visitor', 'ConcreteVisitor', 'Element', 'ConcreteElement'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

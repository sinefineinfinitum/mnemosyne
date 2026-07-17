<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Composite;

final class CompositeTest extends TestCase
{
    private Composite $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Composite();
    }

    public function testName(): void
    {
        $this->assertSame('composite', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Component', 'Composite'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

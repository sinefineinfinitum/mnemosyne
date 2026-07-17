<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Adapter;

final class AdapterTest extends TestCase
{
    private Adapter $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Adapter();
    }

    public function testName(): void
    {
        $this->assertSame('adapter', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Adapter', 'Target', 'Adaptee'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

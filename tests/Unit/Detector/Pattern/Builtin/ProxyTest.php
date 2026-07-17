<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Proxy;

final class ProxyTest extends TestCase
{
    private Proxy $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Proxy();
    }

    public function testName(): void
    {
        $this->assertSame('proxy', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Subject', 'Proxy'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

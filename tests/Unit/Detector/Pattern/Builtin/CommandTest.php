<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Command;

final class CommandTest extends TestCase
{
    private Command $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Command();
    }

    public function testName(): void
    {
        $this->assertSame('command', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Command', 'ConcreteCommand', 'Invoker'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Memento;

final class MementoTest extends TestCase
{
    private Memento $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Memento();
    }

    public function testName(): void
    {
        $this->assertSame('memento', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Originator', 'Memento'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

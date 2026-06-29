<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Mediator;

final class MediatorTest extends TestCase
{
    private Mediator $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Mediator();
    }

    public function testName(): void
    {
        $this->assertSame('mediator', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Mediator', 'Colleague'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

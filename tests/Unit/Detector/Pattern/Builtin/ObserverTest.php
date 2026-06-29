<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Observer;

final class ObserverTest extends TestCase
{
    private Observer $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Observer();
    }

    public function testName(): void
    {
        $this->assertSame('observer', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Subject', 'Observer', 'ConcreteObserver'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

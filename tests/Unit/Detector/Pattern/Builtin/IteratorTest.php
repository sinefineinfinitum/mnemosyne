<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Iterator;

final class IteratorTest extends TestCase
{
    private Iterator $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Iterator();
    }

    public function testName(): void
    {
        $this->assertSame('iterator', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Iterator', 'ConcreteIterator'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

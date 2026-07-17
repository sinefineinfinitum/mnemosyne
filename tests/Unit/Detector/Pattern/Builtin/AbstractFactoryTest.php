<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\AbstractFactory;

final class AbstractFactoryTest extends TestCase
{
    private AbstractFactory $pattern;

    protected function setUp(): void
    {
        $this->pattern = new AbstractFactory();
    }

    public function testName(): void
    {
        $this->assertSame('abstract_factory', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['AbstractFactory', 'ConcreteFactory', 'Product'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

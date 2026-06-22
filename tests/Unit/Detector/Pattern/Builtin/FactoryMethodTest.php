<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Builtin;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\FactoryMethod;

final class FactoryMethodTest extends TestCase
{
    private FactoryMethod $pattern;

    protected function setUp(): void
    {
        $this->pattern = new FactoryMethod();
    }

    public function testName(): void
    {
        $this->assertSame('factory_method', $this->pattern->name());
    }

    public function testRoles(): void
    {
        $this->assertSame(['Creator', 'ConcreteCreator', 'Product'], $this->pattern->roles());
    }

    public function testCandidateSql(): void
    {
        $this->assertNotEmpty($this->pattern->candidateSql());
    }
}

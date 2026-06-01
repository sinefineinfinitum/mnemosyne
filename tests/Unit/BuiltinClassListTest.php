<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\BuiltinClassList;

final class BuiltinClassListTest extends TestCase
{
    public function testBuiltinClassDetected(): void
    {
        $this->assertTrue(BuiltinClassList::isBuiltin('\DateTime'));
    }

    public function testBuiltinClassWithoutLeadingSlash(): void
    {
        $this->assertTrue(BuiltinClassList::isBuiltin('Exception'));
    }

    public function testBuiltinInterfaceDetected(): void
    {
        $this->assertTrue(BuiltinClassList::isBuiltin('Traversable'));
    }

    public function testUserClassNotBuiltin(): void
    {
        $this->assertFalse(BuiltinClassList::isBuiltin('App\MyClass'));
    }

    public function testVendorClassNotBuiltin(): void
    {
        $this->assertFalse(BuiltinClassList::isBuiltin('Psr\Log\LoggerInterface'));
    }

    public function testVendorClassWithLeadingSlash(): void
    {
        $this->assertFalse(BuiltinClassList::isBuiltin('\Symfony\Component\Console\Application'));
    }
}

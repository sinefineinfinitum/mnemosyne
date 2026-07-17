<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Pipeline;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Engine\PatternRegistry;
use SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Stub\PatternInterfaceStub;

final class PatternRegistryTest extends TestCase
{
    public function testRegisterAndRetrieve(): void
    {
        $registry = new PatternRegistry();
        $pattern = new PatternInterfaceStub('test', ['a']);

        $registry->register($pattern);

        $this->assertSame($pattern, $registry->get('test'));
    }

    public function testGetReturnsNullForUnknown(): void
    {
        $registry = new PatternRegistry();
        $this->assertNull($registry->get('nonexistent'));
    }

    public function testAllReturnsRegisteredPatterns(): void
    {
        $p1 = new PatternInterfaceStub('a', ['x']);
        $p2 = new PatternInterfaceStub('b', ['y']);

        $registry = new PatternRegistry([$p1, $p2]);

        $all = $registry->all();
        $this->assertCount(2, $all);
        $this->assertContains($p1, $all);
        $this->assertContains($p2, $all);
    }

    public function testRegisterOverwritesDuplicate(): void
    {
        $p1 = new PatternInterfaceStub('test', ['a']);
        $p2 = new PatternInterfaceStub('test', ['b']);

        $registry = new PatternRegistry([$p1]);
        $registry->register($p2);

        $this->assertSame($p2, $registry->get('test'));
        $this->assertCount(1, $registry->all());
    }
}

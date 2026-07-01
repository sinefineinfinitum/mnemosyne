<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\Linker\CrossReferenceIndex;

final class CrossReferenceIndexTest extends TestCase
{
    public function testBuildAndQuery(): void
    {
        $index = new CrossReferenceIndex();

        $index->addReference('App\Contracts\ServiceInterface', 'App\Service\UserService');
        $index->addReference('App\Contracts\ServiceInterface', 'App\Service\AdminService');
        $index->freeze(
            [
            'App\Service\UserService',
            'App\Service\AdminService',
            'App\Contracts\ServiceInterface',
            ]
        );

        $usedBy = $index->getUsedBy('App\Contracts\ServiceInterface');
        $this->assertSame(
            ['App\Service\AdminService', 'App\Service\UserService'],
            $usedBy
        );
    }

    public function testEmptyProjectEntityReturnsEmpty(): void
    {
        $index = new CrossReferenceIndex();

        $index->addReference('App\Foo', 'App\Bar');
        $index->freeze(['App\Foo']);

        $this->assertSame([], $index->getUsedBy('App\Foo'));
    }

    public function testVendorReferenceExcluded(): void
    {
        $index = new CrossReferenceIndex();

        $index->addReference('App\Foo', 'Vendor\Something');
        $index->addReference('App\Foo', 'App\Bar');
        $index->freeze(['App\Foo', 'App\Bar']);

        $usedBy = $index->getUsedBy('App\Foo');
        $this->assertSame(['App\Bar'], $usedBy);
    }

    public function testSelfReferenceExcluded(): void
    {
        $index = new CrossReferenceIndex();

        $index->addReference('App\Foo', 'App\Foo');
        $index->freeze(['App\Foo']);

        $this->assertSame([], $index->getUsedBy('App\Foo'));
    }

    public function testFrozenPreventsMutation(): void
    {
        $index = new CrossReferenceIndex();
        $index->freeze();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CrossReferenceIndex is frozen after build');
        $index->addReference('A', 'B');
    }

    public function testGetUsedByForUnknownFqn(): void
    {
        $index = new CrossReferenceIndex();
        $index->freeze();

        $this->assertSame([], $index->getUsedBy('Nonexistent\Class'));
    }

    public function testDeterministicSorting(): void
    {
        $index = new CrossReferenceIndex();

        $index->addReference('App\C', 'App\B');
        $index->addReference('App\C', 'App\A');
        $index->addReference('App\C', 'App\B');
        $index->freeze(['App\C', 'App\B', 'App\A']);

        $this->assertSame(['App\A', 'App\B'], $index->getUsedBy('App\C'));
    }
}

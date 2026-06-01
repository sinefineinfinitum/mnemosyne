<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\Metadata\PackageMetadataProvider;
use SineFine\Ponymator\Analyzer\VendorPackageResolver;
use SineFine\Ponymator\Documentation\Generator\DocLinker;
use SineFine\Ponymator\Filesystem\PathResolver;

final class DocLinkerTest extends TestCase
{
    public function testMapsKnownFqnToMarkdownLink(): void
    {
        $resolver = $this->createMock(PathResolver::class);
        $resolver->method('relativeDocLink')
            ->with('current/Page.md', 'target/Foo.md')
            ->willReturn('../target/Foo.md');

        $linker = new DocLinker(
            ['App\Foo' => 'target/Foo.md'],
            $resolver,
        );

        $result = $linker->mapToLinks(['App\Foo'], 'current/Page.md');

        $this->assertSame(['[App\Foo](../target/Foo.md)'], $result);
    }

    public function testMapsUnknownFqnToCodeSpan(): void
    {
        $resolver = $this->createMock(PathResolver::class);

        $linker = new DocLinker([], $resolver);

        $result = $linker->mapToLinks(['App\Unknown'], 'current/Page.md');

        $this->assertSame(['`App\Unknown`'], $result);
    }

    public function testNormalizesLeadingBackslash(): void
    {
        $resolver = $this->createMock(PathResolver::class);
        $resolver->method('relativeDocLink')
            ->with('current/Page.md', 'target/Foo.md')
            ->willReturn('../target/Foo.md');

        $linker = new DocLinker(
            ['App\Foo' => 'target/Foo.md'],
            $resolver,
        );

        $result = $linker->mapToLinks(['\App\Foo'], 'current/Page.md');

        $this->assertSame(['[App\Foo](../target/Foo.md)'], $result);
    }

    public function testMapsMultipleFqns(): void
    {
        $resolver = $this->createMock(PathResolver::class);
        $resolver->method('relativeDocLink')
            ->willReturnCallback(
                fn(string $from, string $to) => match ($to) {
                'target/Foo.md' => 'Foo.md',
                'target/Bar.md' => 'Bar.md',
                }
            );

        $linker = new DocLinker(
            [
                'App\Foo' => 'target/Foo.md',
                'App\Bar' => 'target/Bar.md',
            ],
            $resolver,
        );

        $result = $linker->mapToLinks(['App\Foo', 'App\Baz', 'App\Bar'], 'current/Page.md');

        $this->assertSame(
            ['[App\Foo](Foo.md)', '`App\Baz`', '[App\Bar](Bar.md)'],
            $result,
        );
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        $resolver = $this->createMock(PathResolver::class);

        $linker = new DocLinker([], $resolver);

        $this->assertSame([], $linker->mapToLinks([], 'any/Page.md'));
    }

    public function testMapsVendorFqnToPackageLink(): void
    {
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->method('relativeDocLink')
            ->with('src/Service/UserService.md', 'vendor.md')
            ->willReturn('../vendor.md');

        $vendorResolver = $this->createVendorResolverWithPackage('psr/log', '3.0.2', 'Common interface');

        $linker = new DocLinker([], $pathResolver, $vendorResolver);

        $result = $linker->mapToLinks(['Psr\Log\LoggerInterface'], 'src/Service/UserService.md');

        $this->assertSame(['[Psr\Log\LoggerInterface](../vendor.md)'], $result);
    }

    public function testVendorFqnTakesPriorityOverPlainCode(): void
    {
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->method('relativeDocLink')
            ->willReturnCallback(fn($from, $to) => match ($to) {
                'src/App/Foo.md' => 'Foo.md',
                'vendor.md' => '../vendor.md',
                default => $to,
            });

        $vendorResolver = $this->createVendorResolverWithPackage('psr/log', '3.0.2', 'Common interface');

        $linker = new DocLinker(
            ['App\Foo' => 'src/App/Foo.md'],
            $pathResolver,
            $vendorResolver,
        );

        $result = $linker->mapToLinks(
            ['App\Foo', 'Psr\Log\LoggerInterface'],
            'current/Page.md',
        );

        $this->assertSame(
            ['[App\Foo](Foo.md)', '[Psr\Log\LoggerInterface](../vendor.md)'],
            $result,
        );
    }

    public function testUnresolvedExternalFqnStillShowsAsPlainCode(): void
    {
        $pathResolver = $this->createMock(PathResolver::class);
        $tempDir = sys_get_temp_dir() . '/dlt-' . uniqid();
        mkdir($tempDir . '/vendor', 0777, true);

        $provider = new PackageMetadataProvider($tempDir);
        $vendorResolver = new VendorPackageResolver([], $provider, $tempDir);
        $this->rmdir($tempDir);

        $linker = new DocLinker([], $pathResolver, $vendorResolver);

        $result = $linker->mapToLinks(['Unknown\Vendor\Class'], 'current/Page.md');

        $this->assertSame(['`Unknown\Vendor\Class`'], $result);
    }

    public function testVendorLinkUsesFqnAsText(): void
    {
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->method('relativeDocLink')
            ->with('Page.md', 'vendor.md')
            ->willReturn('vendor.md');

        $vendorResolver = $this->createVendorResolverWithPackage('symfony/console', '6.4.0', 'Console component');

        $linker = new DocLinker([], $pathResolver, $vendorResolver);

        $result = $linker->mapToLinks(
            ['Symfony\Component\Console\Application'],
            'Page.md',
        );

        $this->assertSame(['[Symfony\Component\Console\Application](vendor.md)'], $result);
    }

    public function testVendorLinkNoAnchorFragment(): void
    {
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->method('relativeDocLink')
            ->with('Page.md', 'vendor.md')
            ->willReturn('vendor.md');

        $vendorResolver = $this->createVendorResolverWithPackage('doctrine/orm', '2.19.0', 'ORM');

        $linker = new DocLinker([], $pathResolver, $vendorResolver);

        $result = $linker->mapToLinks(
            ['Doctrine\ORM\EntityManager'],
            'Page.md',
        );

        $this->assertSame(['[Doctrine\ORM\EntityManager](vendor.md)'], $result);
        $this->assertStringNotContainsString('#', $result[0]);
    }

    /**
     * @return VendorPackageResolver
     */
    private function createVendorResolverWithPackage(string $packageName, string $version, string $description): VendorPackageResolver
    {
        $tempDir = sys_get_temp_dir() . '/dlt-' . uniqid();
        $vendorDir = $tempDir . '/vendor/' . $packageName;
        mkdir($vendorDir, 0777, true);

        $nsParts = explode('/', $packageName);
        $nsPrefix = '';
        if ($packageName === 'psr/log') {
            $nsPrefix = 'Psr\\Log\\';
        } elseif ($packageName === 'symfony/console') {
            $nsPrefix = 'Symfony\\Component\\Console\\';
        } elseif ($packageName === 'doctrine/orm') {
            $nsPrefix = 'Doctrine\\ORM\\';
        }

        file_put_contents(
            $vendorDir . '/composer.json',
            json_encode([
                'autoload' => ['psr-4' => [$nsPrefix => '']],
            ], JSON_UNESCAPED_SLASHES)
        );

        file_put_contents(
            $tempDir . '/composer.lock',
            json_encode([
                'packages' => [
                    ['name' => $packageName, 'version' => 'v' . $version, 'description' => $description],
                ],
                'packages-dev' => [],
            ], JSON_UNESCAPED_SLASHES)
        );

        $provider = new PackageMetadataProvider($tempDir);
        $resolver = new VendorPackageResolver([$packageName], $provider, $tempDir);

        $this->tempDirsForCleanup[] = $tempDir;

        return $resolver;
    }

    /** @var string[] */
    private array $tempDirsForCleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirsForCleanup as $dir) {
            $this->rmdir($dir);
        }
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}

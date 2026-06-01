<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\Metadata\PackageMetadataProvider;
use SineFine\Ponymator\Analyzer\VendorPackageResolver;

final class VendorPackageResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator-vpr-' . uniqid();
        mkdir($this->tempDir . '/vendor', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
    }

    private function writeComposerJson(string $package, array $autoload, ?string $description = null): void
    {
        $path = $this->tempDir . '/vendor/' . $package;
        mkdir($path, 0777, true);
        $data = ['autoload' => $autoload];
        if ($description !== null) {
            $data['description'] = $description;
        }
        file_put_contents(
            $path . '/composer.json',
            json_encode($data, JSON_UNESCAPED_SLASHES)
        );
    }

    public function testResolveByPsr4Prefix(): void
    {
        $this->writeComposerJson('psr/log', [
            'psr-4' => ['Psr\\Log\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $resolver = new VendorPackageResolver(['psr/log'], $provider, $this->tempDir);

        $this->assertSame('psr/log', $resolver->resolve('Psr\Log\LoggerInterface'));
    }

    public function testResolveByPsr0Prefix(): void
    {
        $this->writeComposerJson('symfony/console', [
            'psr-0' => ['Symfony\\Component\\Console\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $resolver = new VendorPackageResolver(['symfony/console'], $provider, $this->tempDir);

        $this->assertSame('symfony/console', $resolver->resolve('Symfony\Component\Console\Application'));
    }

    public function testLongestPrefixMatch(): void
    {
        $this->writeComposerJson('doctrine/orm', [
            'psr-4' => ['Doctrine\\ORM\\' => ''],
        ]);
        $this->writeComposerJson('doctrine/common', [
            'psr-4' => ['Doctrine\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $resolver = new VendorPackageResolver(['doctrine/orm', 'doctrine/common'], $provider, $this->tempDir);

        $this->assertSame('doctrine/orm', $resolver->resolve('Doctrine\ORM\EntityManager'));
        $this->assertSame('doctrine/common', $resolver->resolve('Doctrine\Common\Collections\Collection'));
    }

    public function testBuiltinClassReturnsNull(): void
    {
        $this->writeComposerJson('psr/log', [
            'psr-4' => ['Psr\\Log\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $resolver = new VendorPackageResolver(['psr/log'], $provider, $this->tempDir);

        $this->assertNull($resolver->resolve('\DateTime'));
        $this->assertNull($resolver->resolve('Exception'));
    }

    public function testUnresolvedFqnReturnsNull(): void
    {
        $this->writeComposerJson('psr/log', [
            'psr-4' => ['Psr\\Log\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $resolver = new VendorPackageResolver(['psr/log'], $provider, $this->tempDir);

        $this->assertNull($resolver->resolve('Some\Vendor\UnknownClass'));
    }

    public function testGetPackageInfoFromLock(): void
    {
        file_put_contents(
            $this->tempDir . '/composer.lock',
            json_encode([
                'packages' => [
                    [
                        'name' => 'psr/log',
                        'version' => 'v3.0.2',
                        'description' => 'Common interface for logging libraries',
                    ],
                ],
                'packages-dev' => [],
            ], JSON_UNESCAPED_SLASHES)
        );
        $this->writeComposerJson('psr/log', [
            'psr-4' => ['Psr\\Log\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $info = $provider->getPackageInfo('psr/log');

        $this->assertSame('3.0.2', $info['version']);
        $this->assertSame('Common interface for logging libraries', $info['description']);
    }

    public function testGetPackageInfoFromVendorJsonFallback(): void
    {
        $this->writeComposerJson(
            'psr/log',
            ['psr-4' => ['Psr\\Log\\' => '']],
            'Fallback description'
        );

        $provider = new PackageMetadataProvider($this->tempDir);
        $info = $provider->getPackageInfo('psr/log');

        $this->assertSame('unknown', $info['version']);
        $this->assertSame('Fallback description', $info['description']);
    }

    public function testGetPackageInfoUnknown(): void
    {
        $provider = new PackageMetadataProvider($this->tempDir);
        $info = $provider->getPackageInfo('nonexistent/pkg');

        $this->assertSame('unknown', $info['version']);
        $this->assertSame('unknown', $info['description']);
    }

    public function testGetShortName(): void
    {
        $this->writeComposerJson('psr/log', [
            'psr-4' => ['Psr\\Log\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $resolver = new VendorPackageResolver(['psr/log'], $provider, $this->tempDir);

        $this->assertSame('LoggerInterface', $resolver->getShortName('Psr\Log\LoggerInterface'));
        $this->assertSame('Application', $resolver->getShortName('Symfony\Component\Console\Application'));
    }

    public function testGetPrefixMap(): void
    {
        $this->writeComposerJson('psr/log', [
            'psr-4' => ['Psr\\Log\\' => ''],
        ]);

        $provider = new PackageMetadataProvider($this->tempDir);
        $resolver = new VendorPackageResolver(['psr/log'], $provider, $this->tempDir);

        $map = $resolver->getPrefixMap();
        $this->assertArrayHasKey('Psr\Log\\', $map);
        $this->assertSame('psr/log', $map['Psr\Log\\']);
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

<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\BuiltinClassList;
use SineFine\Ponymator\Analyzer\Link\CrossReferenceContext;
use SineFine\Ponymator\Analyzer\Link\CrossReferenceIndex;
use SineFine\Ponymator\Analyzer\Metadata\PackageMetadataProvider;
use SineFine\Ponymator\Analyzer\VendorPackageResolver;
use SineFine\Ponymator\Documentation\Generator\VendorIndexGenerator;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;

final class VendorIndexGeneratorTest extends TestCase
{
    private string $tempDir;
    private MarkdownBuilder $builder;
    private VendorPackageResolver $resolver;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator-vig-' . uniqid();
        mkdir($this->tempDir . '/vendor/psr/log', 0777, true);
        mkdir($this->tempDir . '/vendor/symfony/console', 0777, true);

        file_put_contents(
            $this->tempDir . '/vendor/psr/log/composer.json',
            json_encode([
                'autoload' => ['psr-4' => ['Psr\\Log\\' => '']],
            ], JSON_UNESCAPED_SLASHES)
        );
        file_put_contents(
            $this->tempDir . '/vendor/symfony/console/composer.json',
            json_encode([
                'autoload' => ['psr-4' => ['Symfony\\Component\\Console\\' => '']],
            ], JSON_UNESCAPED_SLASHES)
        );

        file_put_contents(
            $this->tempDir . '/composer.lock',
            json_encode([
                'packages' => [
                    ['name' => 'psr/log', 'version' => 'v3.0.2', 'description' => 'Common interface'],
                    ['name' => 'symfony/console', 'version' => 'v6.4.0', 'description' => 'Console component'],
                ],
                'packages-dev' => [],
            ], JSON_UNESCAPED_SLASHES)
        );

        $this->builder = new MarkdownBuilder();
        $provider = new PackageMetadataProvider($this->tempDir);
        $this->resolver = new VendorPackageResolver(
            ['psr/log', 'symfony/console'],
            $provider,
            $this->tempDir
        );
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
    }

    public function testEmptyVendorIndex(): void
    {
        $index = new CrossReferenceIndex();
        $index->freeze([]);
        $context = new CrossReferenceContext($index, []);

        $generator = new VendorIndexGenerator($this->builder, $this->resolver);
        $output = $generator->generate($context);

        $this->assertStringContainsString('No external packages are referenced by this project.', $output);
    }

    public function testSinglePackageVendorIndex(): void
    {
        $index = new CrossReferenceIndex();
        $index->addReference('Psr\Log\LoggerInterface', 'App\MyClass');
        $index->freeze(['App\MyClass']);

        $context = new CrossReferenceContext($index, []);
        $generator = new VendorIndexGenerator($this->builder, $this->resolver);
        $output = $generator->generate($context);

        $this->assertStringContainsString('# Vendor Packages', $output);
        $this->assertStringContainsString('|Package|Version|Description|Referenced Classes|', $output);
        $this->assertStringContainsString('|psr/log|3.0.2|Common interface|LoggerInterface|', $output);
    }

    public function testMultiplePackagesSortedAlphabetically(): void
    {
        $index = new CrossReferenceIndex();
        $index->addReference('Psr\Log\LoggerInterface', 'App\MyClass');
        $index->addReference('Symfony\Component\Console\Application', 'App\MyCommand');
        $index->freeze(['App\MyClass', 'App\MyCommand']);

        $context = new CrossReferenceContext($index, []);
        $generator = new VendorIndexGenerator($this->builder, $this->resolver);
        $output = $generator->generate($context);

        $psrPos = strpos($output, '|psr/log|');
        $symfonyPos = strpos($output, '|symfony/console|');

        $this->assertNotFalse($psrPos);
        $this->assertNotFalse($symfonyPos);
        $this->assertLessThan($symfonyPos, $psrPos, 'psr/log row should appear before symfony/console row');
    }

    public function testBuiltinClassExcluded(): void
    {
        $index = new CrossReferenceIndex();
        $index->addReference('\DateTime', 'App\MyClass');
        $index->addReference('Psr\Log\LoggerInterface', 'App\MyClass');
        $index->freeze(['App\MyClass']);

        $context = new CrossReferenceContext($index, []);
        $generator = new VendorIndexGenerator($this->builder, $this->resolver);
        $output = $generator->generate($context);

        $this->assertStringContainsString('LoggerInterface', $output);
        $this->assertStringNotContainsString('DateTime', $output);
    }

    public function testUnresolvedFqnExcluded(): void
    {
        $index = new CrossReferenceIndex();
        $index->addReference('Unknown\Vendor\Class', 'App\MyClass');
        $index->freeze(['App\MyClass']);

        $context = new CrossReferenceContext($index, []);
        $generator = new VendorIndexGenerator($this->builder, $this->resolver);
        $output = $generator->generate($context);

        $this->assertStringContainsString('No external packages are referenced', $output);
    }

    public function testDeterministicOutput(): void
    {
        $index = new CrossReferenceIndex();
        $index->addReference('Psr\Log\LoggerInterface', 'App\MyClass');
        $index->addReference('Symfony\Component\Console\Application', 'App\MyCommand');
        $index->addReference('Symfony\Component\Console\Input\InputInterface', 'App\MyCommand');
        $index->freeze(['App\MyClass', 'App\MyCommand']);

        $context = new CrossReferenceContext($index, []);
        $generator = new VendorIndexGenerator($this->builder, $this->resolver);

        $first = $generator->generate($context);
        $second = $generator->generate($context);

        $this->assertSame($first, $second);
    }

    public function testClassesSortedWithinPackage(): void
    {
        $index = new CrossReferenceIndex();
        $index->addReference('Symfony\Component\Console\Output\OutputInterface', 'App\MyCommand');
        $index->addReference('Symfony\Component\Console\Application', 'App\MyCommand');
        $index->addReference('Symfony\Component\Console\Input\InputInterface', 'App\MyCommand');
        $index->freeze(['App\MyCommand']);

        $context = new CrossReferenceContext($index, []);
        $generator = new VendorIndexGenerator($this->builder, $this->resolver);
        $output = $generator->generate($context);

        $this->assertStringContainsString('Application, InputInterface, OutputInterface', $output);
    }

    public function testDeduplicatesClassNames(): void
    {
        $index = new CrossReferenceIndex();
        $index->addReference('Psr\Log\LoggerInterface', 'App\ClassA');
        $index->addReference('Psr\Log\LoggerInterface', 'App\ClassB');
        $index->freeze(['App\ClassA', 'App\ClassB']);

        $context = new CrossReferenceContext($index, []);
        $generator = new VendorIndexGenerator($this->builder, $this->resolver);
        $output = $generator->generate($context);

        $this->assertStringContainsString('LoggerInterface', $output);
        $this->assertStringNotContainsString('LoggerInterface, LoggerInterface', $output);
    }

    public function testMarkdownBuilderVendorIndexEmpty(): void
    {
        $output = $this->builder->vendorIndexEmpty();
        $this->assertStringContainsString('# Vendor Packages', $output);
        $this->assertStringContainsString('No external packages are referenced', $output);
    }

    public function testMarkdownBuilderVendorIndex(): void
    {
        $packages = [
            [
                'package' => 'psr/log',
                'version' => '3.0.2',
                'description' => 'Common interface',
                'classes' => ['LoggerInterface', 'LoggerTrait'],
            ],
        ];

        $output = $this->builder->vendorIndex('Vendor Packages', $packages);
        $this->assertStringContainsString('# Vendor Packages', $output);
        $this->assertStringContainsString('|Package|Version|Description|Referenced Classes|', $output);
        $this->assertStringContainsString('|psr/log|3.0.2|Common interface|LoggerInterface, LoggerTrait|', $output);
        $this->assertStringNotContainsString('##', $output);
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

<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\DependencyAnalyzer;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Config;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Generator\FileDocumenter;
use SineFine\Ponymator\Documentation\Generator\MarkdownGenerator;
use SineFine\Ponymator\Documentation\Renderer\ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\TraitRenderer;
use SineFine\Ponymator\Filesystem\PathResolver;
use SineFine\Ponymator\Filesystem\Scanner;

final class EdgeCaseTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponimator-edge-test-' . uniqid();
        $this->sourceDir = $this->tempDir . '/src';
        $this->targetDir = $this->tempDir . '/docs';
        mkdir($this->sourceDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
    }

    private function makeConfig(): Config
    {
        $config = new Config(null);
        $ref = new \ReflectionProperty(Config::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(
            $config, [
            'source' => $this->sourceDir,
            'target' => $this->targetDir,
            'ignore' => ['vendor', 'tests'],
            ]
        );
        return $config;
    }

    private function makeGenerator(Config $config): MarkdownGenerator
    {
        $parser = new Parser();
        $entityExtractor = new EntityExtractor();
        $fileExtractor = new FileExtractor();
        $dependencyAnalyzer = new DependencyAnalyzer();
        $builder = new MarkdownBuilder();
        $classRenderer = new ClassRenderer($builder);
        $interfaceRenderer = new InterfaceRenderer($builder);
        $traitRenderer = new TraitRenderer($builder);
        $enumRenderer = new EnumRenderer($builder);
        $fileRenderer = new FileRenderer($builder);
        $hashComparator = new HashComparator();
        $pathResolver = new PathResolver($config);
        $documenter = new FileDocumenter(
            $parser,
            $entityExtractor,
            $fileExtractor,
            $dependencyAnalyzer,
            [
                $classRenderer,
                $interfaceRenderer,
                $traitRenderer,
                $enumRenderer,
            ],
            $fileRenderer,
            $hashComparator,
            $pathResolver,
        );
        $documentRemover = new OutdatedDocumentationRemover($pathResolver);

        return new MarkdownGenerator(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
        );
    }

    public function testEmptySourceDir(): void
    {
        $scanner = new Scanner($this->sourceDir);
        $files = $scanner->scan();
        $this->assertSame([], $files);

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $generator->generateFull($files);

        $this->assertDirectoryExists($this->targetDir);
        $this->assertSame([], glob($this->targetDir . '/*.md'));
    }

    public function testNoPublicEntities(): void
    {
        file_put_contents(
            $this->sourceDir . '/Hidden.php', '<?php
namespace App;

class Hidden {
    protected function internal(): void {}
    private function secret(): void {}
}'
        );

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir);
        $files = $scanner->scan();
        $generator->generateFull($files);

        $this->assertFileExists($this->targetDir . '/Hidden.md');
        $content = file_get_contents($this->targetDir . '/Hidden.md');

        $this->assertStringContainsString('type: class', $content);
        $this->assertStringNotContainsString('API', $content);
    }

    public function testMalformedConfig(): void
    {
        $badDir = $this->tempDir . '/badconfig';
        mkdir($badDir);
        file_put_contents($badDir . '/.ponimator.json', 'not valid json {{{');

        $config = new Config($badDir . '/.ponimator.json');

        $this->assertSame('src', $config->getSource());
    }

    public function testNonDocFilesPreserved(): void
    {
        mkdir($this->targetDir, 0755, true);
        file_put_contents($this->targetDir . '/readme.txt', 'keep me');
        file_put_contents($this->targetDir . '/image.png', 'fake png');

        file_put_contents($this->sourceDir . '/A.php', '<?php namespace App; class A {}');

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $files = ['A.php'];

        $generator->generateFull($files);
        $generator->generateDiff([]);

        $this->assertFileExists($this->targetDir . '/readme.txt');
        $this->assertFileExists($this->targetDir . '/image.png');
    }

    public function testSyntaxErrorSkipped(): void
    {
        file_put_contents($this->sourceDir . '/broken.php', '<?php namespace App; class Broken { public function oops(');

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir);
        $files = $scanner->scan();
        $result = $generator->generateFull($files);

        $this->assertFileDoesNotExist($this->targetDir . '/broken.md');
        $this->assertSame(1, $result->getSkipped());
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

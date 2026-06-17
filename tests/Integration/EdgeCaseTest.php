<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\EntityAnalyzer;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Config;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Linker\CrossReferenceFactory;
use SineFine\Ponymator\Documentation\Generator\Engine;
use SineFine\Ponymator\Documentation\Generator\PageGenerator;
use SineFine\Ponymator\Documentation\Renderer\Markdown\ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\Markdown\TraitRenderer;
use SineFine\Ponymator\Filesystem\PathResolver;
use SineFine\Ponymator\Filesystem\Scanner;

final class EdgeCaseTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator-edge-test-' . uniqid();
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

    private function makeGenerator(Config $config): Engine
    {
        $parser = new Parser();
        $combinedAnalyzer = new EntityAnalyzer();
        $fileExtractor = new FileExtractor();
        $builder = new MarkdownBuilder();
        $classRenderer = new ClassRenderer($builder);
        $interfaceRenderer = new InterfaceRenderer($builder);
        $traitRenderer = new TraitRenderer($builder);
        $enumRenderer = new EnumRenderer($builder);
        $fileRenderer = new FileRenderer($builder);
        $hashComparator = new HashComparator();
        $pathResolver = new PathResolver($config);
        $crossReferenceFactory = new CrossReferenceFactory($pathResolver);
        $indexBuilder = new CrossReferenceIndexBuilder($parser, $pathResolver);
        $documenter = new PageGenerator(
            $parser,
            $combinedAnalyzer,
            $fileExtractor,
            [
                $classRenderer,
                $interfaceRenderer,
                $traitRenderer,
                $enumRenderer,
            ],
            $fileRenderer,
            $crossReferenceFactory,
        );
        $documentRemover = new OutdatedDocumentationRemover($pathResolver);

        return new Engine(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
            $indexBuilder
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

    public function testEntityWithGlobalFunction(): void
    {
        file_put_contents(
            $this->sourceDir . '/Service.php', '<?php

class Service {
    public function process(): void {}
}

function helper(string $name): string {
    return "Hello, $name!";
}'
        );

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir);
        $files = $scanner->scan();
        $generator->generateFull($files);

        $this->assertFileExists($this->targetDir . '/Service.md');
        $content = file_get_contents($this->targetDir . '/Service.md');

        $this->assertStringContainsString('type: class', $content);
        $this->assertStringContainsString('`Service`', $content);
        $this->assertStringContainsString('process', $content);
        $this->assertStringContainsString('Global functions', $content);
        $this->assertStringContainsString('helper', $content);
    }

    public function testEntityWithGlobalConstants(): void
    {
        file_put_contents(
            $this->sourceDir . '/Config.php', '<?php

class Config {
    public function get(string $key): mixed {}
}

define("APP_NAME", "Ponymator");
define("APP_VERSION", "1.0");'
        );

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir);
        $files = $scanner->scan();
        $generator->generateFull($files);

        $this->assertFileExists($this->targetDir . '/Config.md');
        $content = file_get_contents($this->targetDir . '/Config.md');

        $this->assertStringContainsString('type: class', $content);
        $this->assertStringContainsString('Global constants', $content);
        $this->assertStringContainsString('APP_NAME', $content);
        $this->assertStringContainsString('APP_VERSION', $content);
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

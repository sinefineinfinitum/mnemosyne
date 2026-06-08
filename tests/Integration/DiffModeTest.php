<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\EntityAnalyzer;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Linker\CrossReferenceFactory;
use SineFine\Ponymator\Documentation\Processor\DocumentationProcessor;
use SineFine\Ponymator\Documentation\Processor\PageGenerator;
use SineFine\Ponymator\Documentation\Renderer\Markdown\ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\Markdown\TraitRenderer;
use SineFine\Ponymator\Filesystem\PathResolver;

final class DiffModeTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator-diff-test-' . uniqid();
        $this->sourceDir = $this->tempDir . '/src';
        $this->targetDir = $this->tempDir . '/docs';
        mkdir($this->sourceDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
    }

    public function testDiffOnlyRegeneratesChangedFile(): void
    {
        file_put_contents($this->sourceDir . '/A.php', '<?php namespace App; class A {}');
        file_put_contents($this->sourceDir . '/B.php', '<?php namespace App; class B {}');

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $files = ['A.php', 'B.php'];

        $generator->generateFull($files);
        $this->assertFileExists($this->targetDir . '/A.md');

        $origATime = filemtime($this->targetDir . '/A.md');
        $origBTime = filemtime($this->targetDir . '/B.md');

        sleep(1);

        file_put_contents($this->sourceDir . '/A.php', '<?php namespace App; class A { public function modified(): void {} }');

        $generator2 = $this->makeGenerator($config);
        $generator2->generateDiff($files);

        $this->assertNotSame($origATime, filemtime($this->targetDir . '/A.md'), 'A.md should be regenerated');
        $this->assertSame($origBTime, filemtime($this->targetDir . '/B.md'), 'B.md should stay unchanged');
    }

    public function testDiffDoesNotRegenerateUnchangedFiles(): void
    {
        file_put_contents($this->sourceDir . '/C.php', '<?php namespace App; class C {}');

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $files = ['C.php'];

        $generator->generateFull($files);
        $origTime = filemtime($this->targetDir . '/C.md');

        sleep(1);

        $generator2 = $this->makeGenerator($config);
        $generator2->generateDiff($files);

        $this->assertSame($origTime, filemtime($this->targetDir . '/C.md'));
    }

    public function testDiffRemovesStaleDocForDeletedFile(): void
    {
        file_put_contents($this->sourceDir . '/D.php', '<?php namespace App; class D {}');

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $files = ['D.php'];

        $generator->generateFull($files);
        $this->assertFileExists($this->targetDir . '/D.md');

        $generator2 = $this->makeGenerator($config);
        $generator2->generateDiff([]);

        $this->assertFileDoesNotExist($this->targetDir . '/D.md');
    }

    private function makeConfig(): object
    {
        $config = new \SineFine\Ponymator\Config(null);
        $ref = new \ReflectionProperty(\SineFine\Ponymator\Config::class, 'config');
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

    private function makeGenerator(object $config): DocumentationProcessor
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

        return new DocumentationProcessor(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
            $indexBuilder
        );
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

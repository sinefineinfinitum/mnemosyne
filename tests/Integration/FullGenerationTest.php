<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use SineFine\Ponymator\Analyzer\EntityAnalyzer;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Analyzer\CallAnalyzer;
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

final class FullGenerationTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator-test-' . uniqid();
        $this->sourceDir = $this->tempDir . '/src';
        $this->targetDir = $this->tempDir . '/docs';

        mkdir($this->sourceDir . '/Service', 0755, true);
        mkdir($this->sourceDir . '/Sub', 0755, true);

        file_put_contents(
            $this->sourceDir . '/Service/UserService.php', '<?php
namespace App\Service;

class UserService {
    public function findById(int $id): ?\App\Models\User { return null; }
}'
        );

        file_put_contents(
            $this->sourceDir . '/Sub/Helper.php', '<?php
namespace App\Sub;

interface Helper {}'
        );
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
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
            new CallAnalyzer(),
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

    public function testFullGenerationCreatesMirrorStructure(): void
    {
        $config = new Config(null);
        $ref = new ReflectionProperty(Config::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(
            $config, [
            'source' => $this->sourceDir,
            'target' => $this->targetDir,
            'ignore' => ['vendor', 'tests'],
            ]
        );

        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir, ['vendor', 'tests']);
        $files = $scanner->scan();
        $this->assertCount(2, $files, 'Scanner should find 2 PHP files');

        $generator->generateFull($files);

        $this->assertFileExists($this->targetDir . '/Service/UserService.md');
        $this->assertFileExists($this->targetDir . '/Sub/Helper.md');

        $content = file_get_contents($this->targetDir . '/Service/UserService.md');
        $this->assertStringContainsString('type: class', $content);
        $this->assertStringContainsString('`App\Service\UserService`', $content);
        $this->assertStringContainsString('findById', $content);
    }

    public function testFullGenerationOutputDeterministic(): void
    {
        $config = new Config(null);
        $ref = new ReflectionProperty(Config::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(
            $config, [
            'source' => $this->sourceDir,
            'target' => $this->targetDir,
            'ignore' => ['vendor', 'tests'],
            ]
        );

        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir, ['vendor', 'tests']);
        $files = $scanner->scan();

        $generator->generateFull($files);
        $first = file_get_contents($this->targetDir . '/Service/UserService.md');

        $generator->generateFull($files);
        $second = file_get_contents($this->targetDir . '/Service/UserService.md');

        $this->assertSame($first, $second, 'Re-running full generation should produce identical output');
    }

    public function testFullGenerationIncludesCallGraphInOutput(): void
    {
        file_put_contents(
            $this->sourceDir . '/Service/UserService.php', '<?php
namespace App\Service;

class UserService {
    public function build(): void {
        \App\Registry::lookup();
        strlen("a");
        $obj = new \App\Models\User();
        $obj->save();
    }
}'
        );

        $config = new Config(null);
        $ref = new ReflectionProperty(Config::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(
            $config, [
            'source' => $this->sourceDir,
            'target' => $this->targetDir,
            'ignore' => ['vendor', 'tests'],
            ]
        );

        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir, ['vendor', 'tests']);
        $files = $scanner->scan();
        $generator->generateFull($files);

        $content = file_get_contents($this->targetDir . '/Service/UserService.md');
        $this->assertStringContainsString('**Calls:**', $content);
    }

    public function testFullGenerationCallGraphDeterministic(): void
    {
        file_put_contents(
            $this->sourceDir . '/Service/UserService.php', '<?php
namespace App\Service;

class UserService {
    public function build(): void {
        \App\Registry::lookup();
        strlen("a");
        $obj = new \App\Models\User();
        $obj->save();
    }
}'
        );

        $config = new Config(null);
        $ref = new ReflectionProperty(Config::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(
            $config, [
            'source' => $this->sourceDir,
            'target' => $this->targetDir,
            'ignore' => ['vendor', 'tests'],
            ]
        );

        $generator = $this->makeGenerator($config);

        $scanner = new Scanner($this->sourceDir, ['vendor', 'tests']);
        $files = $scanner->scan();

        $generator->generateFull($files);
        $first = file_get_contents($this->targetDir . '/Service/UserService.md');

        $generator->generateFull($files);
        $second = file_get_contents($this->targetDir . '/Service/UserService.md');

        $this->assertSame($first, $second, 'Call graph output must be byte-identical across runs');
        $this->assertStringContainsString('**Calls:**', $first);
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}

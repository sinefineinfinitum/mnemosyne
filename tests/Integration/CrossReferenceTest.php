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

final class CrossReferenceTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponimator-crossref-' . uniqid();
        $this->sourceDir = $this->tempDir . '/src';
        $this->targetDir = $this->tempDir . '/docs';

        mkdir($this->sourceDir . '/Contracts', 0755, true);
        mkdir($this->sourceDir . '/Service', 0755, true);
        mkdir($this->sourceDir . '/Traits', 0755, true);

        file_put_contents(
            $this->sourceDir . '/Contracts/ServiceInterface.php',
            '<?php namespace App\Contracts; interface ServiceInterface { public function doSomething(): void; }'
        );
        file_put_contents(
            $this->sourceDir . '/Service/UserService.php',
            '<?php namespace App\Service; class UserService implements \App\Contracts\ServiceInterface { public function doSomething(): void {} }'
        );
        file_put_contents(
            $this->sourceDir . '/Traits/LoggableTrait.php',
            '<?php namespace App\Traits; trait LoggableTrait { public function log(): void {} }'
        );
        file_put_contents(
            $this->sourceDir . '/Service/AdminService.php',
            '<?php namespace App\Service; class AdminService { use \App\Traits\LoggableTrait; public function doStuff(): void {} }'
        );
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
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
            [$classRenderer, $interfaceRenderer, $traitRenderer, $enumRenderer],
            $fileRenderer,
            $hashComparator,
            $pathResolver,
        );
        $documentRemover = new OutdatedDocumentationRemover($pathResolver);

        return new MarkdownGenerator($hashComparator, $pathResolver, $documenter, $documentRemover);
    }

    public function testFullGenerationIncludesKnownImplementations(): void
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

        $generator = $this->makeGenerator($config);
        $scanner = new Scanner($this->sourceDir, ['vendor', 'tests']);
        $files = $scanner->scan();

        $generator->generateFull($files);

        $this->assertFileExists($this->targetDir . '/Contracts/ServiceInterface.md');
        $this->assertFileExists($this->targetDir . '/Service/UserService.md');
        $this->assertFileExists($this->targetDir . '/Traits/LoggableTrait.md');
        $this->assertFileExists($this->targetDir . '/Service/AdminService.md');

        $interfaceDoc = file_get_contents($this->targetDir . '/Contracts/ServiceInterface.md');
        $this->assertStringContainsString('Implementations', $interfaceDoc);
        $this->assertStringContainsString('App\Service\UserService', $interfaceDoc);

        $traitDoc = file_get_contents($this->targetDir . '/Traits/LoggableTrait.md');
        $this->assertStringContainsString('Used by', $traitDoc);
        $this->assertStringContainsString('App\Service\AdminService', $traitDoc);
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

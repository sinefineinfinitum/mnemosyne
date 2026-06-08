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
use SineFine\Ponymator\Documentation\Processor\DocumentationProcessor;
use SineFine\Ponymator\Documentation\Processor\PageGenerator;
use SineFine\Ponymator\Documentation\Renderer\Markdown\ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\Markdown\TraitRenderer;
use SineFine\Ponymator\Filesystem\PathResolver;
use SineFine\Ponymator\Filesystem\Scanner;

final class CrossReferenceTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator-crossref-' . uniqid();
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

    private function makeGenerator(Config $config): DocumentationProcessor
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
            [$classRenderer, $interfaceRenderer, $traitRenderer, $enumRenderer],
            $fileRenderer,
            $crossReferenceFactory,
        );
        $documentRemover = new OutdatedDocumentationRemover($pathResolver);

        return new DocumentationProcessor($hashComparator, $pathResolver, $documenter, $documentRemover, $indexBuilder);
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
        $this->assertStringContainsString('Used By', $interfaceDoc);
        $this->assertStringContainsString('[App\Service\UserService](../Service/UserService.md)', $interfaceDoc);

        $traitDoc = file_get_contents($this->targetDir . '/Traits/LoggableTrait.md');
        $this->assertStringContainsString('Used By', $traitDoc);
        $this->assertStringContainsString('[App\Service\AdminService](../Service/AdminService.md)', $traitDoc);
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

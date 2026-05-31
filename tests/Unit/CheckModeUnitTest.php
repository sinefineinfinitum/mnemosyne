<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\DependencyAnalyzer;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\FreshnessChecker;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Config;
use SineFine\Ponymator\Documentation\Generator\FileDocumenter;
use SineFine\Ponymator\Documentation\Generator\MarkdownGenerator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Renderer\ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\TraitRenderer;
use SineFine\Ponymator\Filesystem\PathResolver;

final class CheckModeUnitTest extends TestCase
{
    private string $tempDir;
    private Config $config;
    private MarkdownGenerator $generator;
    private FreshnessChecker $freshnessChecker;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponimator-check-test-' . uniqid();
        $sourceDir = $this->tempDir . '/src';
        $targetDir = $this->tempDir . '/docs';
        mkdir($sourceDir, 0755, true);
        mkdir($targetDir, 0755, true);

        file_put_contents($sourceDir . '/E.php', '<?php namespace App; class E {}');

        $this->config = new Config(null);
        $ref = new \ReflectionProperty(Config::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(
            $this->config, [
            'source' => $sourceDir,
            'target' => $targetDir,
            'ignore' => ['vendor', 'tests'],
            ]
        );

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
        $pathResolver = new PathResolver($this->config);
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

        $this->generator = new MarkdownGenerator(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
        );
        $this->freshnessChecker = new FreshnessChecker($pathResolver, $hashComparator);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
    }

    public function testCheckReturnsZeroWhenFresh(): void
    {
        $this->generator->generateFull(['E.php']);
        $stale = $this->freshnessChecker->check(['E.php']);
        $this->assertSame(0, $stale);
    }

    public function testCheckReturnsNonZeroForMissingDoc(): void
    {
        $stale = $this->freshnessChecker->check(['E.php']);
        $this->assertSame(1, $stale);
    }

    public function testCheckReturnsNonZeroForModifiedFile(): void
    {
        $this->generator->generateFull(['E.php']);
        file_put_contents($this->config->getSourceAbsolute() . '/E.php', '<?php namespace App; class E { public function changed(): void {} }');
        $stale = $this->freshnessChecker->check(['E.php']);
        $this->assertSame(1, $stale);
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

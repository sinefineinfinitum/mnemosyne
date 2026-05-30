<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\DependencyAnalyzer;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\FreshnessChecker;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Analyzer\PSR4Detector;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Config;
use SineFine\Ponymator\Documentation\Generator\FileDocumenter;
use SineFine\Ponymator\Documentation\Generator\MarkdownGenerator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSR4Renderer;
use SineFine\Ponymator\Filesystem\PathResolver;

final class CheckModeTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponimator-check-int-test-' . uniqid();
        $this->sourceDir = $this->tempDir . '/src';
        $this->targetDir = $this->tempDir . '/docs';
        mkdir($this->sourceDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tempDir);
    }

    public function testFullThenCheckReturnsZero(): void
    {
        file_put_contents($this->sourceDir . '/F.php', '<?php namespace App; class F {}');

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $files = ['F.php'];

        $generator->generateFull($files);

        $checker = new FreshnessChecker(new PathResolver($config), new HashComparator());
        $stale = $checker->check($files);

        $this->assertSame(0, $stale);
    }

    public function testModifyThenCheckReturnsNonZero(): void
    {
        file_put_contents($this->sourceDir . '/G.php', '<?php namespace App; class G {}');

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $files = ['G.php'];

        $generator->generateFull($files);

        file_put_contents($this->sourceDir . '/G.php', '<?php namespace App; class G { public function changed(): void {} }');

        $checker = new FreshnessChecker(new PathResolver($config), new HashComparator());
        $stale = $checker->check($files);

        $this->assertSame(1, $stale);
    }

    public function testNoDocsThenCheckReturnsNonZero(): void
    {
        file_put_contents($this->sourceDir . '/H.php', '<?php namespace App; class H {}');

        $config = $this->makeConfig();
        $checker = new FreshnessChecker(new PathResolver($config), new HashComparator());
        $stale = $checker->check(['H.php']);

        $this->assertSame(1, $stale);
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
        $psr4Detector = new PSR4Detector('App');
        $psr4Renderer = new PSR4Renderer();
        $fileRenderer = new FileRenderer();
        $hashComparator = new HashComparator();
        $pathResolver = new PathResolver($config);
        $documenter = new FileDocumenter(
            $parser,
            $entityExtractor,
            $fileExtractor,
            $dependencyAnalyzer,
            $psr4Detector,
            $psr4Renderer,
            $fileRenderer,
            $hashComparator,
        );
        $documentRemover = new OutdatedDocumentationRemover($pathResolver);

        return new MarkdownGenerator(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
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

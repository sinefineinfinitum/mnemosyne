<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\EntityAnalyzer;
use SineFine\Mnemosyne\Analyzer\FileExtractor;
use SineFine\Mnemosyne\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Mnemosyne\Analyzer\Parser;
use SineFine\Mnemosyne\Comparator\HashComparator;
use SineFine\Mnemosyne\Config;
use SineFine\Mnemosyne\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Mnemosyne\Documentation\Linker\CrossReferenceFactory;
use SineFine\Mnemosyne\Documentation\Generator\Engine;
use SineFine\Mnemosyne\Documentation\Generator\PageGenerator;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\ClassRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\EnumRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\FileRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\InterfaceRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\TraitRenderer;
use SineFine\Mnemosyne\Filesystem\PathResolver;
use SineFine\Mnemosyne\Filesystem\Scanner;

final class ErrorAggregationTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mnemosyne-error-test-' . uniqid();
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

    public function testParseErrorsCollectedInErrorReport(): void
    {
        file_put_contents(
            $this->sourceDir . '/broken.php',
            '<?php namespace App; class Broken { public function oops('
        );
        file_put_contents(
            $this->sourceDir . '/good.php',
            '<?php namespace App; class Good {}'
        );

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $scanner = new Scanner($this->sourceDir);
        $files = $scanner->scan();
        $result = $generator->generateFull($files);

        $report = $result->getErrorReport();
        $this->assertFalse($report->isEmpty());
        $this->assertSame(2, $report->count());
        $this->assertSame(2, $report->errorCount());

        $diag0 = $report->getDiagnostics()[0];
        $this->assertSame('Error', $diag0->severity);
        $this->assertStringContainsString('Cross-file scan failed', $diag0->message);
        $this->assertStringContainsString('broken.php', $diag0->filePath ?? '');

        $diag1 = $report->getDiagnostics()[1];
        $this->assertSame('Error', $diag1->severity);
        $this->assertStringContainsString('Failed to parse', $diag1->message);
        $this->assertStringContainsString('broken.php', $diag1->filePath ?? '');

        $this->assertSame(1, $result->getSkipped());
        $this->assertSame(1, $result->getGenerated());
    }

    public function testCleanRunHasEmptyErrorReport(): void
    {
        file_put_contents(
            $this->sourceDir . '/clean.php',
            '<?php namespace App; class Clean {}'
        );

        $config = $this->makeConfig();
        $generator = $this->makeGenerator($config);
        $scanner = new Scanner($this->sourceDir);
        $files = $scanner->scan();
        $result = $generator->generateFull($files);

        $this->assertTrue($result->getErrorReport()->isEmpty());
        $this->assertSame(1, $result->getGenerated());
        $this->assertSame(0, $result->getSkipped());
    }

    public function testExitCodesFromProcessExecution(): void
    {
        $bin = __DIR__ . '/../../mnemosyne';

        $testDir = $this->tempDir . '/exit-test';
        mkdir($testDir . '/src', 0755, true);
        file_put_contents($testDir . '/src/Foo.php', '<?php namespace App; class Foo {}');

        $cwd = getcwd();
        chdir($testDir);

        $result = $this->runProcess(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' generate --full 2>&1', $exitCode);
        chdir($cwd);

        $this->assertSame(0, $exitCode);
    }

    public function testExitCode66ForNoFiles(): void
    {
        $bin = __DIR__ . '/../../mnemosyne';

        $emptyDir = $this->tempDir . '/empty-project';
        mkdir($emptyDir . '/src', 0755, true);

        $cwd = getcwd();
        chdir($emptyDir);

        $this->runProcess(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' generate --full 2>&1', $exitCode);
        chdir($cwd);

        $this->assertSame(66, $exitCode);
    }

    public function testExitCode2ForUnknownFlag(): void
    {
        $bin = __DIR__ . '/../../mnemosyne';
        $this->runProcess(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --unknown-flag 2>&1', $exitCode);
        $this->assertSame(2, $exitCode);
    }

    public function testExitCode2ForUnknownCommand(): void
    {
        $bin = __DIR__ . '/../../mnemosyne';
        $this->runProcess(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' unexpected-arg 2>&1', $exitCode);
        $this->assertSame(2, $exitCode);
    }

    public function testExitCode2ForUnknownShortArgument(): void
    {
        $bin = __DIR__ . '/../../mnemosyne';
        $this->runProcess(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' -x 2>&1', $exitCode);
        $this->assertSame(2, $exitCode);
    }

    public function testExitCode78ForMissingConfig(): void
    {
        $bin = __DIR__ . '/../../mnemosyne';

        $testDir = $this->tempDir . '/no-config';
        mkdir($testDir, 0755, true);

        $cwd = getcwd();
        chdir($testDir);

        $this->runProcess(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' generate --full --config=' . $testDir . '/nonexistent.json 2>&1',
            $exitCode
        );
        chdir($cwd);

        $this->assertSame(78, $exitCode);
    }

    /**
     * @param string $cmd
     * @param int    $exitCode
     */
    private function runProcess(string $cmd, ?int &$exitCode): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            $this->fail('Could not start process: ' . $cmd);
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        return $stdout . $stderr;
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

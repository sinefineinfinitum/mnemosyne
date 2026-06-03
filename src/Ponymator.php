<?php declare(strict_types=1);

namespace SineFine\Ponymator;

use InvalidArgumentException;
use SineFine\Ponymator\Analyzer\CombinedAnalyzer;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Cli\ArgumentParser;
use SineFine\Ponymator\Cli\Error\ConfigException;
use SineFine\Ponymator\Cli\Error\ErrorOutputFormatter;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Processor\DocumentationProcessor;
use SineFine\Ponymator\Documentation\Processor\ErrorReport;
use SineFine\Ponymator\Documentation\Processor\GenerationResult;
use SineFine\Ponymator\Documentation\Processor\PageGenerator;
use SineFine\Ponymator\Documentation\Linker\CrossReferenceFactory;
use SineFine\Ponymator\Documentation\Renderer\ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\TraitRenderer;
use SineFine\Ponymator\Filesystem\FileSystemException;
use SineFine\Ponymator\Filesystem\PathResolver;
use SineFine\Ponymator\Filesystem\Scanner;

class Ponymator
{
    public function run(): void
    {
        $args = ArgumentParser::parse($_SERVER['argv'] ?? []);

        if ($args->helpRequested) {
            ArgumentParser::printHelp();
            exit(ExitCode::SUCCESS);
        }

        try {
            $config = new Config($args->configPath);
        } catch (ConfigException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(ExitCode::CONFIG_ERROR);
        }

        $parser = new Parser();
        $combinedAnalyzer = new CombinedAnalyzer();
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
        $crossReferenceIndexBuilder = new CrossReferenceIndexBuilder($parser, $pathResolver);

        $generator = new DocumentationProcessor(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
            $crossReferenceIndexBuilder,
        );

        try {
            $scanner = new Scanner($config->getSource(), $config->getIgnore());
            $sourceFiles = $scanner->scan();
        } catch (FileSystemException $exception){
            fwrite(STDERR, "Error: " . $exception->getMessage() . "\n");
            exit(ExitCode::SOURCE_NOT_FOUND);
        }

        if (empty($sourceFiles)) {
            fwrite(STDERR, "Error: No files to document\n");
            exit(ExitCode::SOURCE_NOT_FOUND);
        }

        match ($args->mode) {
            ArgumentParser::DIFF => $this->runDiff($generator, $sourceFiles),
            default              => $this->runFull($generator, $sourceFiles),
        };
    }

    /**
     * @param DocumentationProcessor $generator
     * @param string[]               $sourceFiles
     */
    private function runFull(DocumentationProcessor $generator, array $sourceFiles): void
    {
        echo "Full generation: " . count($sourceFiles) . " files\n";
        $startTime = hrtime(true);

        try {
            $result = $generator->generateFull($sourceFiles);
        } catch (FileSystemException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(ExitCode::OUTPUT_ERROR);
        }

        $result->setExecutionTimeNs(hrtime(true) - $startTime);
        $this->renderErrors($result->getErrorReport());
        $this->reportSummary($result);

        if ($result->getErrorReport()->hasErrors()) {
            exit(ExitCode::GENERIC_ERROR);
        }
    }

    /**
     * @param DocumentationProcessor $generator
     * @param string[]               $sourceFiles
     */
    private function runDiff(DocumentationProcessor $generator, array $sourceFiles): void
    {
        $startTime = hrtime(true);

        try {
            $result = $generator->generateDiff($sourceFiles);
        } catch (FileSystemException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(ExitCode::OUTPUT_ERROR);
        }

        $result->setExecutionTimeNs(hrtime(true) - $startTime);
        $this->renderErrors($result->getErrorReport());
        $this->reportSummary($result);

        if ($result->getErrorReport()->hasErrors()) {
            exit(ExitCode::GENERIC_ERROR);
        }
    }

    private function renderErrors(ErrorReport $report): void
    {
        $formatter = new ErrorOutputFormatter();
        $block = $formatter->format($report);
        if ($block !== '') {
            fwrite(STDERR, $block);
        }
    }

    private function reportSummary(GenerationResult $result): void
    {
        $parts = [];
        if ($result->getGenerated() > 0) {
            $parts[] = $result->getGenerated() . " generated";
        }
        if ($result->getUnchanged() > 0) {
            $parts[] = $result->getUnchanged() . " unchanged";
        }
        if ($result->getSkipped() > 0) {
            $parts[] = $result->getSkipped() . " skipped";
        }
        if ($result->getRemoved() > 0) {
            $parts[] = $result->getRemoved() . " removed";
        }
        echo implode(', ', $parts) . "\n";

        $execTime = $result->getExecutionTimeSec();
        if ($execTime !== null) {
            printf("Execution time: %.2fs\n", $execTime);
        }
    }
}

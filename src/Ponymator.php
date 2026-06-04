<?php declare(strict_types=1);

namespace SineFine\Ponymator;

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
use SineFine\Ponymator\Documentation\Linker\CrossReferenceFactory;
use SineFine\Ponymator\Documentation\Processor\DocumentationProcessor;
use SineFine\Ponymator\Documentation\Processor\ErrorReport;
use SineFine\Ponymator\Documentation\Processor\GenerationResult;
use SineFine\Ponymator\Documentation\Processor\PageGenerator;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;
use SineFine\Ponymator\Documentation\Renderer\FileRendererInterface;
use SineFine\Ponymator\Documentation\Renderer\Markdown\ClassRenderer as MarkdownClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\EnumRenderer as MarkdownEnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\FileRenderer as MarkdownFileRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\InterfaceRenderer as MarkdownInterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\Markdown\TraitRenderer as MarkdownTraitRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\ClassRenderer as Psv1ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\EnumRenderer as Psv1EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\FileRenderer as Psv1FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\InterfaceRenderer as Psv1InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\Psv1Builder;
use SineFine\Ponymator\Documentation\Renderer\PSV1\TraitRenderer as Psv1TraitRenderer;
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

        [$entityRenderers, $fileRenderer] = $this->createRenderers($args->output);

        $hashComparator = new HashComparator();
        $pathResolver = new PathResolver($config, $this->docExtension($args->output));
        $crossReferenceFactory = new CrossReferenceFactory($pathResolver);

        $documenter = new PageGenerator(
            $parser,
            $combinedAnalyzer,
            $fileExtractor,
            $entityRenderers,
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
     * @return array{0: EntityRendererInterface[], 1: FileRendererInterface}
     */
    private function createRenderers(string $output): array
    {
        if ($output === ArgumentParser::OUTPUT_PSV1) {
            $builder = new Psv1Builder();

            return [
                [
                    new Psv1ClassRenderer($builder),
                    new Psv1InterfaceRenderer($builder),
                    new Psv1TraitRenderer($builder),
                    new Psv1EnumRenderer($builder),
                ],
                new Psv1FileRenderer($builder),
            ];
        }

        $builder = new MarkdownBuilder();

        return [
            [
                new MarkdownClassRenderer($builder),
                new MarkdownInterfaceRenderer($builder),
                new MarkdownTraitRenderer($builder),
                new MarkdownEnumRenderer($builder),
            ],
            new MarkdownFileRenderer($builder),
        ];
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
    private function docExtension(string $output): string
    {
        if ($output === ArgumentParser::OUTPUT_PSV1) {
            return 'psv1';
        }

        return 'md';
    }
}

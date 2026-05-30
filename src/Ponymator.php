<?php declare(strict_types=1);

namespace SineFine\Ponymator;

use SineFine\Ponymator\Analyzer\DependencyAnalyzer;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\FreshnessChecker;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Analyzer\PSR4Detector;
use SineFine\Ponymator\Cli\ArgumentParser;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Generator\FileDocumenter;
use SineFine\Ponymator\Documentation\Generator\GenerationResult;
use SineFine\Ponymator\Documentation\Generator\MarkdownGenerator;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSR4Renderer;
use SineFine\Ponymator\Filesystem\PathResolver;
use SineFine\Ponymator\Filesystem\Scanner;

class Ponymator
{
    public function run(): void
    {
        $args = ArgumentParser::parse($_SERVER['argv'] ?? []);

        if ($args->helpRequested) {
            ArgumentParser::printHelp();
            exit(0);
        }

        $config = new Config($args->configPath);

        $parser = new Parser();
        $entityExtractor = new EntityExtractor();
        $fileExtractor = new FileExtractor();
        $dependencyAnalyzer = new DependencyAnalyzer();
        $psr4Detector = new PSR4Detector('SineFine\\Ponymator');
        $psr4Renderer = new PSR4Renderer();
        $fileRenderer = new FileRenderer();
        $hashComparator = new HashComparator();
        $pathResolver = new PathResolver($config);
        $freshnessChecker = new FreshnessChecker($pathResolver, $hashComparator);
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

        $generator = new MarkdownGenerator(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
        );

        try {
            $scanner = new Scanner($config->getSource(), $config->getIgnore());
            $sourceFiles = $scanner->scan();
        } catch (\InvalidArgumentException $exception){
            echo $exception->getMessage() . "\n";
            exit(0);
        }

        if (empty($sourceFiles)) {
            echo "No files to document\n";
            exit(0);
        }

        match ($args->mode) {
            ArgumentParser::DIFF => $this->runDiff($generator, $sourceFiles),
            ArgumentParser::CHECK => $this->runCheck($freshnessChecker, $sourceFiles),
            ArgumentParser::FULL => $this->runFull($generator, $sourceFiles),
            default => $this->runFull($generator, $sourceFiles),
        };
    }

    /**
     * @param MarkdownGenerator $generator
     * @param string[]          $sourceFiles
     */
    private function runFull(MarkdownGenerator $generator, array $sourceFiles): void
    {
        echo "Full generation: " . count($sourceFiles) . " files\n";
        $result = $generator->generateFull($sourceFiles);

        $this->reportSummary($result);

        if ($result->getSkipped() > 0) {
            exit(1);
        }
    }

    /**
     * @param MarkdownGenerator $generator
     * @param string[]          $sourceFiles
     */
    private function runDiff(MarkdownGenerator $generator, array $sourceFiles): void
    {
        $result = $generator->generateDiff($sourceFiles);
        $this->reportSummary($result);
    }

    /**
     * @param FreshnessChecker $checker
     * @param string[]         $sourceFiles
     */
    private function runCheck(FreshnessChecker $checker, array $sourceFiles): void
    {
        $staleCount = $checker->check($sourceFiles);

        if ($staleCount === 0) {
            echo "Documentation is up-to-date.\n";
            exit(0);
        }

        echo "Documentation is outdated: $staleCount file(s) stale.\n";
        exit(2);
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
    }
}

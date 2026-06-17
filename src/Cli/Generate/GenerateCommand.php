<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Generate;

use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Error\ConfigException;
use SineFine\Ponymator\Cli\Error\ErrorOutputFormatter;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Config;
use SineFine\Ponymator\Documentation\GeneratorFactory;
use SineFine\Ponymator\Documentation\Generator\Engine;
use SineFine\Ponymator\Documentation\Generator\ErrorReport;
use SineFine\Ponymator\Documentation\Generator\GenerationResult;
use SineFine\Ponymator\Filesystem\FileSystemException;
use SineFine\Ponymator\Filesystem\Scanner;

final class GenerateCommand
{
    public function execute(Command $cmd): void
    {
        try {
            $config = new Config($cmd->configPath);
        } catch (ConfigException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(ExitCode::CONFIG_ERROR);
        }

        $factory = new GeneratorFactory();
        $generator = $factory->create($config, $cmd->output);

        try {
            $scanner = new Scanner($config->getSource(), $config->getIgnore());
            $sourceFiles = $scanner->scan();
        } catch (FileSystemException $exception) {
            fwrite(STDERR, "Error: " . $exception->getMessage() . "\n");
            exit(ExitCode::SOURCE_NOT_FOUND);
        }

        if (empty($sourceFiles)) {
            fwrite(STDERR, "Error: No files to document\n");
            exit(ExitCode::SOURCE_NOT_FOUND);
        }

        $this->runGeneration($generator, $sourceFiles, $cmd);
    }

    /**
     * @param Engine   $generator
     * @param string[] $sourceFiles
     */
    private function runGeneration(Engine $generator, array $sourceFiles, Command $cmd): void
    {
        if ($cmd->isDiff) {
            $this->runDiff($generator, $sourceFiles);
        } else {
            $this->runFull($generator, $sourceFiles);
        }
    }

    /**
     * @param Engine   $generator
     * @param string[] $sourceFiles
     */
    private function runFull(Engine $generator, array $sourceFiles): void
    {
        echo "Full generation: " . count($sourceFiles) . " files\n";

        try {
            $result = $generator->generateFull($sourceFiles);
        } catch (FileSystemException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(ExitCode::OUTPUT_ERROR);
        }

        $this->renderErrors($result->getErrorReport());
        $this->reportSummary($result);

        if ($result->getErrorReport()->hasErrors()) {
            exit(ExitCode::GENERIC_ERROR);
        }
    }

    /**
     * @param Engine   $generator
     * @param string[] $sourceFiles
     */
    private function runDiff(Engine $generator, array $sourceFiles): void
    {
        try {
            $result = $generator->generateDiff($sourceFiles);
        } catch (FileSystemException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(ExitCode::OUTPUT_ERROR);
        }

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
    }
}

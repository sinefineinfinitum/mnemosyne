<?php declare(strict_types=1);

namespace SineFine\Ponymator;

use SineFine\Ponymator\Cli\ArgumentParser;
use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Cli\ExecutionTimer;
use SineFine\Ponymator\Cli\Generate\GenerateCommand;
use SineFine\Ponymator\Cli\Graph\ClearCommand;
use SineFine\Ponymator\Cli\Graph\ImportCommand;
use SineFine\Ponymator\Cli\HelpPrinter;
use SineFine\Ponymator\Cli\Show\ShowImpactCommand;
use SineFine\Ponymator\Cli\Show\ShowPathCommand;
use SineFine\Ponymator\Cli\Show\ShowEntityCommand;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;

class Ponymator
{
    public function run(): void
    {
        $timer = new ExecutionTimer();
        register_shutdown_function([$timer, 'finish']);

        $cmd = ArgumentParser::parse($_SERVER['argv'] ?? []);

        if ($cmd->helpRequested) {
            $this->printHelp($cmd);
            exit(ExitCode::SUCCESS);
        }

        match ($cmd->group) {
            'generate' => (new GenerateCommand())->execute($cmd),
            'graph' => $this->handleGraph($cmd),
            'show' => $this->handleShow($cmd),
            default => HelpPrinter::printHelp(),
        };
    }

    private function handleGraph(Command $cmd): void
    {
        match ($cmd->subcommand) {
            'import' => (new ImportCommand())->execute($cmd),
            'clear' => (new ClearCommand())->execute($cmd),
            default => HelpPrinter::printGraphHelp(),
        };
    }

    private function handleShow(Command $cmd): void
    {
        $dbPath = $cmd->resolveDbPath(false);

        if (!file_exists($dbPath)) {
            fwrite(STDERR, "Error: Database file not found: $dbPath\n");
            exit(ExitCode::DATA_ERROR);
        }

        $pdo = Command::openDb($dbPath);

        $query = new GraphQuery($pdo);

        match ($cmd->subcommand) {
            'entity' => (new ShowEntityCommand())->execute($cmd, $query),
            'impact' => (new ShowImpactCommand())->execute($cmd, $query),
            'path' => (new ShowPathCommand())->execute($cmd, $query),
            default => HelpPrinter::printShowHelp(),
        };
    }

    private function printHelp(Command $cmd): void
    {
        match ($cmd->group) {
            'generate' => HelpPrinter::printGenerateHelp(),
            'graph' => HelpPrinter::printGraphHelp(),
            'show' => HelpPrinter::printShowHelp(),
            default => HelpPrinter::printHelp(),
        };
    }
}

<?php declare(strict_types=1);

namespace SineFine\Mnemosyne;

use SineFine\Mnemosyne\Cli\ArgumentParser;
use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Cli\Error\ExitCode;
use SineFine\Mnemosyne\Cli\ExecutionTimer;
use SineFine\Mnemosyne\Cli\Generate\GenerateCommand;
use SineFine\Mnemosyne\Cli\Graph\ClearCommand;
use SineFine\Mnemosyne\Cli\Graph\ImportCommand;
use SineFine\Mnemosyne\Cli\HelpPrinter;
use SineFine\Mnemosyne\Cli\Show\ShowEntityCommand;
use SineFine\Mnemosyne\Cli\Show\ShowImpactCommand;
use SineFine\Mnemosyne\Cli\Show\ShowPathCommand;
use SineFine\Mnemosyne\Db\PDOFactory;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;

class MnemosyneCommand
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
        $factory = new PDOFactory($cmd);
        $pdo = $factory->connect(requireExisting: true);

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

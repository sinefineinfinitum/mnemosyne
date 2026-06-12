<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Graph;

use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Db\PDOFactory;
use SineFine\Ponymator\Graph\Experimental\Schema;

class ClearCommand
{
    public function execute(Command $cmd): void
    {
        $factory = new PDOFactory($cmd);
        $dbPath = $factory->resolvePath();
        $isNew = !file_exists($dbPath);
        $pdo = $factory->connect();

        Schema::drop($pdo);
        Schema::create($pdo);

        if ($isNew) {
            echo "Graph database created: $dbPath\n";
        } else {
            echo "Graph database cleared: $dbPath\n";
        }
    }
}

<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Graph;

use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Db\PDOFactory;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

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

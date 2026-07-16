<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Graph;

use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Cli\Error\ExitCode;
use SineFine\Mnemosyne\Config;
use SineFine\Mnemosyne\Db\PDOFactory;
use SineFine\Mnemosyne\Filesystem\FileFinder;
use SineFine\Mnemosyne\Graph\Experimental\GraphCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Msv1ToGraphImporter;
use SineFine\Mnemosyne\Graph\Experimental\Schema;
use Throwable;

class ImportCommand
{
    public function execute(Command $cmd, Config $config): void
    {
        $factory = new PDOFactory($cmd, $config);
        $pdo = $factory->connect();

        Schema::create($pdo);

        $targetDir = $config->getTargetAbsolute();
        $finder = new FileFinder();
        $msv1Files = $finder->find($targetDir, ['msv1']);

        if (empty($msv1Files)) {
            fwrite(STDERR, "Error: No .msv1 files found in target directory: $targetDir\n");
            exit(ExitCode::SOURCE_NOT_FOUND);
        }

        $command = new GraphCommand($pdo);
        $query = new GraphQuery($pdo);
        $builder = new Msv1ToGraphImporter($command, $query);

        try {
            $builder->buildFromFiles($msv1Files, $targetDir);
        } catch (Throwable $e) {
            fwrite(STDERR, "Error: Import failed: " . $e->getMessage() . "\n");
            exit(ExitCode::GENERIC_ERROR);
        }

        $entityCount = $query->countEntities();
        $relCount = $query->countRelationships();

        echo "Graph import complete: $entityCount entities, $relCount relationships imported into " . $factory->resolvePath() . "\n";
    }

}

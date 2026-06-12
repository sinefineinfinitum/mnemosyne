<?php declare(strict_types=1);

namespace SineFine\Ponymator\Db;

use PDO;
use PDOException;
use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Error\ConfigException;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Config;

final class PDOFactory
{
    public function __construct(
        private Command $command,
    ) {
    }

    public function connect(bool $requireExisting = false): PDO
    {
        $dbPath = $this->resolvePath(!$requireExisting);

        if ($requireExisting && !file_exists($dbPath)) {
            fwrite(STDERR, "Error: Database file not found: $dbPath\n");
            exit(ExitCode::DATA_ERROR);
        }

        return self::openDb($dbPath);
    }

    public function resolvePath(bool $tryConfig = true): string
    {
        if ($this->command->dbPath !== null) {
            return $this->command->dbPath;
        }

        if ($tryConfig) {
            try {
                $config = new Config($this->command->configPath);
                $dbPath = $config->getDbPath();
                if ($dbPath !== null) {
                    return $dbPath;
                }
            } catch (ConfigException $e) {
                fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
                exit(ExitCode::CONFIG_ERROR);
            }
        }

        fwrite(STDERR, "Error: --db-path is required and no dbPath found in config\n");
        exit(ExitCode::WRONG_USAGE);
    }

    private static function openDb(string $dbPath): PDO
    {
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            fwrite(STDERR, "Error: Cannot open database: " . $e->getMessage() . "\n");
            exit(ExitCode::DATA_ERROR);
        }

        return $pdo;
    }
}

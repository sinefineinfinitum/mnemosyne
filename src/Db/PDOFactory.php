<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Db;

use PDO;
use PDOException;
use RuntimeException;
use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Cli\Error\ExitCode;
use SineFine\Mnemosyne\Config;

final class PDOFactory
{
    public function __construct(
        private Command $command,
        private Config $config,
    ) {
    }

    public function connect(bool $requireExisting = false): PDO
    {
        $dbPath = $this->resolvePath();

        if ($requireExisting && !file_exists($dbPath)) {
            fwrite(STDERR, "Error: Database file not found: $dbPath\n");
            exit(ExitCode::DATA_ERROR);
        }

        return self::openDb($dbPath, $this->config->getPragmas());
    }

    public function connectReadOnly(): PDO
    {
        $dbPath = $this->resolvePath();

        if (!file_exists($dbPath)) {
            fwrite(STDERR, "Error: Database file not found: $dbPath\n");
            exit(ExitCode::DATA_ERROR);
        }

        return self::openDb($dbPath, $this->config->getPragmas(), readOnly: true);
    }

    public function resolvePath(): string
    {
        if ($this->command->dbPath !== null) {
            return $this->command->dbPath;
        }

        $dbPath = $this->config->getDbPath();
        if ($dbPath !== null) {
            return $dbPath;
        }

        fwrite(STDERR, "Error: --db-path is required and no dbPath found in config\n");
        exit(ExitCode::WRONG_USAGE);
    }

    /**
     * Opens an SQLite database connection with the specified path, pragmas, and access mode.
     *
     * @param  string               $dbPath   The file path to the SQLite database.
     * @param  array<string, mixed> $pragmas  An associative array of PRAGMA settings to be applied.
     * @param  bool                 $readOnly
     * @return PDO
     * @throws PDOException | RuntimeException
     */
    private static function openDb(string $dbPath, array $pragmas, bool $readOnly = false): PDO
    {
        try {
            $flags = $readOnly
                ? [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]
                : [];

            $pdo = new PDO('sqlite:' . $dbPath, null, null, $flags);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            foreach ($pragmas as $key => $value) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                    fwrite(STDERR, "Error: Invalid PRAGMA name: $key\n");
                    exit(ExitCode::CONFIG_ERROR);
                }
                $safeValue = self::sanitizePragmaValue($key, $value);
                $pdo->exec("PRAGMA $key = $safeValue");
            }
        } catch (PDOException $e) {
            fwrite(STDERR, "Error: Cannot open database: " . $e->getMessage() . "\n");
            exit(ExitCode::DATA_ERROR);
        }

        return $pdo;
    }

    private static function sanitizePragmaValue(string $key, mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return $value;
        }

        if (is_string($value) && str_starts_with($value, '-') && ctype_digit(substr($value, 1))) {
            return $value;
        }

        $allowedStrings = ['MEMORY', 'DEFAULT', 'FILE', 'DELETE', 'TRUNCATE', 'PERSIST', 'WAL', 'OFF'];
        if (is_string($value) && in_array(strtoupper($value), $allowedStrings, true)) {
            return "'" . $value . "'";
        }

        fwrite(STDERR, "Error: Invalid PRAGMA value for '$key': " . var_export($value, true) . "\n");
        exit(ExitCode::CONFIG_ERROR);
    }
}

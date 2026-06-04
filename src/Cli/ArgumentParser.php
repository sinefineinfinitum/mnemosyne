<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli;

use SineFine\Ponymator\Cli\Error\ExitCode;

final class ArgumentParser
{
    public const FULL = 'full';
    public const DIFF = 'diff';

    public const OUTPUT_MD = 'md';
    public const OUTPUT_PSV1 = 'psv1';

    private function __construct(
        public string $mode,
        public ?string $configPath,
        public bool $helpRequested,
        public string $output,
    ) {
    }

    /**
     * @param string[] $argv
     */
    public static function parse(array $argv): self
    {
        $mode = self::DIFF;
        $configPath = null;
        $helpRequested = false;
        $output = self::OUTPUT_MD;

        array_shift($argv);

        foreach ($argv as $arg) {
            match (true) {
                $arg === '--full' => $mode = self::FULL,
                $arg === '--diff' => $mode = self::DIFF,
                str_starts_with($arg, '--config=') => $configPath = substr($arg, 9),
                str_starts_with($arg, '--output=') => $output = self::parseOutput(substr($arg, 9)),
                $arg === '--help' => $helpRequested = true,
                str_starts_with($arg, '--') => self::mistakeExit('Unknown flag: ' . $arg),
                default => self::usageExit('Unexpected argument: ' . $arg),
            };
        }

        return new self($mode, $configPath, $helpRequested, $output);
    }

    public static function printHelp(): void
    {
        echo <<<'HELP'
Usage: ponymator [options]

Options:
  --full              Regenerate all documentation
  --diff              Regenerate only changed files (default)
  --config=<path>     Path to config file (default: .ponymator.json)
  --output=md         Generate Markdown documentation (default)
  --output=psv1       Generate Ponymator Syntax v1 documentation
  --help              Display this help message

Exit codes:
  0   Success
  1   Generic error (config, parse, runtime)
  2   Command-line mistake (unknown flag)
  64  Wrong or missing required arguments
  66  Source directory or files not found
  73  Cannot create output file or directory
  78  Config missing, unreadable, or malformed

HELP;
    }

    private static function parseOutput(string $output): string
    {
        if ($output === self::OUTPUT_MD || $output === self::OUTPUT_PSV1) {
            return $output;
        }

        self::mistakeExit('Unknown output format: ' . $output);
    }

    /**
     * @phpstan-return never
     */
    private static function mistakeExit(string $message): void
    {
        fwrite(STDERR, "Error: $message\n");
        exit(ExitCode::COMMAND_LINE_MISTAKE);
    }

    private static function usageExit(string $message): void
    {
        fwrite(STDERR, "Error: $message\n");
        exit(ExitCode::WRONG_USAGE);
    }
}

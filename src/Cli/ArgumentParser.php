<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli;

final class ArgumentParser
{
    public const FULL = 'full';
    public const DIFF = 'diff';
    public const CHECK = 'check';
    private function __construct(
        public string $mode,
        public ?string $configPath,
        public bool $helpRequested,
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

        array_shift($argv);

        foreach ($argv as $arg) {
            if ($arg === '--full') {
                $mode = self::FULL;
            } elseif ($arg === '--diff') {
                $mode = self::DIFF;
            } elseif ($arg === '--check') {
                $mode = self::CHECK;
            } elseif (str_starts_with($arg, '--config=')) {
                $configPath = substr($arg, 9);
            } elseif ($arg === '--help') {
                $helpRequested = true;
            }
        }

        return new self($mode, $configPath, $helpRequested);
    }

    public static function printHelp(): void
    {
        echo <<<'HELP'
Usage: ponimator [options]

Options:
  --full              Regenerate all documentation
  --diff              Regenerate only changed files (default)
  --check             Verify documentation is up-to-date
  --config=<path>     Path to config file (default: .ponimator.json)
  --help              Display this help message

Exit codes:
  0   Success (all modes) or up-to-date (check mode)
  1   Generic error (config, parse, runtime)
  2   Check mode: documentation is outdated

HELP;
    }
}

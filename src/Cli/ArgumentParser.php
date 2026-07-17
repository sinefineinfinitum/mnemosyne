<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli;

use SineFine\Mnemosyne\Cli\Error\ExitCode;

final class ArgumentParser
{
    public const OUTPUT_MD = 'md';
    public const OUTPUT_MSV1 = 'msv1';
    private const COMMANDS = ['generate', 'graph', 'show', 'detect'];

    /**
     * @param string[] $argv
     */
    public static function parse(array $argv): Command
    {
        array_shift($argv);

        if (empty($argv)) {
            return new Command('', null, [], null, self::OUTPUT_MD, null, null, true, false);
        }

        $first = $argv[0];

        if ($first === '--help') {
            return new Command('', null, [], null, self::OUTPUT_MD, null, null, true, false);
        }

        if (str_starts_with($first, '--')) {
            self::mistakeExit('Unknown flag: ' . $first);
        }

        if (!in_array($first, self::COMMANDS, true)) {
            self::mistakeExit('Unknown command: ' . $first . '. Run mnemosyne --help for usage.');
        }

        $group = $first;
        array_shift($argv);

        return match ($group) {
            'generate' => self::parseGenerate($argv),
            'graph' => self::parseGraph($argv),
            'show' => self::parseShow($argv),
            'detect' => self::parseDetect($argv),
        };
    }

    /**
     * @param string[] $argv
     */
    private static function parseGenerate(array $argv): Command
    {
        $isDiff = true;
        $configPath = null;
        $output = self::OUTPUT_MD;
        $helpRequested = false;

        foreach ($argv as $arg) {
            match (true) {
                $arg === '--full' => $isDiff = false,
                $arg === '--diff' => $isDiff = true,
                $arg === '--help' => $helpRequested = true,
                str_starts_with($arg, '--config=') => $configPath = substr($arg, 9),
                str_starts_with($arg, '--output=') => $output = self::parseOutput(substr($arg, 9)),
                str_starts_with($arg, '--') => self::mistakeExit('Unknown flag: ' . $arg),
                default => self::usageExit('Unexpected argument: ' . $arg),
            };
        }

        return new Command('generate', null, [], $configPath, $output, null, null, $helpRequested, $isDiff);
    }

    /**
     * @param string[] $argv
     */
    private static function parseGraph(array $argv): Command
    {
        if (empty($argv)) {
            return new Command('graph', null, [], null, self::OUTPUT_MD, null, null, true, false);
        }

        $first = $argv[0];

        if ($first === '--help') {
            return new Command('graph', null, [], null, self::OUTPUT_MD, null, null, true, false);
        }

        if (str_starts_with($first, '--')) {
            self::mistakeExit('Unknown flag: ' . $first);
        }

        $subcommands = ['import', 'clear'];
        if (!in_array($first, $subcommands, true)) {
            self::mistakeExit('Unknown graph subcommand: ' . $first . '. Available: import, clear');
        }

        $subcommand = $first;
        array_shift($argv);

        $configPath = null;
        $dbPath = null;
        $helpRequested = false;

        foreach ($argv as $arg) {
            match (true) {
                $arg === '--help' => $helpRequested = true,
                str_starts_with($arg, '--config=') => $configPath = substr($arg, 9),
                str_starts_with($arg, '--db-path=') => $dbPath = substr($arg, 10),
                str_starts_with($arg, '--') => self::mistakeExit('Unknown flag: ' . $arg),
                default => self::usageExit('Unexpected argument: ' . $arg),
            };
        }

        return new Command('graph', $subcommand, [], $configPath, self::OUTPUT_MD, $dbPath, null, $helpRequested, false);
    }

    /**
     * @param string[] $argv
     */
    private static function parseShow(array $argv): Command
    {
        if (empty($argv)) {
            return new Command('show', null, [], null, self::OUTPUT_MD, null, null, true, false);
        }

        $first = $argv[0];

        if ($first === '--help') {
            return new Command('show', null, [], null, self::OUTPUT_MD, null, null, true, false);
        }

        if (str_starts_with($first, '--')) {
            self::mistakeExit('Unknown flag: ' . $first);
        }

        $subcommands = ['entity', 'impact', 'path'];
        if (!in_array($first, $subcommands, true)) {
            self::mistakeExit('Unknown show subcommand: ' . $first . '. Available: entity, impact, path');
        }

        $subcommand = $first;
        array_shift($argv);

        $positionalArgs = [];
        $dbPath = null;
        $depth = null;
        $helpRequested = false;

        foreach ($argv as $arg) {
            match (true) {
                $arg === '--help' => $helpRequested = true,
                str_starts_with($arg, '--db-path=') => $dbPath = substr($arg, 10),
                str_starts_with($arg, '--depth=') => $depth = self::parseDepth(substr($arg, 8)),
                str_starts_with($arg, '--') => self::mistakeExit('Unknown flag: ' . $arg),
                default => $positionalArgs[] = $arg,
            };
        }

        $namedArgs = self::namedShowArgs($subcommand, $positionalArgs);

        return new Command('show', $subcommand, $positionalArgs, null, self::OUTPUT_MD, $dbPath, $depth, $helpRequested, false, $namedArgs);
    }


    /**
     * @param  string   $subcommand
     * @param  string[] $positionalArgs
     * @return array<string, string>
     */
    private static function namedShowArgs(string $subcommand, array $positionalArgs): array
    {
        if ($subcommand === 'path') {
            if (count($positionalArgs) < 2) {
                self::usageExit('show path requires two entity names: <from> <to>');
            }

            return ['from' => $positionalArgs[0], 'to' => $positionalArgs[1]];
        }

        if (empty($positionalArgs)) {
            self::usageExit("show $subcommand requires an entity name");
        }

        return ['entity' => $positionalArgs[0]];
    }

    /**
     * @param string[] $argv
     */
    private static function parseDetect(array $argv): Command
    {
        if (empty($argv)) {
            return new Command('detect', null, [], null, self::OUTPUT_MD, null, null, true, false);
        }

        $configPath = null;
        $dbPath = null;
        $helpRequested = false;

        foreach ($argv as $arg) {
            match (true) {
                $arg === '--help' => $helpRequested = true,
                str_starts_with($arg, '--config=') => $configPath = substr($arg, 9),
                str_starts_with($arg, '--db-path=') => $dbPath = substr($arg, 10),
                str_starts_with($arg, '--') => self::mistakeExit('Unknown flag: ' . $arg),
                default => self::usageExit('Unexpected argument: ' . $arg),
            };
        }

        return new Command('detect', null, [], $configPath, self::OUTPUT_MD, $dbPath, null, $helpRequested, false);
    }

    private static function parseOutput(string $output): string
    {
        if ($output === self::OUTPUT_MD || $output === self::OUTPUT_MSV1) {
            return $output;
        }

        self::mistakeExit('Unknown output format: ' . $output);
    }

    private static function parseDepth(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1) {
            self::usageExit('Invalid depth value: ' . $value . '. Must be a positive integer >= 1.');
        }

        return (int) $value;
    }

    /**
     * @phpstan-return never
     */
    private static function mistakeExit(string $message): void
    {
        fwrite(STDERR, "Error: $message\n");
        exit(ExitCode::COMMAND_LINE_MISTAKE);
    }

    /**
     * @phpstan-return never
     */
    private static function usageExit(string $message): void
    {
        fwrite(STDERR, "Error: $message\n");
        exit(ExitCode::WRONG_USAGE);
    }
}

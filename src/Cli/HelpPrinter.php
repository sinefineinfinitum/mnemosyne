<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli;

class HelpPrinter
{

    public static function printHelp(): void
    {
        echo <<<'HELP'
Usage: mnemosyne <command> [<subcommand>] [<args>] [<flags>]

Commands:
  generate    Generate documentation from PHP source code
  graph       Manage the SQLite graph database
  show        Analyze entity dependencies
  detect      Detect design patterns in the imported codebase

Run 'mnemosyne <command> --help' for more information on a command.

Exit codes:
  0   Success
  1   Generic error (config, parse, runtime)
  2   Command-line mistake (unknown flag/command)
  64  Wrong or missing required arguments
  65  Data error (database, entity not found)
  66  Source directory or files not found
  73  Cannot create output file or directory
  78  Config missing, unreadable, or malformed

HELP;
    }

    public static function printGenerateHelp(): void
    {
        echo <<<'HELP'
Usage: mnemosyne generate [--full | --diff] [--config=<path>] [--output=md|msv1]

Options:
  --full              Regenerate all documentation
  --diff              Regenerate only changed files (default)
  --config=<path>     Path to config file (default: .mnemosyne.json)
  --output=md         Generate Markdown documentation (default)
  --output=msv1       Generate mnemosyne Syntax v1 documentation
  --help              Display this help message

HELP;
    }

    public static function printGraphHelp(): void
    {
        echo <<<'HELP'
Usage: mnemosyne graph <subcommand> [<flags>]

Subcommands:
  import              Import PHP analysis into graph database
  clear               Drop and recreate all tables in graph database

Options:
  --db-path=<path>    Path to SQLite graph database
  --config=<path>     Path to config file (default: .mnemosyne.json)
  --help              Display this help message

HELP;
    }

    public static function printDetectHelp(): void
    {
        echo <<<'HELP'
Usage: mnemosyne detect [<flags>]

Detect design patterns in the imported PHP codebase.

Flags:
  --db-path=<path>              Path to SQLite graph database
  --config=<path>               Path to config file (default: .mnemosyne.json)
  --help                        Display this help message

HELP;
    }

    public static function printShowHelp(): void
    {
        echo <<<'HELP'
Usage: mnemosyne show <subcommand> <args> [<flags>]

Subcommands:
  entity <name>       Display entity card (file, type, dependencies)
  impact <name>       Show all entities affected by changing <name>
  path <from> <to>    Find shortest dependency path between two entities

Options:
  --depth=N           Limit analysis depth (must be >= 1)
  --db-path=<path>    Path to SQLite graph database
  --help              Display this help message

HELP;
    }
}

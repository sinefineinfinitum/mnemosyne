<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli;

final class Command
{
    /**
     * @param string                $group
     * @param string|null           $subcommand
     * @param string[]              $positionalArgs
     * @param string|null           $configPath
     * @param string                $output
     * @param string|null           $dbPath
     * @param int|null              $depth
     * @param bool                  $helpRequested
     * @param bool                  $isDiff
     * @param array<string, string> $namedArgs
     */
    public function __construct(
        public string $group,
        public ?string $subcommand,
        public array $positionalArgs,
        public ?string $configPath,
        public string $output,
        public ?string $dbPath,
        public ?int $depth,
        public bool $helpRequested,
        public bool $isDiff = false,
        public array $namedArgs = [],
    ) {
    }
}

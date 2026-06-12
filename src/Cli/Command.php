<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli;

final class Command
{
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
    ) {
    }
}

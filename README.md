# Ponimator

A CLI-first PHP documentation generator that produces deterministic Markdown documentation for a project's public API.

## Principles

### AST-First Correctness

Ponimator uses PHP Abstract Syntax Tree analysis (via [`nikic/php-parser`](https://github.com/nikic/PHP-Parser)) as the single source of truth. Public API extraction — classes, interfaces, traits, enums, public methods, signatures, inheritance, implemented interfaces, modifiers, and dependencies — is derived from parsed PHP source code, never from regex or string matching. If source code cannot be parsed, the tool fails with actionable diagnostics.

### Deterministic Output

For identical source code and configuration, repeated runs produce byte-identical Markdown files. Ordering of classes, methods, dependencies, imports, modifiers, and sections is fully deterministic, making output suitable for CI comparison.

### CLI-First Experience

| Mode | Flag | Description |
| :--- | :--- | :--- |
| Full | `--full` | Regenerate all documentation |
| Diff | `--diff` | Regenerate only changed files (default) |
| Check | `--check` | Verify documentation is up-to-date |

Exit codes:

- `0` — success or up-to-date (check mode)
- `1` — generic error (config, parse, runtime)
- `2` — check mode: documentation is outdated

### Test-First Quality

Every behavior affecting documentation, CLI contracts, configuration, parsing, or exit codes is covered by PHPUnit tests. Required coverage: AST parsing, Markdown format, config validation, full generation, incremental diff, check mode, error handling.

## Installation

```bash
composer require sinefineinfinitum/ponymator
```

## Usage

```bash
vendor/bin/ponymator [--full | --diff | --check] [--config=<path>] [--help]
```

By default, Ponimator runs in diff mode — only regenerating documentation for changed source files.

## Requirements

- PHP ^8.0
- ext-json (usually built-in)

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpcs
```

## Architecture

```
src/
├── Analyzer/           # PHP parsing, entity extraction, freshness checks
├── Cli/                # Argument parsing
├── Comparator/         # Hash-based file comparison
├── Documentation/      # Generation, rendering, cleaning
│   ├── Cleaner/
│   ├── Generator/
│   └── Renderer/
├── Filesystem/         # Path resolution, file scanning
├── Config.php
└── Ponymator.php       # Main runner
```

## Configuration

See [docs/Config.md](docs/Config.md) for configuration options (default: `.ponimator.json`).

## License

MIT

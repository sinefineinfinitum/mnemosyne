# Ponymator

[![Packagist Version](https://img.shields.io/packagist/v/sinefineinfinitum/ponymator)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-purple)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![License](https://img.shields.io/packagist/l/sinefineinfinitum/ponymator)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![CI](https://img.shields.io/github/actions/workflow/status/sinefineinfinitum/ponymator/ci.yml?branch=main)](https://github.com/sinefineinfinitum/ponymator/actions)
[![PHPStan level](https://img.shields.io/badge/PHPStan-8-brightgreen)](https://github.com/phpstan/phpstan)

A CLI-first PHP documentation generator that produces deterministic Markdown documentation for a project's public API.

## Principles

### AST-First Correctness

Ponymator uses PHP Abstract Syntax Tree analysis (via [`nikic/php-parser`](https://github.com/nikic/PHP-Parser)) as the single source of truth. Public API extraction — classes, interfaces, traits, enums, public methods, signatures, inheritance, implemented interfaces, modifiers, and dependencies — is derived from parsed PHP source code, never from regex or string matching. If source code cannot be parsed, the tool fails with actionable diagnostics.

### Deterministic Output

For identical source code and configuration, repeated runs produce byte-identical Markdown files. Ordering of classes, methods, dependencies, imports, modifiers, and sections is fully deterministic, making output suitable for CI comparison.

### CLI-First Experience

| Mode | Flag | Description |
| :--- | :--- | :--- |
| Full | `--full` | Regenerate all documentation |
| Diff | `--diff` | Regenerate only changed files (default) |

Exit codes:

- `0` — success
- `1` — generic error (config, parse, runtime)

### Test-First Quality

Every behavior affecting documentation, CLI contracts, configuration, parsing, or exit codes is covered by PHPUnit tests. Required coverage: AST parsing, Markdown format, config validation, full generation, incremental diff, error handling.

## Installation

```bash
composer require sinefineinfinitum/ponymator
```

## Usage

```bash
vendor/bin/ponymator [--full | --diff] [--config=<path>] [--help]
```

By default, Ponimator runs in diff mode — only regenerating documentation for changed source files.

## Generated Documentation Example

The generated Markdown includes YAML frontmatter with a content hash and type, followed by a summary of the entity:

````markdown
---
type: class
hash: 3d8f1b2c9a0e
---

# `App\Service\UserService`

## Head

**Type**: class
**Modifiers**: readonly
**Parent**: `App\Abstracts\BaseService`
**Interfaces**: `App\Contracts\ServiceInterface`

## Methods

- public static function create(string $name, array $data = []): App\Models\User
- public function findById(int $id, ?bool $active = true): ?App\Models\User

## Used by

- [App\Contract\ServiceInterface](..\Contract\ServiceInterface.md)
- `Vendor\Package\SomeClass`

## Dependencies

- [App\Abstract\BaseService](..\Abstract\BaseService.md)
- [App\Model\User](..\Model\User.md)

## Creates

- `create`: [`App\Models\User`](..\Models\User.md)
- `findById`: [`App\Models\User`](..\Models\User.md)
````

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
├── Analyzer/               # AST parsing, entity & dependency extraction
│   ├── Extractor/          #  — class, interface, trait, enum extractors
│   ├── Linker/             #  — cross-reference index builder
│   ├── Visitor/            #  — AST visitors (entities, deps, creations)
├── Cli/                    # Argument parsing
├── Comparator/             # Hash-based file comparison
├── Documentation/          # Generation, cross-linking, rendering, cleanup
│   ├── Cleaner/            #  — outdated doc removal
│   ├── Linker/             #  — cross-reference resolution
│   ├── Processor/          #  — page generation orchestration
│   └── Renderer/           #  — per-entity Markdown renderers
├── Filesystem/             # Path resolution, file scanning
├── Config.php              # Configuration loading & validation
└── Ponymator.php           # Main orchestrator
```

## Configuration

Configuration file: `.ponymator.json`. Example:
```json
{
    "source": "app",
    "target": "api-docs",
    "ignore": ["vendor", "node_modules"]
}
```

## License

MIT

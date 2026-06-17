# Ponymator

[![Packagist Version](https://img.shields.io/packagist/v/sinefineinfinitum/ponymator)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-purple)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![License](https://img.shields.io/packagist/l/sinefineinfinitum/ponymator)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![CI](https://img.shields.io/github/actions/workflow/status/sinefineinfinitum/ponymator/ci.yml?branch=main)](https://github.com/sinefineinfinitum/ponymator/actions)
[![PHPStan level](https://img.shields.io/badge/PHPStan-8-brightgreen)](https://github.com/phpstan/phpstan)

A CLI-first PHP documentation generator that produces deterministic Markdown documentation for a project's API surface.

## Principles

### AST-First Correctness

Ponymator uses PHP Abstract Syntax Tree analysis (via [`nikic/php-parser`](https://github.com/nikic/PHP-Parser)) as the single source of truth. API extraction — classes, interfaces, traits, enums, constants, properties, methods, signatures, inheritance, implemented interfaces, modifiers, and dependencies — is derived from parsed PHP source code, never from regex or string matching. If source code cannot be parsed, the tool fails with actionable diagnostics.

### Deterministic Output

For identical source code and configuration, repeated runs produce byte-identical Markdown files. Ordering of classes, methods, dependencies, imports, modifiers, and sections is fully deterministic, making output suitable for CI comparison.

### CLI-First Experience

| Mode | Flag | Description |
| :--- | :--- | :--- |
| Full | `generate --full` | Regenerate all documentation |
| Diff | `generate --diff` | Regenerate only changed files (default) |
| Graph | `graph import` | Import PHP analysis into graph database |
| Show | `show entity` | Analyze entity dependencies and impact |

Exit codes:

| Code | Meaning |
| :--- | :--- |
| `0` | Success |
| `1` | Generic error (parsing, runtime) |
| `2` | Command-line syntax error (unknown flag/command) |
| `64` | Wrong usage (invalid arguments) |
| `65` | Data error (database, entity not found) |
| `66` | Source not found |
| `73` | Output file/directory error |
| `78` | Config file missing, unreadable, or malformed |

### Test-First Quality

Every behavior affecting documentation, CLI contracts, configuration, parsing, or exit codes is covered by PHPUnit tests. Required coverage: AST parsing, Markdown format, config validation, full generation, incremental diff, error handling.

## Installation

```bash
composer require sinefineinfinitum/ponymator
```

## Usage

```bash
# Main help
vendor/bin/ponymator --help

# Generate documentation
vendor/bin/ponymator generate [--full | --diff] [--config=<path>] [--output=md|psv1]

# Manage graph database
vendor/bin/ponymator graph import [--db-path=<path>]
vendor/bin/ponymator graph clear

# Analyze entities
vendor/bin/ponymator show entity <name> [--depth=N]
vendor/bin/ponymator show impact <name> [--depth=N]
vendor/bin/ponymator show path <from> <to>
```

### Commands

#### `generate`
Produces documentation from PHP source code.
- `--full`: Force regeneration of all files.
- `--diff`: Only update files that changed since last run (default).
- `--output=md`: (Default) Standard Markdown output.
- `--output=psv1`: [Ponymator Syntax v1](spec-ps-v1.md) (compact, machine-readable format for graph analysis).

#### `graph`
Handles the SQLite graph database used for deep dependency analysis.
- `import`: Scans source code, parses AST, and populates the graph database with entities and relationships.
- `clear`: Drops all tables and recreates the schema in the graph database. Useful for a fresh start or fixing corruption.

#### `show`
Interactive analysis of the dependency graph. Supports FQCN or short names (if unique).
- `entity <name>`: Shows detailed info about an entity (class, method, etc.) and its direct outgoing dependencies (structural and calls).
- `impact <name>`: Performs reverse dependency analysis. Lists all entities that depend on the target, recursively up to `--depth`.
- `path <from> <to>`: Finds the shortest path between two entities. Analyzes both forward (depends on) and reverse (is used by) relationships to show how two parts of the system are connected.
- `--depth=N`: Limits recursion depth for `impact` command (default: 3).
- `--db-path=<path>`: Override the database path from config.

## Generated Documentation Example

The generated Markdown includes YAML frontmatter with a content hash and type, followed by a summary of the entity:

````markdown
---
type: class
hash: 3d8f1b2c9a0e
---

# `App\Service\UserService`

`final class` extends `App\Abstracts\BaseService` implements `App\Contracts\ServiceInterface`

## Constants

| Constant | Visibility | Type | Value |
| :------- | :--------- | :--- | :---- |
| `MAX_RETRIES` | public | int | `3` |

## Properties

- `public string $name`
- `protected ?int $cacheTtl = null`

## Methods

- `public static function create(``string`` $name``, ``array`` $data = []``): ``App\Models\User`
  - **Creates:**
    - [App\Models\User](../Models/User.md)
  - **Calls:**
    - `strong` `App\Service\Logger::log`
    - `strong` [App\Models\User](../Models/User.md)->save
    - `weak` `handleException`

## Used by

- [App\Contract\ServiceInterface](..\Contract\ServiceInterface.md)
- `Vendor\Package\SomeClass`
````

### Markdown Call Graph & Object Creation Rules

1. **Method-Nested Structure**: No global `Creates` or `Call Graph` sections. Object creations (`Creates`) and method calls (`Calls`) are nested directly under their respective method signature.
2. **Human-Readable Association**: Compact symbols (`*`, `?`) are replaced with explicit labels: `` `strong` `` or `` `weak` ``.
3. **No Unknown Targets**: `Unknown` targets are excluded. Unresolved calls list only the called name (labeled `` `weak` ``).
4. **Call Operator Notation**: Type of call is implied by PHP syntax instead of text tags:
   - Static: `Class::method`
   - Dynamic: `Class->method`
5. **No Duplication**: Instantiations via `new` are listed only under `Creates` and excluded from `Calls`.

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
    "ignore": ["vendor", "node_modules"],
    "dbPath": "./db/path"
}
```

## License

MIT

# Ponymator

[![Packagist Version](https://img.shields.io/packagist/v/sinefineinfinitum/mnemosyne)](https://packagist.org/packages/sinefineinfinitum/mnemosyne)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-purple)](https://packagist.org/packages/sinefineinfinitum/mnemosyne)
[![License](https://img.shields.io/packagist/l/sinefineinfinitum/mnemosyne)](https://packagist.org/packages/sinefineinfinitum/mnemosyne)
[![CI](https://img.shields.io/github/actions/workflow/status/sinefineinfinitum/mnemosyne/ci.yml?branch=main)](https://github.com/sinefineinfinitum/mnemosyne/actions)
[![PHPStan level](https://img.shields.io/badge/PHPStan-8-brightgreen)](https://github.com/phpstan/phpstan)
[![Mutation Score](https://img.shields.io/badge/MSI-71%25-yellow)](https://github.com/sinefineinfinitum/mnemosyne/actions)

A CLI-first PHP documentation generator that produces deterministic documentation for a project's API surface.

## Principles

### AST-First Correctness

Ponymator uses PHP Abstract Syntax Tree analysis (via [`nikic/php-parser`](https://github.com/nikic/PHP-Parser)) as the single source of truth. API extraction — classes, interfaces, traits, enums, constants, properties, methods, signatures, inheritance, implemented interfaces, modifiers, and dependencies — is derived from parsed PHP source code, never from regex or string matching. If source code cannot be parsed, the tool fails with actionable diagnostics.

### Deterministic Output

For identical source code and configuration, repeated runs produce byte-identical documentation files. Ordering of classes, methods, dependencies, imports, modifiers, and sections is fully deterministic, making output suitable for CI comparison.

### CLI-First Experience

| Mode | Flag | Description |
| :--- | :--- | :--- |
| Full | `generate --full` | Regenerate all documentation |
| Diff | `generate --diff` | Regenerate only changed files (default) |
| Graph | `graph import` | Import PHP analysis into graph database |
| Show | `show entity` | Analyze entity dependencies and impact |
| Detect | `detect` | Detect design patterns in the codebase |

Exit codes:

| Code | Meaning |
| :--- | :--- |
| `0` | Success |
| `1` | Generic error (config, parsing, runtime) |
| `2` | Command-line syntax error (unknown flag/command) |
| `64` | Wrong usage (invalid arguments) |
| `65` | Data error (database, entity not found) |
| `66` | Source not found |
| `73` | Output file/directory error |
| `78` | Config file missing, unreadable, or malformed |

### Test-First Quality

Every behavior affecting documentation, CLI contracts, configuration, parsing, or exit codes is covered by PHPUnit tests. Required coverage: AST parsing, documentation format, config validation, full generation, incremental diff, error handling.

## Installation

```bash
composer require sinefineinfinitum/mnemosyne
```

## Usage

```bash
# Main help
vendor/bin/ponymator --help

# Generate documentation
vendor/bin/ponymator generate [--full | --diff] [--config=<path>] [--output=md|msv1]

# Manage graph database
vendor/bin/ponymator graph import [--db-path=<path>]
vendor/bin/ponymator graph clear

# Analyze entities
vendor/bin/ponymator show entity <name> [--depth=N]
vendor/bin/ponymator show impact <name> [--depth=N]
vendor/bin/ponymator show path <from> <to>

# Detect patterns
vendor/bin/ponymator detect [--config=<path>]
```

### Commands

#### `generate`
Produces documentation from PHP source code. Supports multiple output formats:
- `--output=md`: (Default) Human-readable **Markdown** output with YAML frontmatter, method signatures, and dependency lists. Ideal for developer portals and GitHub/GitLab rendering.
- `--output=msv1`: **Mnemosyne Syntax v1** (MSV1) — a compact, machine-readable format designed for rapid parsing and deep graph analysis. It minimizes noise while preserving structural information.

Options:
- `--full`: Force regeneration of all files.
- `--diff`: Only update files that changed since last run (default).

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

#### `detect`
Scans the imported codebase (via `graph import`) and identifies common **Design Patterns** (e.g., Strategy, Observer, Factory Method). It helps in understanding the architectural intent of the code.

## Generated Documentation Example (Markdown)

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
- ext-pdo & ext-pdo_sqlite (for graph features)

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
├── Analyzer/               # AST parsing and entity extraction
├── Cli/                    # Command-line interface and commands
├── Comparator/             # File change detection
├── Db/                     # Database access layer
├── Detector/               # Design pattern detection logic
├── Documentation/          # Documentation rendering and processing
├── Filesystem/             # File and path management
├── Graph/                  # Dependency graph logic
├── Msv1Parser/             # Parser for MSV1 format
├── Config.php              # Configuration management
└── Mnemosyne.php           # Application entry point
```

## Configuration

Configuration file: `.mnemosyne.json`. Example:
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

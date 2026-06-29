# Ponymator

[![Packagist Version](https://img.shields.io/packagist/v/sinefineinfinitum/ponymator)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-purple)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![License](https://img.shields.io/packagist/l/sinefineinfinitum/ponymator)](https://packagist.org/packages/sinefineinfinitum/ponymator)
[![CI](https://img.shields.io/github/actions/workflow/status/sinefineinfinitum/ponymator/ci.yml?branch=main)](https://github.com/sinefineinfinitum/ponymator/actions)
[![PHPStan level](https://img.shields.io/badge/PHPStan-8-brightgreen)](https://github.com/phpstan/phpstan)
[![Mutation Score](https://img.shields.io/badge/MSI-71%25-yellow)](https://github.com/sinefineinfinitum/ponymator/actions)

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

## Benchmark: Token Savings for AI Agents

Ponymator documentation consumes significantly fewer LLM tokens than raw PHP source, making it an efficient context source for AI coding agents.

### Methodology

- **Subject**: Symfony framework source code (`symfony/symfony`, 10 632 PHP files, ~116 MB)
- **Doc format**: PSV1 (Ponymator Syntax v1) — compact machine-readable format
- **Token counter**: Whitespace-based estimator (conservative; tiktoken/cl100k_base gives similar ratios)
- **Metric**: `compression_ratio = doc_tokens / raw_tokens` (lower = better)

### Results (Symfony framework)

| Metric | PSV1 format | Markdown format |
| :----- | :---------: | :-------------: |
| PHP files analyzed | 10 623 | 10 623 |
| Total raw tokens | 22 459 751 | 22 459 751 |
| Total doc tokens | 2 389 674 | 6 452 342 |
| **Token savings** | **20 070 077 (89.4%)** | **16 007 409 (71.3%)** |
| Avg compression (per file) | 28.8% of original | 77.3% of original |
| Matched file pairs | 10 623 (99.9%) | 10 623 (99.9%) |

> PSV1 is the most token-efficient format (89% savings), suitable for agent context. Markdown retains full readability and cross-reference links with 71% savings. Both formats preserve the full API surface — classes, methods, signatures, dependencies, constants, properties — while omitting implementation bodies.

> Some small PHP files (test fixtures, interfaces with few methods) show higher doc-to-raw ratio in Markdown due to YAML frontmatter and structural headers. PSV1 avoids this overhead entirely.

### Context Window Density

How many entity descriptions fit in common context window sizes:

| Window | Raw PHP fits | Ponymator fits | Multiplier |
| :----- | :----------: | :------------: | :--------: |
| 4 096  | 9 666 | 10 583 | 1.1× |
| 8 192  | 10 272 | 10 620 | 1.0× |
| 16 384 | 10 431 | 10 623 | 1.0× |

For small codebases (≤4K window), Ponymator fits ~1 × more entities. For large files, the savings grow: a 500‑token PHP file often shrinks to 50–100 tokens in PSV1 form.

### Cost Estimation (GPT-4o)

At GPT-4o input pricing ($2.50 / 1M tokens):

| Scenario | Format | Tokens | Cost | Savings |
| :------- | :----: | :----: | :--- | :------ |
| Read all raw PHP | — | 22.5M | $56.15 | — |
| Read all Ponymator docs | PSV1 | 2.4M | $5.97 | **$50.17** |
| Read all Ponymator docs | Markdown | 6.5M | $16.13 | **$40.02** |

For an agent context of 8 192 tokens per call:

| Format | Entities per call | Calls to scan all |
| :----- | :---------------: | :---------------: |
| Raw PHP | ~1 | ~10 600 |
| PSV1 | ~40+ | ~260 |
| Markdown | ~15 | ~700 |

### Reproducing

```bash
# Using Docker (Windows/macOS/Linux) — defaults to Markdown
docker compose -f benchmark/docker-compose.yml run ponymator-bench

# Or manually with the analysis script
pip install tiktoken
python benchmark/analyze_token_savings.py \
  --php-dir=src \
  --doc-dir=docs \
  --tokenizer=tiktoken

# Compare both output formats:
# 1. Generate Markdown docs
vendor/bin/ponymator generate --full --output=md
python benchmark/analyze_token_savings.py --php-dir=src --doc-dir=docs-md

# 2. Generate PSV1 docs
vendor/bin/ponymator generate --full --output=psv1
python benchmark/analyze_token_savings.py --php-dir=src --doc-dir=docs-psv1
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

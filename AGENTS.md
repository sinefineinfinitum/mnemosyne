# Agent Instructions for Ponymator

## Project Purpose

Ponymator is a CLI-first PHP documentation generator. Its primary responsibility is to analyze PHP source code and generate deterministic Markdown documentation for the public API of a project.

The generated documentation must accurately reflect the actual PHP source code and be reliable enough for local development, automation, and CI pipelines.

## Core Rules

### 1. AST-First Correctness

Use PHP Abstract Syntax Tree analysis as the source of truth.

Do not implement public API extraction using regex-based parsing, ad-hoc string scanning, or fragile text matching.

Documentation-related behavior must be derived from parsed PHP source code, including:

- Public classes
- Interfaces
- Traits
- Enums
- Public methods
- Method signatures
- Inheritance
- Implemented interfaces
- Modifiers
- Dependencies/imports

If PHP source code cannot be parsed reliably, fail clearly with actionable diagnostics instead of generating incomplete or misleading documentation.

### 2. Deterministic Markdown Output

Generated Markdown must be stable and reproducible.

For identical source code and configuration, repeated runs must produce byte-identical documentation files.

Keep ordering deterministic for:

- Classes
- Interfaces
- Traits
- Enums
- Methods
- Dependencies
- Imports
- Modifiers
- Generated sections

Treat formatting changes in generated Markdown as product behavior changes. Avoid unnecessary output churn.

### 3. CLI-First Developer Experience

Ponimator is a command-line developer tool. CLI behavior must be predictable, scriptable, and CI-friendly.

The CLI should support clear modes for:

- Full documentation generation
- Incremental updates
- Freshness/check mode

Use output streams consistently:

- Standard output: normal progress, summaries, and user-facing success information
- Standard error: failures, invalid configuration, parse errors, diagnostics, and unexpected runtime problems

Exit codes must be meaningful:

- `0`: successful execution or up-to-date documentation checks
- Non-zero: outdated documentation, invalid configuration, parse failures, file system errors, or unexpected runtime failures

Do not introduce behavior that makes the tool difficult to use in automation scripts or CI pipelines.

### 4. Test-First Quality

Any behavior affecting generated documentation, CLI contracts, configuration handling, parsing, or exit codes must be covered by automated tests.

Prefer writing tests before or alongside implementation.

Every user-visible behavior should have an independent test case where practical.

Required test coverage areas include:

- AST parsing of supported PHP language constructs
- Markdown generation format
- Configuration loading and validation
- Full generation mode
- Incremental diff/update mode
- Check mode and exit codes
- Error handling and diagnostics

## Implementation Guidance

### PHP Version

Use PHP 8.0-compatible code unless the project configuration explicitly changes.

Avoid language features introduced after PHP 8.0.

### Tests

Use PHPUnit for automated tests.

When changing public behavior, add or update tests in the relevant test suite before considering the work complete.

### Documentation Generation

Generated documentation should prioritize:

- Accuracy
- Determinism
- Human readability
- CI-friendly comparison

Avoid changes that make generated files noisy or unstable between runs.

### Error Handling

Prefer explicit, actionable errors.

When parsing, configuration, or file system operations fail, the user should understand:

- What failed
- Where it failed
- How to fix or investigate it

Do not silently ignore failures that can affect generated documentation correctness.

## Agent Checklist

Before completing a code change, verify:

- The implementation uses AST-based PHP analysis where public API extraction is involved.
- Generated Markdown output remains deterministic.
- CLI output uses stdout and stderr appropriately.
- Exit codes match the documented contract.
- Relevant PHPUnit tests were added or updated.
- Existing tests still pass.
- No PHP features newer than PHP 8.0 were introduced.
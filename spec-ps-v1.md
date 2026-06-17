# Ponymator Syntax (PS) v1.1

Minimal, deterministic syntax for describing code structure as a graph.

## Core

### Goals

- **Minimal sigil set** — each symbol has a single visual meaning; contextual overloading only where context is unambiguous
(e.g. `<` is `implements` at entity level, generic bracket in types).
- **Deterministic** — identical source code always produces identical output. No ordering ambiguity, no optional sections.
- **Graph-first** — every line maps to one node and one edge to its parent.
Ready for import into graph databases and dependency analyzers.
- **Language-agnostic** — entities, visibility, inheritance, types, and members exist in most OOP languages.
- **No boilerplate** — one declaration per line, hierarchy by indentation only, no brackets, no commas, no closing tags.

### Core symbols

| Symbol / key                           | Meaning                                                                                           |
|----------------------------------------|---------------------------------------------------------------------------------------------------|
| `@`                                    | entity type (`class`, `interface`, `trait`, `enum`, `file`) — reserved `@[a-z]+` for future entity types |
| `>`                                    | extends                                                                                           |
| `<`                                    | implements (entity level)                                                                         |
| `$`                                    | property, parameter, global variable                                                              |
| `!`                                    | constant                                                                                          |
| `=`                                    | assignment (default value, enum case value)                                                       |
| `.`                                    | member — method (under `@class`), function (under `@file`)                                        |
| `:`                                    | type / return type                                                                                |
| `^`                                    | creates instance                                                                                  |
| `*` `?`                                | calls — `*` resolved, `?` weak (see Call Graph)                                                   |
| `+` `-` `#`                            | visibility: public, private, protected for OOP                                                    |
| `\|`                                   | union type                                                                                        |
| `&`                                    | by-ref (parameter level)                                                                          |
| `~`                                    | case (under `@enum`)                                                                              |
| `final` `abstract` `static` `readonly` | language keywords (after the entity/member name)                                                  |

### Core rules

1. Keywords (`final`, `abstract`, `static`, `readonly`) MUST follow the entity or member name on the same line:
2. Indentation defines nesting: one level of exactly 4 spaces (MUST NOT use tabs) for children of a `.` block.
3. One line per declaration — no inlining of methods, properties, or constants.
4. `<` and `>` at the start of a directive line mean `implements` / `extends`. Within a type expression after `:`,
the same characters are part of generic type syntax (e.g. `Collection<User>`, `array<string,int>`)
— context resolves the ambiguity.

---

## PHP binding

This section defines how Core maps to PHP.

### PHP-specific symbols

| Symbol | Meaning    |
|--------|------------|
| `%`    | trait use  |

### Entity-type rules

1. New entity types MUST use the `@[a-z]+` pattern and be registered in the symbol table.
   Parsers MUST reject unknown `@` directives with a clear error.

2. `@file` supports a **limited subset** of core symbols:
   - Allowed: `$` (global variable), `!` (file constant), `.` (function), `:` (type), `=` (default value)
   - Not allowed: `>`, `<`, `%`, `^`, `~`, `+`/`-`/`#`, `&`
   These symbols are either OOP-specific or entity-level and have no meaning in
   a procedural file context.

### Naming

1. Names MUST use FQCN format (e.g. `App\Service\SearchService`) for all entity types except `@file`,
which MUST use a file path relative to the project root.
2. Constant names MUST NOT start with `$` or contain whitespace. The `$` prefix is reserved
   for properties, parameters, and global variables.
3. All primitives MUST be lowercase.

### Call Graph (v1.1)

Each call inside a method or function body is emitted as a **single line**
indented 4 spaces under the owning member.

#### Format

```
MARKER FQCN SEPARATOR TARGET
```

- `MARKER` is `*` (resolved, single FQCN) or `?` (weak: multiple candidates, unresolved variable, or non-final `static`).
- `SEPARATOR` is `::` for static calls, `->` for dynamic calls. For global function calls the separator is omitted — `*App\Util\formatDate`.
- For dynamic calls with multiple candidate types, one line per candidate is emitted (all marked `?`).
- For dynamic calls with no resolvable candidate, the line is omitted.
- For static calls with no resolvable class (unknown / external FQCN), the line is emitted as `?FQCN::method`.

`^` (Creates) is **not** part of the call graph — it remains a separate sigil for `new` expressions.

#### Resolution of call-site keywords

| Source                          | Resolves to                                                                                |
|---------------------------------|--------------------------------------------------------------------------------------------|
| `$this->method()`               | `*App\Current\ClassName->method`                                                            |
| `self::method()`                | `*App\Current\ClassName::method`                                                            |
| `parent::method()`              | `*App\Parent\ClassName::method` (resolved against the `extends` chain)                      |
| `static::method()` in `final`   | `*App\Current\ClassName::method`                                                            |
| `static::method()` non-`final`  | `?App\Current\ClassName::method` (late-static — any subclass is possible)                  |

#### Global function calls

Only **project-defined** functions appear. PHP language functions (`strlen`, `array_map`, `count`, etc.) MUST NOT be emitted.

- A function is "project-defined" if it is declared at file level anywhere in the project, or is a method of a class/interface/trait/enum in the project type index.
- A namespaced project function is emitted as `*App\Util\formatDate` (no separator).
- A **non-namespaced** project function is emitted as `*my_plugin_init` — bare function name, no separator, no leading backslash.
- A call to a non-project function (PHP built-in or undefined) is silently dropped from the call graph.

#### External dependencies

- External symbols are emitted exactly like project symbols: `*` for a single resolved target, `?` for multiple candidates.
- Unresolvable static calls are omitted. Association strength reflects resolution certainty, not project ownership.

#### Deduplication and ordering

- One line per unique `(kind, FQCN, method)` triple within a single member. Repeated calls in the source collapse to a single line.
- Method-chains (`$a->b()->c()`) emit one line per `->` in the chain (the immediate receiver of each call).
- Calls within a member are emitted in **source order** (the order in which they appear in the method body).
- Members within an entity are emitted in **source order** (the order of declaration in the source file).
- This applies per-file: each `@file` / `@class` / `@trait` / `@enum` document is ordered by the position of the declaration in its source file.

#### Examples

```
.+parse
    *Ponymator\Parser\Parser::parseFile
    ?App\Service\Handler1->process
    ?App\Service\Handler2->process
    *App\Util\formatDate
```

### PHP primitives

Built-in PHP types used without namespace:

`int` `float` `string` `bool` `array` `object` `callable` `iterable`
`void` `never` `null` `mixed` `self` `static` `true` `false`

### PHP examples

#### OOP — class

```
@class final App\Service\SearchService
>App\Core\BaseService
<App\Contracts\SearchInterface
%App\LoggableTrait

$-vectorStore readonly:App\Storage\VectorStore
$-mixedResult:int|string|null

!+DEFAULT_LIMIT:int=25

.+search final
    $query:App\Query\SearchQuery
    :App\Search\SearchResult|null
    ^App\Search\SearchResult

.+merge static
    &$source:array
    $limit:int=10
    :array

.+setStatus
    $status:int|string
    :void

```

Nullable is expressed via union with `null`:

```
.+find
    $id:int
    :User|null
```

#### Trait with trait-use

```
@trait App\Traits\TimestampsTrait
%App\Traits\LoggableTrait

$+createdAt:\DateTimeImmutable

.+touch
    :void
```

Traits can use other traits via `%`. The order follows alphabetical sorting
for deterministic output.

#### Enum

```
@enum App\Status
~Active=1
~Inactive:int=2
~Pending
```

#### Procedural — file

```
@file src/functions.php

.getUser
    $id:int
    :App\Entity\User|null

!MAX_RETRIES:int=3

$debugMode:bool=false
```

---

## File extension

Generated PS v1.x documentation files use the **`.psv1`** extension.

Output files mirror the source directory structure. For a source file at `src/Service/SearchService.php`,
the generated documentation is written to `target/Service/SearchService.psv1`.

This includes:
- Entity files (classes, interfaces, traits, enums) — output path mirrors the source `.php` location
- File documentation (procedural files) — same source-to-output mapping

---

## Versioning

PS uses **semantic versioning** for the syntax specification.

The current version is **v1.1**.

### Compatibility policy

| Change type | Version bump | Examples |
|---|---|---|
| Addition (backward-compatible) | Minor (v1.0 → v1.1) | New symbol, new optional keyword |
| Fix (no syntax change) | Patch (v1.0 → v1.0.1) | Clarified wording, corrected example |
| Breaking change | Major (v1.0 → v2.0) | Symbol repurposing, removal, changed meaning |

### Breaking changes (v2.0+)

The following would trigger a major version bump:

- Changing the meaning of an existing symbol.
- Removing or renaming a symbol.
- Changing indentation rules.
- Making a previously required element optional or vice versa.

Non-breaking additions (minor version) must not change the interpretation of any valid v1.0
document. Parsers written for v1.0 MUST be able to safely ignore unknown optional elements
introduced in v1.1, v1.2, etc.

## Known limitations (v1.1)

- **Non-literal defaults** — complex expressions (`1 + 2`, `__FILE__`, ternary) render as `null`.
- **Array defaults** — render as `[]` with no key/value detail.
- **Float precision** — whole-number floats (`1.0`) render as `"1"`. Deterministic but loses type distinction.

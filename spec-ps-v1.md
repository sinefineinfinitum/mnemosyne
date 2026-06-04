# Ponymator Syntax (PS) v1.0

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

| Symbol / key | Meaning                                                               |
|--------------|-----------------------------------------------------------------------|
| `@`          | entity type (`class`, `interface`, `trait`, `enum`, `file`) — reserved `@[a-z]+` for future entity types |
| `>`          | extends                                                               |
| `<`          | implements (entity level)                                             |
| `$`          | property, parameter, global variable                                  |
| `!`          | constant                                                              |
| `=`          | assignment (default value, enum case value)                           |
| `.`          | member — method (under `@class`), function (under `@file`)            |
| `:`          | type / return type                                                    |
| `^`          | creates instance                                                      |
| `+` `-` `#`  | visibility: public, private, protected for OOP                        |
| `\|`         | union type                                                            |
| `&`          | by-ref (parameter level)                                              |
| `~`          | case (under `@enum`)                                                  |
| `final` `abstract` `static` `readonly` | language keywords (after the entity/member name)                      |

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

$-readonly vectorStore:App\Storage\VectorStore
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

Generated PS v1.0 documentation files use the **`.psv1`** extension.

Output files mirror the source directory structure. For a source file at `src/Service/SearchService.php`,
the generated documentation is written to `target/Service/SearchService.psv1`.

This includes:
- Entity files (classes, interfaces, traits, enums) — output path mirrors the source `.php` location
- File documentation (procedural files) — same source-to-output mapping

---

## Versioning

PS uses **semantic versioning** for the syntax specification.

The current version is **v1.0**.

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

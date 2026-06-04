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
| `@`          | entity type (`class`, `interface`, `trait`, `enum`, `file`)           |
| `>`          | extends                                                               |
| `<`          | implements (entity level)                                             |
| `$`          | property, parameter, global variable                                  |
| `!`          | constant                                                              |
| `=`          | assignment (default value, enum case value)                           |
| `.`          | member — method (under `@class`), function (under `@file`)            |
| `:`          | type / return type                                                    |
| `^`          | creates instance                                                      |
| `+` `-` `#`  | visibility: public, private, protected for OOP                        |
| `?`          | nullable — MUST be placed immediately after `:`, before the type name |
| `\|`         | union type                                                            |
| `&`          | by-ref (parameter level)                                              |
| `~`          | case (under `@enum`)                                                  |
| `final` `abstract` `static` `readonly` | language keywords (after the entity/member name)                      |

### Core rules

1. `?` MUST appear immediately after `:` and before the type name: `:?TypeName`.
2. Keywords (`final`, `abstract`, `static`, `readonly`) MUST follow the entity or member name on the same line:
`@class final Name`, `.+method static`.
3. Indentation defines nesting: one level of indentation (4 spaces) for children of a `.` block.
4. One line per declaration — no inlining of methods, properties, or constants.
5. `<` and `>` at the start of a directive line mean `implements` / `extends`. Within a type expression after `:`,
the same characters are part of generic type syntax (e.g. `Collection<User>`, `array<string,int>`)
— context resolves the ambiguity.

---

## PHP binding

This section defines how Core maps to PHP.

### PHP-specific symbols

| Symbol | Meaning    |
|--------|------------|
| `%`    | trait use  |

### Naming

1. Names MUST use FQCN format (e.g. `App\Service\SearchService`) for all entity types except `@file`,
which MUST use a file path relative to the project root.
2. All primitives MUST be lowercase.

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
    :?App\Search\SearchResult
    ^App\Search\SearchResult

.+merge static
    &$source:array
    $limit:int=10
    :array

.+setStatus
    $status:int|string
    :void

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
    :?App\Entity\User

!MAX_RETRIES:int=3

$debugMode:bool=false
```

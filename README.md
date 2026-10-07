<h1 align="center">Collection 📁</h1>

<p align="center">
  Laravel-style collections for any PHP project — with dot-notation paths in every method.
</p>

<p align="center">
  <a href="https://packagist.org/packages/collection/collection"><img src="https://img.shields.io/packagist/v/collection/collection" alt="Latest version"></a>
  <a href="https://github.com/chipslays/collection/actions/workflows/php.yml"><img src="https://github.com/chipslays/collection/actions/workflows/php.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/collection/collection"><img src="https://img.shields.io/packagist/php-v/collection/collection" alt="PHP version"></a>
  <a href="https://packagist.org/packages/collection/collection"><img src="https://img.shields.io/packagist/dt/collection/collection" alt="Downloads"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/packagist/l/collection/collection" alt="License"></a>
</p>

---

## Why Collection

- **Paths everywhere.** `get`, `set`, `where`, `sortBy`, `groupBy`, `sum`, `pluck` and dozens of other methods accept paths like `user.address.city`, `users.*.email`, `users[?active].email` or `**.email`. Paths go through arrays, objects and nested collections — and stay close to hand-written loops in speed.
- **Reshape data in one call.** `rename()` and `remap()` move fields into the structure you need; `cast()` converts types with built-in casters, enums, classes, callbacks or your own `Caster`.
- **The Laravel API you already know.** Every method of `Illuminate\Support\Collection`, with the same names and behaviour — but no framework required. [What is different 🤔](docs/laravel.md)
- **You stay in control.** Methods that wrap PHP functions expose their options: `SORT_*` flags, JSON flags and depth, `preserveKeys`, a seeded `Random\Randomizer`, rounding modes.
- **No surprises.** Transforming methods return a new collection; the few that change data in place are [clearly listed](docs/getting-started.md#new-collection-or-in-place). Strings are never called as functions by accident.
- **Zero dependencies.** PHP 8.4+, one package, fully tested and statically analysed.

---

## Installation

```bash
composer require collection/collection
```

```php
use Collection\Collection;

$c = new Collection($array);
$c = Collection::make($arrayOrJsonOrIterable);
$c = collection($array); // global helper
```

---

## Documentation

| Section | Description |
|---|---|
| [Getting Started](docs/getting-started.md) | creating collections, conventions, exceptions, subclassing |
| [Paths: Dot-Notation & Wildcards](docs/paths.md) | `user.name`, `users.*.email`, `users.*[id]`, `users[?age>18]`, `**.email`, objects, escaping dots |
| [Reading, Writing & Converting](docs/reading-writing.md) | `get`, `set`, `has`, `forget`, `push`, `toJson`… |
| [Filtering & Finding](docs/filtering.md) | `where`, `filter`, `first`, `sole`, `contains`, `unique`… |
| [Transforming](docs/transforming.md) | `map`, `pluck`, `mapPath`, `flatten`, `toTree`, `reduce`… |
| [Reshaping: rename, remap & cast](docs/reshaping.md) | rename/move keys, build new structures, convert types |
| [Sorting & Grouping](docs/sorting-grouping.md) | `sortBy`, `groupBy`, `keyBy`, `countBy`, `partition` |
| [Aggregates](docs/aggregates.md) | `sum`, `avg`, `median`, `mode`, `percentage`… |
| [Slicing, Chunking & Randomness](docs/slicing.md) | `slice`, `chunk`, `paginate`, `sliding`, `random`… |
| [Combining Collections](docs/combining.md) | `merge`, `diff`, `intersect`, `zip`, `crossJoin`… |
| [Pipelines, Macros & Debugging](docs/pipelines.md) | `pipe`, `when`, higher-order messages, macros, `dump` |
| [Coming from Laravel](docs/laravel.md) | what is the same and what is different |

**[All methods at a glance 👉](docs/README.md#all-methods)**

---

## Contributing

```bash
composer test # Pest
composer analyse # PHPStan
```

Issues and pull requests are welcome!

## License

MIT — see [LICENSE.md](LICENSE.md).

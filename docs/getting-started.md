# Getting Started

[👈 Documentation](README.md)

- [Installation](#installation)
- [Creating a collection](#creating-a-collection)
- [Conventions](#conventions)
  - [New collection or in place?](#new-collection-or-in-place)
  - [Keys are preserved](#keys-are-preserved)
  - [Callbacks](#callbacks)
  - [Conditions (where-style arguments)](#conditions-where-style-arguments)
  - [Missing paths read as null](#missing-paths-read-as-null)
- [Exceptions](#exceptions)
- [Extending the class](#extending-the-class)

---

## Installation

Requires **PHP 8.4+**. No other dependencies.

```bash
composer require collection/collection
```

---

## Creating a collection

```php
use Collection\Collection;

$c = new Collection(['apple', 'banana']);
$c = Collection::make(['apple', 'banana']);
$c = collection(['apple', 'banana']);          // global helper, same as make()
```

The constructor accepts arrays, other collections, `Arrayable`, `Traversable`, `JsonSerializable`, objects with a `toArray()` method (Laravel collections, Eloquent models…), `null` (empty collection) or a single value (wrapped into an array).

### make()

```php
Collection::make(mixed $items = [], string $wildcard = '*', string $delimiter = '.'): static
```

Same as the constructor, but a string holding a JSON **array or object** is decoded:

```php
Collection::make('{"user": {"name": "Alice"}}')->get('user.name'); // 'Alice'
Collection::make('hello')->all();                                  // ['hello']
```

### fromJson()

```php
Collection::fromJson(string $json, int $depth = 512, int $flags = 0, string $wildcard = '*', string $delimiter = '.'): static
```

`$depth` and `$flags` are passed to `json_decode()`; `JSON_THROW_ON_ERROR` is always on. JSON that is not an array or object throws `InvalidArgumentException`.

```php
Collection::fromJson($json, flags: JSON_BIGINT_AS_STRING);
```

### wrap(), unwrap(), empty()

```php
Collection::wrap('hello');        // ['hello']
Collection::wrap(null);           // []
Collection::wrap($collection);    // a copy with the same wildcard and delimiter
Collection::unwrap($collection);  // the plain array; any other value is returned unchanged
Collection::empty();
```

### times(), range()

```php
Collection::times(3);                   // [1, 2, 3]
Collection::times(3, fn($i) => $i * 2); // [2, 4, 6]
Collection::range(1, 5);                // [1, 2, 3, 4, 5]
Collection::range(0, 1, 0.25);          // [0, 0.25, 0.5, 0.75, 1.0]
Collection::range('a', 'e');            // ['a', 'b', 'c', 'd', 'e']
```

---

## Conventions

### New collection or in place?

Every method that transforms data returns a **new** collection and leaves the original untouched.

These methods change the collection **in place** (and return it for chaining, unless they return a removed value):

`set`, `put`, `forget`, `remove`, `pull`, `getOrPut`, `push`, `add`, `prepend`, `unshift`, `pop`, `shift`, `splice`, `transform`, `clear`, and `$c[...] = ` / `unset($c[...])`.

### Keys are preserved

Like Laravel, filtering, sorting and slicing keep the original keys. Call `values()` when you need a list:

```php
Collection::make([1, 2, 3, 4])->filter(fn($n) => $n % 2 === 0)->all();           // [1 => 2, 3 => 4]
Collection::make([1, 2, 3, 4])->filter(fn($n) => $n % 2 === 0)->values()->all(); // [2, 4]
```

### Callbacks

Callbacks receive `($value, $key)`.

- **Strings are never treated as callables.** `sum('count')` sums the `count` field, `contains('trim')` looks for the string `'trim'`. Pass a closure or a first-class callable (`strlen(...)`) when you mean a function.
- **Built-in PHP functions receive only the value.** `map('trim')`, `map(intval(...))`, `filter(is_numeric(...))` are safe even though the key would otherwise be passed as a second argument.

### Conditions (where-style arguments)

`where`, `filter`, `reject`, `contains`, `every`, `firstWhere`, `firstOrFail`, `sole`, `hasSole`, `hasMany` and `partition` accept the same forms:

| Arguments | Meaning |
|---|---|
| `(fn($item, $key) => bool)` | the callback decides |
| `('path')` | value at path is truthy |
| `('path', $value)` | value at path `==` `$value` (`filter`/`reject`: `===`) |
| `('path', $operator, $value)` | `=` `==` `===` `!=` `<>` `!==` `<` `>` `<=` `>=` `<=>` |

An unknown operator throws `InvalidArgumentException`.

### Missing paths read as null

Inside items, a path that does not exist reads as `null`, exactly like Laravel's `data_get()`. So `whereNull('deleted_at')` also matches items without that key, and `groupBy('role')` puts items without a role under the `''` key.

---

## Exceptions

| Exception | Thrown by | Extends |
|---|---|---|
| `Collection\Exceptions\ItemNotFoundException` | `sole()`, `firstOrFail()` | `UnderflowException` 👉 `RuntimeException` |
| `Collection\Exceptions\MultipleItemsFoundException` | `sole()` (has `getCount()`) | `OverflowException` 👉 `RuntimeException` |
| `InvalidArgumentException` | bad operator, bad pattern, invalid sizes/steps, bad wildcard/delimiter, scalar JSON | |
| `UnexpectedValueException` | `ensure()`, `reduceSpread()` | |
| `JsonException` | invalid JSON in `make()`/`fromJson()`/`toJson()` | |

---

## Extending the class

Methods create new instances with `new static($items, $wildcard, $delimiter)`, so a subclass must keep that constructor signature:

```php
class UserCollection extends Collection
{
    public function active(): static
    {
        return $this->where('active', true);
    }
}

UserCollection::make($users)->active()->sortBy('name'); // still a UserCollection
```

`toBase()` turns any subclass back into a plain `Collection`.

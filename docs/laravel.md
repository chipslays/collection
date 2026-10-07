# Coming from Laravel

[👈 Documentation](README.md)

Collection implements every method of `Illuminate\Support\Collection` with the same signatures and semantics: keys are preserved, `where()` compares loosely, `has()` checks that a key exists, higher-order messages (`$users->sum->price`) work, `sole()`/`firstOrFail()` throw `ItemNotFoundException`/`MultipleItemsFoundException`.

It works without Laravel and accepts Laravel collections as input:

```php
$c = Collection::make($laravelCollection);
$c = Collection::make($eloquentModel);   // anything with toArray()
```

## What is different

| | Laravel | Collection |
|---|---|---|
| `get`, `set`/`put`, `has`, `hasAny`, `forget`, `pull`, `getOrPut`, `only`, `except` | top-level keys only | [dot-notation paths](paths.md) with wildcards; an exact top-level key still wins |
| `$c['a.b']` | the key `'a.b'` | the path `a.b` |
| `make('[1,2]')` | `['[1,2]']` | decodes a JSON array/object string |
| `filter()`, `reject()` | callable only | also where-style arguments; `('path', $value)` compares **strictly** |
| `when()` / `unless()` result | whatever the callback returns | a returned collection continues the chain, anything else keeps `$this` |
| `mode()` | scalar values only | any values |
| `random()`, `shuffle()` | global RNG | optional `Random\Randomizer` |
| `sort*`, `unique`, `median`, `slice`, `reverse`, `chunk`, `toJson`, `fromJson`, `percentage` | | extra parameters for the wrapped PHP functions (flags, depth, preserveKeys, rounding mode…) |
| built-in functions as callbacks | receive `($value, $key)` | receive only the value: `map('trim')` is safe |
| `lazy()` | `LazyCollection` | not available yet |

## Extra methods

Not in Laravel:

- path queries: filters `users[?age>=18]`, recursive descent `**.email` — in every method that takes a path
- reshaping: `rename`, `remap`, `cast`, `castUsing`
- paths: `set` with `overwrite: false`, `hasAll`, `hasKey`, `remove`, `collect('path')`, `count('path')`, `keys('path')`, `mapPath`, `evolve`, `find`
- items: `filterMap`, `mapKeys`, `firstKey`, `lastKey`, `insert`, `copy`, `clear`, `isList`, `isAssoc`
- filters: `whereContains`, `whereStartsWith`, `whereEndsWith`, `whereMatches`
- aggregates: `product`, `minBy`, `maxBy`, `standardDeviation`
- structure: `toTree`, `transpose`, `unzip`, `scan`, `symmetricDiff`, `paginate`, `ksort`

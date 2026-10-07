# Sorting & Grouping

[👈 Documentation](README.md)

Sorting returns a new collection and preserves keys. `$options` accepts the native `SORT_*` flags (`SORT_NUMERIC`, `SORT_STRING`, `SORT_NATURAL`, `SORT_FLAG_CASE`, `SORT_LOCALE_STRING`).

- Sorting: [sort](#sort) · [sortDesc](#sortdesc) · [sortBy](#sortby) · [sortByDesc](#sortbydesc) · [sortKeys / sortKeysDesc](#sortkeys--sortkeysdesc) · [sortKeysUsing / ksort](#sortkeysusing--ksort)
- Grouping: [groupBy](#groupby) · [keyBy / mapBy](#keyby--mapby) · [countBy](#countby) · [partition](#partition)

---

## Sorting

### sort()

```php
sort(callable|int|null $callback = null): static
```

```php
$c->sort();                               // asort()
$c->sort(SORT_NATURAL | SORT_FLAG_CASE);  // 'img2' before 'IMG10'
$c->sort(fn($a, $b) => $a <=> $b);        // uasort()
```

### sortDesc()

```php
sortDesc(callable|int $options = SORT_REGULAR): static
```

A comparator is reversed for you.

### sortBy()

```php
sortBy(mixed $callback, int $options = SORT_REGULAR, bool $descending = false): static
```

Sort by a path, by a callback returning the sort value, or by several criteria. Missing paths sort as `null`.

```php
$users->sortBy('name');
$users->sortBy('profile.age', descending: true);
$files->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
$users->sortBy(fn($u) => strlen($u['name']));
```

Several criteria — each entry is a path (ascending), `[path, 'asc'|'desc']`, `path => 'asc'|'desc'`, or a comparator `fn($a, $b)`:

```php
$products->sortBy(['category', 'price' => 'desc']);
$products->sortBy([['category', 'asc'], ['price', 'desc']]);
$products->sortBy([fn($a, $b) => $a['rank'] <=> $b['rank'], 'name']);
```

### sortByDesc()

`sortBy($callback, $options, descending: true)`; with an array every criterion is descending.

### sortKeys / sortKeysDesc

```php
sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
sortKeysDesc(int $options = SORT_REGULAR): static
```

### sortKeysUsing / ksort

```php
$c->sortKeysUsing('strnatcasecmp');
$c->ksort();                  // sortKeys()
$c->ksort(SORT_NATURAL);      // sortKeys(SORT_NATURAL)
$c->ksort(fn($a, $b) => ...); // sortKeysUsing()
```

---

## Grouping

### groupBy()

```php
groupBy(mixed $groupBy, bool $preserveKeys = false): static
```

Groups into sub-collections by a path or a callback.

```php
$users->groupBy('role');
$users->groupBy(fn($u) => $u['age'] >= 18 ? 'adult' : 'minor');
$users->groupBy('role', preserveKeys: true);
```

- **Several levels** — pass an array: `groupBy(['country', 'city'])`.
- **Many groups per item** — when the group value is an array, the item joins every group: `$posts->groupBy('tags')`.
- **Keys** are normalised: `null` 👉 `''`, `true`/`false` 👉 `1`/`0`, enums 👉 their value, `Stringable` 👉 string.

### keyBy / mapBy

Key items by a path or callback; later items win on duplicates. `mapBy()` is an alias.

```php
$byId = $users->keyBy('id');
$byEmail = $users->keyBy(fn($u) => strtolower($u['email']));
```

### countBy()

```php
$c->countBy();                                        // ['a' => 2, 'b' => 1]
$users->countBy('role');                              // ['admin' => 2, 'editor' => 5]
$users->countBy(fn($u) => substr($u['email'], strpos($u['email'], '@') + 1));
```

### partition()

Split into `[passing, failing]` collections (keys preserved). Accepts where-style arguments:

```php
[$admins, $others] = $users->partition('role', 'admin');
[$adults, $minors] = $users->partition('age', '>=', 18);
```

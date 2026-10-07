# Combining Collections

[👈 Documentation](README.md)

Every method accepts arrays, collections and any iterable, and returns a new collection.

[merge](#merge) · [mergeRecursive](#mergerecursive) · [replace / replaceRecursive](#replace--replacerecursive) · [concat](#concat) · [union](#union) · [combine](#combine) · [diff…](#diff) · [intersect…](#intersect) · [symmetricDiff](#symmetricdiff) · [zip / unzip](#zip--unzip) · [crossJoin](#crossjoin) · [multiply](#multiply)

---

### merge()

`array_merge()`: string keys overwrite, integer keys are appended.

```php
$defaults->merge($options);
```

### mergeRecursive()

`array_merge_recursive()`.

### replace / replaceRecursive

`array_replace()` / `array_replace_recursive()` — integer keys are significant.

```php
$config->replaceRecursive(['db' => ['host' => 'replica']]);
```

### concat()

Appends every value of the source, ignoring its keys.

### union()

`$this + $items`: adds keys that are missing here.

### combine()

The values of this collection become keys for `$values`:

```php
Collection::make(['name', 'age'])->combine(['Alice', 30]);  // ['name' => 'Alice', 'age' => 30]
```

### diff

Keys are preserved. Each method wraps the matching PHP function:

| Method | PHP function |
|---|---|
| `diff($items)` | `array_diff` |
| `diffUsing($items, $callback)` | `array_udiff` |
| `diffAssoc($items)` | `array_diff_assoc` |
| `diffAssocUsing($items, $callback)` | `array_diff_uassoc` |
| `diffKeys($items)` | `array_diff_key` |
| `diffKeysUsing($items, $callback)` | `array_diff_ukey` |

```php
Collection::make([1, 2, 3, 4])->diff([2, 4]);  // [0 => 1, 2 => 3]
$objects->diffUsing($other, fn($a, $b) => $a->id <=> $b->id);
```

### intersect

| Method | PHP function |
|---|---|
| `intersect($items)` | `array_intersect` |
| `intersectUsing($items, $callback)` | `array_uintersect` |
| `intersectAssoc($items)` | `array_intersect_assoc` |
| `intersectAssocUsing($items, $callback)` | `array_intersect_uassoc` |
| `intersectByKeys($items)` | `array_intersect_key` |

### symmetricDiff()

Values present in exactly one of the two collections (re-indexed):

```php
$before->symmetricDiff($after);  // added + removed values
```

### zip / unzip

```php
Collection::make([1, 2])->zip(['a', 'b'])->toArray();  // [[1, 'a'], [2, 'b']]

[$ids, $names] = Collection::make([[1, 'a'], [2, 'b']])->unzip();
```

`zip()` returns a collection of collections; shorter lists are padded with `null`.

### crossJoin()

```php
Collection::make([1, 2])->crossJoin(['a', 'b']);
// [[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']]
```

### multiply()

`Collection::make([1, 2])->multiply(3)` 👉 `[1, 2, 1, 2, 1, 2]`.

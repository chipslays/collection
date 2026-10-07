# Aggregates

[👈 Documentation](README.md)

Every aggregate takes what to aggregate as:

- `null` — the items themselves
- `'path'` — the value at a [path](paths.md) in each item
- a callable — `fn($item, $key)`

`avg`, `min`, `max`, `median`, `mode` and `standardDeviation` **ignore `null` values** (and therefore items where the path is missing).

[sum](#sum) · [product](#product) · [avg / average](#avg--average) · [min / max](#min--max) · [minBy / maxBy](#minby--maxby) · [median](#median) · [mode](#mode) · [standardDeviation](#standarddeviation) · [percentage](#percentage)

---

### sum()

```php
$orders->sum('total');
$orders->sum(fn($o) => $o['price'] * $o['qty']);
$orders->sum('items.0.price');
```

### product()

```php
$dimensions->product('size');
```

### avg / average

Returns `int`, `float`, or `null` for no values.

```php
$reviews->avg('rating');
```

### min / max

```php
$products->min('price');
$products->max(fn($p) => $p['price'] * (1 - $p['discount']));
```

### minBy / maxBy

The **whole item** holding the smallest/largest value (the first one on ties):

```php
$cheapest = $products->minBy('price');
$best     = $players->maxBy('stats.score');
```

### median()

```php
median(mixed $key = null, int $flags = SORT_REGULAR): int|float|null
```

The mean of the two middle values for an even count.

```php
$scores->median('score');
```

### mode()

```php
mode(mixed $key = null): ?array
```

The most frequent value(s), sorted ascending; `null` for an empty collection. Works with any values, not only scalars.

```php
Collection::make([1, 2, 2, 3, 3])->mode();  // [2, 3]
```

### standardDeviation()

```php
standardDeviation(mixed $key = null, bool $sample = false): ?float
```

Population deviation by default; `sample: true` divides by `n - 1`. `null` when fewer than two values.

### percentage()

```php
percentage(mixed $callback, int $precision = 2, int|RoundingMode $mode = PHP_ROUND_HALF_UP): ?float
```

Percentage (0–100) of items passing a [where-style](getting-started.md#conditions-where-style-arguments) test; `null` for an empty collection.

```php
$users->percentage('active');                         // 66.67
$users->percentage(fn($u) => $u['score'] >= 50, 1);
$users->percentage('active', 0, PHP_ROUND_HALF_EVEN);
```

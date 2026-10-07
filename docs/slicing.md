# Slicing, Chunking & Randomness

[👈 Documentation](README.md)

Keys are preserved unless stated otherwise.

[slice](#slice) · [take / skip](#take--skip) · [forPage](#forpage) · [paginate](#paginate) · [chunk](#chunk) · [chunkWhile](#chunkwhile) · [sliding](#sliding) · [split / splitIn](#split--splitin) · [nth](#nth) · [pad](#pad) · [shuffle](#shuffle) · [random](#random)

---

### slice()

```php
slice(int $offset, ?int $length = null, bool $preserveKeys = true): static
```

```php
$c->slice(2, 3);
$c->slice(-3);
$c->slice(1, preserveKeys: false);
```

### take / skip

```php
$c->take(3);    // first 3
$c->take(-2);   // last 2
$c->skip(10);
```

### forPage()

```php
$c->forPage(2, 15);  // items 16–30
```

### paginate()

```php
paginate(int $perPage, int $page = 1): array
```

```php
$users->paginate(15, 2);
// ['data' => Collection (re-indexed), 'total' => 100, 'per_page' => 15,
//  'current_page' => 2, 'last_page' => 7, 'from' => 16, 'to' => 30]
```

The page is clamped to `1..last_page`.

### chunk()

```php
chunk(int $size, bool $preserveKeys = true): static
```

A collection of collections; `$size < 1` gives an empty collection.

### chunkWhile()

A new chunk starts when `$callback($value, $key, $currentChunk)` returns `false`:

```php
Collection::make([1, 2, 3, 7, 8, 11])
    ->chunkWhile(fn($v, $k, $chunk) => $v === $chunk->last() + 1);
// [[1, 2, 3], [7, 8], [11]]
```

### sliding()

```php
sliding(int $size = 2, int $step = 1): static
```

```php
Collection::make([1, 2, 3, 4])->sliding()->map->values()->toArray();
// [[1, 2], [2, 3], [3, 4]]
```

Throws `InvalidArgumentException` when `$size` or `$step` is below 1.

### split / splitIn

```php
Collection::make(range(1, 10))->split(3);    // sizes 4, 3, 3 (balanced)
Collection::make(range(1, 10))->splitIn(3);  // sizes 4, 4, 2 (fill first groups)
```

### nth()

Every `$step`-th item starting at `$offset`, re-indexed: `nth(2)`, `nth(3, 1)`.

### pad()

```php
Collection::make([1, 2])->pad(4, 0);   // [1, 2, 0, 0]
Collection::make([1, 2])->pad(-4, 0);  // [0, 0, 1, 2]
```

### shuffle()

```php
shuffle(?Random\Randomizer $randomizer = null): static
```

Pass a `Randomizer` for reproducible or cryptographically secure order:

```php
use Random\Randomizer;
use Random\Engine\Mt19937;

$c->shuffle(new Randomizer(new Mt19937(42)));  // same order on every run
```

### random()

```php
random(mixed $number = null, bool $preserveKeys = false, ?Random\Randomizer $randomizer = null): mixed
```

```php
$c->random();                 // one item
$c->random(3);                // collection of 3 items
$c->random(1);                // collection of 1 item
$c->random(fn($c) => intdiv($c->count(), 2));
```

Requesting more items than available throws `InvalidArgumentException`.

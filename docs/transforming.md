# Transforming

[👈 Documentation](README.md)

All methods return a new collection. Callbacks receive `($value, $key)`; keys are preserved unless stated otherwise.

- Mapping: [map](#map) · [mapWithKeys](#mapwithkeys) · [mapToDictionary / mapToGroups](#maptodictionary--maptogroups) · [mapKeys](#mapkeys) · [mapSpread](#mapspread) · [mapInto](#mapinto) · [flatMap](#flatmap) · [filterMap](#filtermap)
- Paths inside items: [mapPath](#mappath) · [evolve](#evolve) · [pluck](#pluck) · [select](#select) · [only / except](#only--except)
- Reshaping: [flatten](#flatten) · [collapse / collapseWithKeys](#collapse--collapsewithkeys) · [dot / undot](#dot--undot) · [flip](#flip) · [reverse](#reverse) · [transpose](#transpose) · [toTree](#totree)
- Folding: [reduce](#reduce) · [reduceSpread](#reducespread) · [scan](#scan)
- Strings: [join](#join) · [implode](#implode)

---

## Mapping

### map()

```php
$c->map(fn($v, $k) => $v * 2);
$c->map('trim');            // built-ins receive only the value
$c->map(strtoupper(...));
```

### mapWithKeys()

The callback returns `[key => value]` pairs:

```php
$users->mapWithKeys(fn($u) => [$u['email'] => $u['name']]);
```

### mapToDictionary / mapToGroups

Group values by the key each callback returns — plain arrays or collections:

```php
$users->mapToDictionary(fn($u) => [$u['dept'] => $u['name']]);  // ['eng' => ['Alice', 'Carol'], ...]
$users->mapToGroups(fn($u) => [$u['dept'] => $u['name']]);      // groups are collections
```

### mapKeys()

Change only the keys: `$c->mapKeys(fn($key, $value) => strtolower($key))`.

### mapSpread()

Each item is spread into arguments; the key comes last:

```php
Collection::make([[1, 2], [3, 4]])->mapSpread(fn($a, $b) => $a + $b);  // [3, 7]
```

### mapInto()

`new $class($value, $key)` for every item; for a backed enum `$class::from($value)`:

```php
$rows->mapInto(UserDto::class);
Collection::make(['active', 'banned'])->mapInto(Status::class);
```

### flatMap()

Map, then collapse one level: `$users->flatMap(fn($u) => $u['roles'])`.

### filterMap()

Map and drop `null`/`false` results in one pass (`0` and `''` are kept):

```php
$users->filterMap(fn($u) => $u['active'] ? $u['email'] : null);
```

---

## Paths inside items

### mapPath()

```php
mapPath(string|int|array $path, callable $callback): static
```

Transforms only the value(s) at a path inside every item and leaves the rest intact. Wildcards apply the callback to each match; missing paths are skipped; objects on the path are cloned, never mutated.

```php
$products->mapPath('price', fn($p) => round($p * 1.2, 2));
$posts->mapPath('tags.*.name', strtolower(...));
```

### evolve()

Several `mapPath()` calls at once:

```php
$users->evolve([
    'name'          => 'trim',
    'email'         => strtolower(...),
    'profile.score' => fn($s) => min($s, 100),
]);
```

### pluck()

```php
pluck(mixed $value, mixed $key = null): static
```

One value per item (a path or a callback); missing paths give `null`. With `$key` the result is keyed by that path.

```php
$users->pluck('name');                 // ['Alice', 'Bob']
$users->pluck('name', 'id');           // [7 => 'Alice', 9 => 'Bob']
$users->pluck('address.city');
$users->pluck(fn($u) => "{$u['first']} {$u['last']}");
```

### select()

Keep only some paths inside every item:

```php
$users->select('id', 'profile.email');
// [['id' => 7, 'profile' => ['email' => '...']], ...]
```

### only / except

Keep or drop paths of the collection itself. Both understand nested paths and wildcards:

```php
$config->only('app.name', 'db.host');
$payload->except('password', 'users.*.token');
```

---

## Reshaping

### flatten()

```php
flatten(int|float $depth = INF): static
```

```php
Collection::make([[1, [2, 3]], [4]])->flatten();   // [1, 2, 3, 4]
Collection::make([[1, [2, 3]], [4]])->flatten(1);  // [1, [2, 3], 4]
```

### collapse / collapseWithKeys

Merge nested arrays/collections one level deep (non-array items are dropped). `collapse()` uses `array_merge` semantics, `collapseWithKeys()` keeps keys:

```php
Collection::make([[1, 2], [3]])->collapse();                    // [1, 2, 3]
Collection::make([['a' => 1], ['b' => 2]])->collapseWithKeys(); // ['a' => 1, 'b' => 2]
```

### dot / undot

```php
Collection::make(['user' => ['name' => 'Alice'], 'tags' => []])->dot();
// ['user.name' => 'Alice', 'tags' => []]

Collection::make(['user.name' => 'Alice'])->undot();
// ['user' => ['name' => 'Alice']]
```

### flip

Swap keys and values.

### reverse()

```php
reverse(bool $preserveKeys = true): static
```

### transpose()

Rows become columns: `[[1, 2], [3, 4]]` 👉 `[[1, 3], [2, 4]]`.

### toTree()

```php
toTree(
    string|int $idKey = 'id',
    string|int $parentKey = 'parent_id',
    string|int $childrenKey = 'children',
    mixed $rootId = null,
    bool $orphansAsRoots = false,
): static
```

Builds a nested tree from a flat list. Ids are compared loosely (`'1'` matches `1`), cycles are cut, and items whose parent does not exist are dropped unless `$orphansAsRoots` is `true`.

```php
$categories->toTree();
$menu->toTree('id', 'parent', 'items', rootId: 0);
```

---

## Folding

### reduce()

```php
$total = $cart->reduce(fn($carry, $item, $key) => $carry + $item['price'], 0);
```

### reduceSpread()

Carry several values at once; the callback returns an array of new carries:

```php
[$sum, $count] = $c->reduceSpread(fn($sum, $count, $v) => [$sum + $v, $count + 1], 0, 0);
```

### scan()

A running `reduce()` that keeps every intermediate value:

```php
Collection::make([1, 2, 3, 4])->scan(fn($carry, $v) => $carry + $v, 0);  // [1, 3, 6, 10]
```

---

## Strings

### join()

```php
join(string $glue = '', ?string $finalGlue = ''): string
```

```php
Collection::make(['Alice', 'Bob', 'Carol'])->join(', ', ' and ');  // 'Alice, Bob and Carol'
```

### implode()

```php
$c->implode(', ');                            // scalar items
$users->implode('name', ', ');                // a path, then the glue
$users->implode(fn($u) => $u['name'], ', ');
```

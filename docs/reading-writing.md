# Reading, Writing & Converting

[👈 Documentation](README.md)

All path arguments support [dot-notation and wildcards](paths.md).

- Reading: [get](#get) · [collect](#collect) · [all / toArray](#all--toarray) · [keys](#keys) · [values](#values) · [count](#count)
- Checking: [has](#has) · [hasAny](#hasany) · [hasAll / hasKey](#hasall--haskey) · [isEmpty / isNotEmpty](#isempty--isnotempty) · [isList / isAssoc](#islist--isassoc)
- Writing (in place): [set / put](#set--put) · [getOrPut](#getorput) · [forget / remove](#forget--remove) · [pull](#pull) · [push / add](#push--add) · [prepend / unshift](#prepend--unshift) · [pop / shift](#pop--shift) · [splice](#splice) · [transform](#transform) · [clear](#clear)
- Copying: [insert](#insert) · [copy](#copy)
- Converting: [toJson / toPrettyJson](#tojson--toprettyjson) · [jsonSerialize](#jsonserialize) · [string cast](#string-cast) · [toBase](#tobase) · [iteration](#iteration)

---

## Reading

### get()

```php
get(string|int|null $path = null, mixed $default = null): mixed
```

Value at a path, or `$default`. A `Closure` default is only called when the path is missing. Without a path returns all items.

```php
$c->get('user.name');
$c->get('users.*.email');
$c->get('user.avatar', fn() => gravatar($email));
```

### collect()

```php
collect(string|int|null $path = null, mixed $default = null): static
```

The value at a path as a new collection.

```php
$c->collect('orders')->where('status', 'paid')->sum('total');
```

### all / toArray

`all()` returns the underlying array unchanged. `toArray()` also converts nested collections (and any `Arrayable`) to arrays, recursively.

### keys()

```php
keys(string|int|null $path = null): static
```

```php
$c->keys()->all();        // ['user', 'meta']
$c->keys('user')->all();  // ['name', 'age']
```

### values()

A new collection with keys `0..n-1`.

### count()

```php
count(string|int|null $path = null): int
```

Number of items, or of the value at a path (a scalar counts as 1, missing or `null` as 0).

```php
$c->count('users');
count($c);          // Countable
```

---

## Checking

### has()

```php
has(string|int|array $key, string|int ...$more): bool
```

`true` when **all** paths exist. A key holding `null` exists — use `isset($c['path'])` to require a non-null value.

```php
$c->has('user.email');
$c->has('id', 'email');
$c->has(['id', 'email']);
$c->has('users.*.email');   // at least one user has it
```

### hasAny()

`true` when at least one path exists: `hasAny('email', 'phone')`, `hasAny(['email', 'phone'])`.

### hasAll / hasKey

Aliases for `has()`.

### isEmpty / isNotEmpty

### isList / isAssoc

Whether keys are `0..n-1` in order (`array_is_list()`), or not.

---

## Writing (in place)

### set / put

```php
set(string|int $path, mixed $value = null, bool $overwrite = true): static
put(string|int $key, mixed $value): static
```

Creates missing levels. Wildcards write to every match. With `overwrite: false` only missing paths are filled (handy for defaults).

```php
$c->set('user.address.city', 'Berlin');
$c->set('users.*.active', true);
$c->set('settings.theme', 'light', overwrite: false);
```

### getOrPut()

```php
getOrPut(string|int $path, mixed $value): mixed
```

Returns the value if the path exists; otherwise stores `$value` (a `Closure` is called) and returns it.

```php
$token = $c->getOrPut('cache.token', fn() => bin2hex(random_bytes(16)));
```

### forget / remove

```php
forget(mixed $keys, string|int ...$more): static
```

```php
$c->forget('user.password');
$c->forget('users.*.password', 'meta');
$c->forget(['a', 'b']);
```

### pull()

```php
pull(string|int $path, mixed $default = null): mixed
```

Returns the value and removes it.

### push / add

```php
$c->push('a', 'b', 'c');
$c->add('d');
```

### prepend / unshift

```php
$c->prepend('first');
$c->prepend('home', 'key');   // under a key
$c->unshift('x', 'y');
```

### pop / shift

```php
$c->pop();      // last item
$c->pop(3);     // collection of the last three (last first)
$c->shift();    // first item
$c->shift(2);   // collection of the first two
```

### splice()

```php
splice(int $offset, ?int $length = null, mixed $replacement = []): static
```

Removes a portion (optionally replacing it) and returns the removed items.

```php
$removed = $c->splice(1, 2, ['x']);
```

### transform()

Like `map()`, but in place: `$c->transform(fn($v, $k) => $v * 2)`.

### clear()

Removes all items.

---

## Copying

### insert()

```php
insert(int $index, mixed $value): static
```

A new, re-indexed collection with `$value` at `$index` (negative counts from the end).

### copy()

A copy whose nested collections are copied too (other objects are shared).

---

## Converting

### toJson / toPrettyJson

```php
toJson(int $flags = 0, int $depth = 512): string
toPrettyJson(int $flags = 0, int $depth = 512): string
```

`$flags` and `$depth` go to `json_encode()`; `JSON_THROW_ON_ERROR` is always on.

```php
$c->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

### jsonSerialize

`json_encode($collection)` works; nested `JsonSerializable` and `Arrayable` values are converted.

### string cast

`(string) $c` returns JSON. Call `escapeWhenCastingToString()` to HTML-escape it (useful in templates).

### toBase

Returns a plain `Collection` from any subclass.

### iteration

```php
foreach ($c as $key => $value) { ... }
```

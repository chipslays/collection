# Filtering & Finding

[👈 Documentation](README.md)

Filtering methods return a new collection and **preserve keys** — call `values()` to re-index. Conditions use [where-style arguments](getting-started.md#conditions-where-style-arguments); a missing path reads as `null`.

- Filtering: [filter](#filter) · [reject](#reject) · [where](#where) · [whereStrict](#wherestrict) · [whereIn / whereNotIn](#wherein--wherenotin) · [whereNull / whereNotNull](#wherenull--wherenotnull) · [whereBetween / whereNotBetween](#wherebetween--wherenotbetween) · [whereContains / whereStartsWith / whereEndsWith](#wherecontains--wherestartswith--whereendswith) · [whereMatches](#wherematches) · [whereInstanceOf](#whereinstanceof) · [unique](#unique) · [duplicates](#duplicates)
- Taking runs: [takeUntil / takeWhile / skipUntil / skipWhile](#takeuntil--takewhile--skipuntil--skipwhile)
- Finding one item: [first / last](#first--last) · [firstWhere](#firstwhere) · [firstOrFail](#firstorfail) · [sole](#sole) · [value](#value) · [find](#find) · [firstKey / lastKey](#firstkey--lastkey) · [search](#search) · [after / before](#after--before)
- Yes/no questions: [contains](#contains) · [containsStrict](#containsstrict) · [doesntContain](#doesntcontain) · [every](#every) · [hasSole / hasMany](#hassole--hasmany)

---

## Filtering

### filter()

```php
filter(mixed $callback = null, mixed $operator = null, mixed $value = null): static
```

```php
$c->filter();                       // remove falsy items
$c->filter(fn($v, $k) => $v > 10);
$c->filter('active');               // value at path is truthy
$c->filter('role', 'admin');        // strict: role === 'admin'
$c->filter('age', '>=', 18);
```

> The two-argument form of `filter()` and `reject()` compares **strictly**. Use `where()` for Laravel's loose comparison.

### reject()

The inverse of `filter()`; without arguments removes truthy items.

```php
$users->reject('banned');
$users->reject('role', 'guest');
```

### where()

```php
where(mixed $key, mixed $operator = null, mixed $value = null): static
```

Loose `==` by default, like Laravel. Operators: `=` `==` `===` `!=` `<>` `!==` `<` `>` `<=` `>=` `<=>`.

```php
$users->where('active');
$users->where('role', 'admin');
$users->where('profile.age', '>=', 18);
$users->where(fn($u) => $u['score'] > 50);
```

### whereStrict()

`whereStrict('status', 1)` — same as `where('status', '===', 1)`.

### whereIn / whereNotIn

```php
whereIn(mixed $key, iterable $values, bool $strict = false): static
```

Also `whereInStrict()` and `whereNotInStrict()`.

```php
$users->whereIn('role', ['admin', 'editor']);
$users->whereNotIn('status', ['banned']);
```

### whereNull / whereNotNull

```php
$users->whereNull('deleted_at');     // null or missing
$users->whereNotNull('deleted_at');
$c->whereNotNull();                  // items that are not null themselves
```

### whereBetween / whereNotBetween

Inclusive range: `whereBetween('price', [10, 100])`.

### whereContains / whereStartsWith / whereEndsWith

```php
whereContains(mixed $path, string $needle, bool $ignoreCase = false, ?string $encoding = null): static
```

Only string (and `Stringable`) values match. Case-insensitive mode uses `mb_strtolower()` with `$encoding`.

```php
$users->whereContains('email', '@GMAIL', ignoreCase: true);
$files->whereEndsWith('name', '.php');
```

### whereMatches()

```php
whereMatches(mixed $path, string $pattern, int $offset = 0): static
```

An invalid pattern throws `InvalidArgumentException` with the PCRE error.

```php
$codes->whereMatches('code', '/^[A-Z]{2}\d{3}$/');
```

### whereInstanceOf()

```php
whereInstanceOf(string|array $type, mixed $path = null): static
```

```php
$items->whereInstanceOf([Admin::class, Moderator::class]);
$jobs->whereInstanceOf(Mailable::class, 'payload');
```

### unique()

```php
unique(mixed $key = null, bool $strict = false, int $flags = SORT_REGULAR): static
```

The first occurrence wins. Loose comparison by default; `$flags` go to `array_unique()`. `uniqueStrict($key)` is `unique($key, true)`.

```php
$c->unique();
$c->unique(flags: SORT_STRING);
$users->unique('email');
$users->unique(fn($u) => strtolower($u['email']));
```

### duplicates()

```php
duplicates(mixed $callback = null, bool $strict = false): static
```

Values that repeat an earlier value, keyed like the source. With a path or callback the **retrieved values** are returned. `duplicatesStrict()` compares with `===`.

```php
Collection::make(['a', 'b', 'a'])->duplicates()->all();  // [2 => 'a']
$users->duplicates('email')->all();                      // [3 => 'x@y.z']
```

---

## Taking runs

### takeUntil / takeWhile / skipUntil / skipWhile

A value is compared strictly; a callable receives `($item, $key)`. Keys are preserved.

```php
Collection::make([1, 2, 3, 4, 5])->takeUntil(fn($v) => $v > 3);  // [1, 2, 3]
Collection::make([1, 2, 3, 4, 5])->takeWhile(fn($v) => $v < 3);  // [1, 2]
Collection::make([1, 2, 3, 4, 5])->skipUntil(3);                 // [2 => 3, 3 => 4, 4 => 5]
Collection::make([1, 2, 3, 4, 5])->skipWhile(fn($v) => $v < 3);  // [2 => 3, 3 => 4, 4 => 5]
```

---

## Finding one item

### first / last

```php
first(?callable $callback = null, mixed $default = null): mixed
last(?callable $callback = null, mixed $default = null): mixed
```

`$default` may be a `Closure`.

```php
$users->first();
$users->first(fn($u) => $u['active'], 'nobody');
$users->last(fn($u) => $u['active']);
```

### firstWhere()

`firstWhere('role', 'admin')`, `firstWhere('age', '>=', 18)` — first item matching where-style arguments, or `null`.

### firstOrFail()

Like `firstWhere()` (or `first()` without arguments), but throws `ItemNotFoundException`.

```php
$user = $users->firstOrFail('id', $id);
```

### sole()

The only matching item; throws `ItemNotFoundException` for none and `MultipleItemsFoundException` for several.

```php
$user = $users->sole('email', $email);
```

### value()

```php
value(string|int|array $path, mixed $default = null): mixed
```

The value at `$path` from the first item where that path exists.

```php
$settings->value('timezone', 'UTC');
```

### find()

```php
find(string|int|array $path, mixed $value, mixed $default = null): mixed
```

The first item where `$path === $value`.

```php
$users->find('id', 42);
$users->find('profile.email', 'a@x.com');
```

### firstKey / lastKey

Key of the first/last item, or of the first/last item passing a callback.

### search()

```php
search(mixed $value, bool $strict = false): string|int|false
```

Key of the first item equal to `$value` (or passing a callable).

### after / before

```php
after(mixed $value, bool $strict = false): mixed
```

The item right after/before the first item equal to `$value` (or passing a callable), or `null`.

```php
Collection::make([1, 2, 3])->after(2);   // 3
Collection::make([1, 2, 3])->before(2);  // 1
```

---

## Yes/no questions

### contains()

```php
contains(mixed $key, mixed $operator = null, mixed $value = null): bool
```

One argument: a callable, or a value compared loosely. Two or three: a where-style condition. `some()` is an alias.

```php
$c->contains('Alice');
$c->contains(fn($v) => $v > 10);
$users->contains('role', 'admin');
$users->contains('age', '>', 65);
```

### containsStrict()

`containsStrict('1')` or `containsStrict('status', 1)` with `===`.

### doesntContain

`doesntContain(...)` and `doesntContainStrict(...)` — the inverses.

### every()

```php
$users->every('active');
$users->every('age', '>=', 18);
$users->every(fn($u) => $u['verified']);
```

`true` for an empty collection.

### hasSole / hasMany

Whether exactly one / more than one item matches: `hasSole('role', 'owner')`, `hasMany('role', 'admin')`. `containsOneItem()` and `containsManyItems()` are deprecated aliases kept for Laravel compatibility.

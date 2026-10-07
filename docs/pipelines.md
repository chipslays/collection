# Pipelines, Macros & Debugging

[👈 Documentation](README.md)

- Iterating: [each](#each) · [eachSpread](#eachspread)
- Pipelines: [pipe](#pipe) · [pipeInto](#pipeinto) · [pipeThrough](#pipethrough) · [tap](#tap)
- Conditions: [when / unless](#when--unless) · [whenEmpty / whenNotEmpty / unlessEmpty / unlessNotEmpty](#whenempty--whennotempty--unlessempty--unlessnotempty)
- Validation: [ensure](#ensure)
- [Higher-order messages](#higher-order-messages)
- [Macros](#macros)
- [Debugging](#debugging)

---

## Iterating

### each()

Return `false` to stop.

```php
$users->each(function ($user, $key) {
    if ($user['banned']) {
        return false;
    }
    notify($user);
});
```

### eachSpread()

Each item is spread into arguments; the key comes last:

```php
Collection::make([['Alice', 30], ['Bob', 25]])
    ->eachSpread(fn($name, $age) => print "$name is $age\n");
```

---

## Pipelines

### pipe()

Pass the collection to a callback and return its result: `$total = $cart->pipe(fn($c) => $c->sum('price'))`.

### pipeInto()

`new $class($collection)`: `$cart->pipeInto(CartPresenter::class)`.

### pipeThrough()

```php
$result = $users->pipeThrough([
    fn($c) => $c->where('active', true),
    fn($c) => $c->sortBy('name'),
    fn($c) => $c->values(),
]);
```

### tap()

Run a side effect and keep chaining:

```php
$users->where('active', true)
    ->tap(fn($c) => $logger->info("{$c->count()} active users"))
    ->sortBy('name');
```

---

## Conditions

### when / unless

```php
when(mixed $value = null, ?callable $callback = null, ?callable $default = null): static
```

The condition may be any value or a `Closure` receiving the collection. Callbacks receive `($collection, $value)`. A collection returned by the callback continues the chain; anything else keeps the current collection.

```php
$users->when($onlyActive, fn($c) => $c->where('active', true));
$users->when($request['sort'] ?? null, fn($c, $field) => $c->sortBy($field));
$users->when(fn($c) => $c->count() > 100, fn($c) => $c->take(100));
$users->unless($isAdmin, fn($c) => $c->except('*.email'));
```

### whenEmpty / whenNotEmpty / unlessEmpty / unlessNotEmpty

```php
$results->whenEmpty(fn($c) => $c->push('Nothing found'));
```

---

## Validation

### ensure()

```php
ensure(string|array $types): static
```

Throws `UnexpectedValueException` unless every item matches one of the types: `int`, `float`, `string`, `bool`, `array`, `null`, `numeric`, `scalar`, `object`, `callable`, `iterable`, `countable`, `mixed`, or a class/interface name.

```php
$ids->ensure('int');
$amounts->ensure(['int', 'float']);
$users->ensure(User::class);
```

---

## Higher-order messages

A shortcut for the most common callbacks. Property access reads a [path](paths.md) from every item; method calls are forwarded to every item.

```php
$users->map->name;               // $users->map(fn($u) => $u['name'])
$users->sum->{'stats.score'};
$users->filter->active;
$users->sortBy->age;
$users->each->notify($message);  // $user->notify($message) on objects
```

Available for: `average`, `avg`, `contains`, `countBy`, `doesntContain`, `each`, `every`, `filter`, `first`, `flatMap`, `groupBy`, `keyBy`, `last`, `map`, `mapBy`, `max`, `maxBy`, `min`, `minBy`, `partition`, `percentage`, `product`, `reject`, `skipUntil`, `skipWhile`, `some`, `sortBy`, `sortByDesc`, `sum`, `takeUntil`, `takeWhile`, `unique`.

Register your own with `Collection::proxy('methodName')`.

---

## Macros

Add methods at runtime. Closures are bound to the collection, so `$this` works:

```php
Collection::macro('toUpper', function () {
    return $this->map(strtoupper(...));
});

Collection::make(['a', 'b'])->toUpper()->all();  // ['A', 'B']
```

- Subclasses inherit macros and may override them; macros registered on a subclass never leak to the parent.
- Static closures and first-class callables work too: `Collection::macro('upperFirst', ucfirst(...))`.
- `Collection::mixin(new MyMixin())` registers every public/protected method of an object (each must return a `Closure`); pass `replace: false` to keep existing macros.
- `Collection::hasMacro('toUpper')`, `Collection::flushMacros()`.

---

## Debugging

```php
$users->where('active', true)->dump()->sortBy('name');  // dump and keep chaining
$users->dump($extra, $values);                          // dump additional values too
$users->dd();                                            // dump and stop
```

Uses Symfony VarDumper's `dump()`/`dd()` when available, `var_dump()` otherwise.

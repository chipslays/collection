# Reshaping: rename, remap & cast

[👈 Documentation](README.md)

Three tools for turning data you received into data you want. All of them return a **new** collection, understand [paths](paths.md) (wildcards, filters, `**`) and clone objects instead of mutating them.

- [rename](#rename) — rename or move keys
- [remap](#remap) — build a new structure for every item
- [cast](#cast) — convert values to the right types

---

## rename()

```php
rename(string|int|array $from, string|int|null $to = null): static
```

Renames or moves paths in the collection.

```php
$c->rename('user.mail', 'user.email');                 // rename, position kept
$c->rename('user.age', 'meta.age');                    // move to another branch
$c->rename(['id' => 'uuid', 'mail' => 'email']);       // several at once
```

Wildcards, filters and `**` in the source are mapped, in order, onto the wildcards of the target:

```php
$c->rename('users.*.mail', 'users.*.email');           // in every user
$c->rename('users.*.mail', 'emails.*');                // collect into a new list
$c->rename('**.e_mail', '**.email');                   // at any depth
$c->rename('users[?legacy].login', 'users.*.username'); // only for legacy users
```

Missing sources are skipped. A target with a different number of wildcards than the source throws `InvalidArgumentException`.

---

## remap()

```php
remap(array $map): static
```

Builds a new structure for **every item** from `[target path => source]`. Use it to turn an API response into your own format in one place.

```php
$users = collection($apiResponse['data'])->remap([
    'id'           => 'user_id',
    'name'         => 'profile.full_name',
    'address.city' => 'addresses.0.city',        // dots in the target create nesting
    'tags'         => 'roles.*.slug',            // wildcard -> array
    'admin'        => 'roles[?slug=admin]',      // filter
    'label'        => fn($u, $key) => "#{$u['user_id']} {$u['profile']['full_name']}",
]);
```

- A **source** is a path, a list of literal segments (`['meta', 'a.b']`), or a callback `fn($item, $key)`. A missing source gives `null`.
- A **target** is taken literally: the delimiter creates nesting, the wildcard has no special meaning.
- Item keys are preserved; fields come out in the order of the map.

To keep the item and only rename some keys, use [`rename()`](#rename); to keep some fields as they are, use [`select()`](transforming.md#select).

---

## cast()

```php
cast(string|int|array $casts, mixed $caster = null): static
```

Converts values at paths inside **every item**:

```php
$orders->cast([
    'id'             => 'int',
    'total'          => 'float:2',
    'paid'           => 'bool',
    'status'         => OrderStatus::class,
    'created_at'     => 'datetime',
    'items.*.qty'    => 'int',
    'customer.email' => ['trim', 'strtolower'],
]);

$orders->cast('created_at', 'datetime:d.m.Y');   // single path
```

Missing paths are left untouched. Named casters keep `null` as `null`; callables and `Caster` objects receive it and decide themselves.

### Built-in casters

| Caster | Result |
|---|---|
| `int`, `integer` | `(int)`; a `DateTimeInterface` becomes its timestamp |
| `float`, `double`, `real` | `(float)`; `float:2` rounds to 2 decimals |
| `decimal:2` | fixed-point **string**, `'12.50'` |
| `string` | `(string)`; arrays and plain objects become JSON, enums their value, dates `ATOM` (or `string:Y-m-d`) |
| `bool`, `boolean` | strings via `FILTER_VALIDATE_BOOL`: `'false'`, `'no'`, `'off'`, `'0'` → `false` |
| `array` | JSON array/object strings are decoded; collections and iterables converted |
| `json` | decodes a JSON string (throws on invalid JSON) |
| `object` | JSON string or array → `stdClass` |
| `collection` | a `Collection` (JSON strings decoded) |
| `datetime`, `immutable_datetime` | `DateTimeImmutable`; numbers are timestamps; `datetime:d.m.Y` parses with a format |
| `date` | like `datetime`, time set to midnight |
| `timestamp` | Unix timestamp (`int`) |

Formats without `!` or `|` get `!` prepended, so `datetime:Y-m-d` gives midnight, not "now". A value that does not match the format throws `InvalidArgumentException`.

### Classes and enums

```php
$rows->cast([
    'status'  => Status::class,           // backed enum: Status::from($value)
    'status2' => Status::class . ':try',  // Status::tryFrom($value), null when invalid
    'size'    => Size::class,             // pure enum: case by name, Size::M
    'at'      => DateTime::class,         // any DateTimeInterface class (Carbon too)
    'price'   => Money::class,            // any other class: new Money($value)
]);
```

A value that is already an instance of the class is kept as is.

### Callbacks

Any callable receives `($value, $item, $key)` — the value, the whole item (before casting) and the item key:

```php
$orders->cast([
    'total' => fn($value, $order) => new Money($value, $order['currency']),
    'slug'  => fn($value, $item, $key) => $value ?? "order-{$key}",
    'name'  => trim(...),                 // built-ins receive only the value
    'title' => 'ucfirst',                 // any global function by name
]);
```

### Caster objects

For reusable, configurable casters implement `Collection\Contracts\Caster`:

```php
use Collection\Contracts\Caster;

final class MoneyCast implements Caster
{
    public function __construct(private string $currency = 'EUR') {}

    public function cast(mixed $value, mixed $item, string|int $key): mixed
    {
        return new Money((int) round($value * 100), $this->currency);
    }
}

$orders->cast(['total' => new MoneyCast('USD')]);
```

### Named casters

Register a caster once and use it by name everywhere. Arguments after `:` are split by `,` and passed after `$key`:

```php
Collection::castUsing('money', fn($value, $item, $key, $currency = 'EUR') => new Money($value, $currency));
Collection::castUsing('usd', new MoneyCast('USD'));

$orders->cast(['total' => 'money:USD', 'tax' => 'money', 'fee' => 'usd']);
```

### Chains

A list of casters is applied left to right:

```php
$users->cast(['email' => ['trim', 'strtolower'], 'age' => ['trim', 'int']]);
```

### Where casters come from

For a string caster the lookup order is: a name registered with `castUsing()` → a built-in caster (lowercase names) → a class or enum → a global function. Anything else throws `InvalidArgumentException`.

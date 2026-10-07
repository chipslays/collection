# Paths: Dot-Notation & Wildcards

[👈 Documentation](README.md)

- [Basics](#basics)
- [Wildcards](#wildcards)
- [Named wildcards `*[field]`](#named-wildcards-field)
- [Filters `[?condition]`](#filters-condition)
- [Recursive descent `**`](#recursive-descent-)
- [Objects, ArrayAccess and nested collections](#objects-arrayaccess-and-nested-collections)
- [Keys containing dots](#keys-containing-dots)
- [Custom wildcard and delimiter](#custom-wildcard-and-delimiter)
- [ArrayAccess](#arrayaccess)
- [Where paths work](#where-paths-work)

---

## Basics

A path is a list of keys joined by `.`:

```php
$c = Collection::make([
    'user' => ['name' => 'Alice', 'address' => ['city' => 'Berlin']],
]);

$c->get('user.address.city');          // 'Berlin'
$c->get('user.phone', 'n/a');          // 'n/a'
$c->set('user.address.zip', '10115');  // creates missing levels
$c->has('user.address.zip');           // true
$c->forget('user.address');
```

Integer keys work as segments (`users.0.name`) and as whole paths (`get(0)`).

---

## Wildcards

`*` matches every child. The result keeps the original keys, so you always know which value came from which parent:

```php
$c = Collection::make([
    'users' => [
        ['id' => 7, 'name' => 'Alice', 'emails' => [['address' => 'a@x.com']]],
        ['id' => 9, 'name' => 'Bob',   'emails' => [['address' => 'b@x.com'], ['address' => 'b2@x.com']]],
    ],
]);

$c->get('users.*.name');              // [0 => 'Alice', 1 => 'Bob']
$c->get('users.*.emails.*.address');  // [0 => [0 => 'a@x.com'], 1 => [0 => 'b@x.com', 1 => 'b2@x.com']]
```

Wildcards also write and delete:

```php
$c->set('users.*.active', true);    // on every user
$c->forget('users.*.emails');       // from every user
$c->has('users.*.emails');          // true if at least one user has it
```

A trailing wildcard in `forget()` empties the level: `forget('users.*')` leaves `users => []`.

---

## Named wildcards `*[field]`

`*[field]` keys the result by a field of each child instead of the original key. The field may itself be a path.

```php
$c->get('users.*[id].name');              // [7 => 'Alice', 9 => 'Bob']
$c->get('users.*[id].emails.*.address');  // [7 => [...], 9 => [...]]
$c->get('users.*[profile.uuid].name');
```

If the field is missing from a child, that child keeps its original key. For `set`, `has` and `forget` a named wildcard behaves like `*`.

---

## Filters `[?condition]`

`key[?condition]` selects only the children of `key` that pass the condition. It works like `key.*`, so keys are preserved:

```php
$c->get('users[?age>=18].name');               // [0 => 'Alice', 2 => 'Carol']
$c->get('users[?active].email');               // truthy
$c->get('users[?!banned].email');              // falsy or missing
$c->get('users[?role="admin" || age<18].name');
$c->get('users[?profile.verified && plan!=free].id');
$c->get('[?price>100]');                       // filter the top level itself
```

- **Conditions:** `path` (truthy), `!path` (falsy), `path <op> value` with `=` `==` `===` `!=` `<>` `!==` `<` `>` `<=` `>=`. `=`/`==` compare loosely, like `where()`. The path inside a condition may itself be nested.
- **Values:** numbers, `true`, `false`, `null`, `'single'`/`"double"` quoted strings, or bare words (`role=admin`).
- **Combining:** `&&` and `||` (`&&` binds tighter); several brackets are ANDed: `users[?active][?age>18]`.
- **Named wildcard + filter:** `users.*[id][?active].name` → `[7 => 'Alice', ...]`.

Filters work everywhere paths do — including writes:

```php
$c->set('users[?age<18].minor', true);       // only for minors
$c->forget('users[?banned]');                // remove banned users
$c->mapPath('items[?qty>0].price', fn($p) => $p * 0.9);
$orders->pluck('items[?qty>0].sku');         // per order
$orders->where('items[?status=failed]');     // orders with at least one failed item
```

A literal `[` in a key is fine (`a.b[1]`); escape it as `\[` if the key itself starts with `[?`.

---

## Recursive descent `**`

`**` matches the current node and every node below it, at any depth. The result is a flat array keyed by the full (escaped) path of each match, so you always know where a value came from — and can read it back with `get()`:

```php
$response->get('**.email');
// [
//     'users.0.email'         => 'a@x',
//     'users.0.profile.email' => 'alice@home',
//     'owner.email'           => 'owner@x',
// ]

$response->get('**[?type=error].message');  // every error message, wherever it is
$response->has('**.password');              // is there a password anywhere?
```

Writes apply to every existing match (`set` never creates new keys under `**`):

```php
$payload->set('**.password', '***');         // mask before logging
$payload->forget('**.internal');             // strip at any depth
$payload->mapPath('**.price', fn($p) => round($p, 2));
$payload->rename('**.e_mail', '**.email');
$payload->only('**.id');                     // keep only ids, structure preserved
```

Object cycles are detected and visited once. With a custom wildcard the token is doubled: `##` for `#`.

---

## Objects, ArrayAccess and nested collections

Paths go through public object properties, `ArrayAccess` objects (`ArrayObject`, Eloquent models…) and nested collections:

```php
$c = Collection::make([
    'order'    => $orderDto,                      // public properties
    'settings' => new ArrayObject(['theme' => 'dark']),
    'groups'   => $users->groupBy('role'),        // collection of collections
]);

$c->get('order.customer.email');
$c->get('settings.theme');
$c->get('groups.admin.0.name');
```

Wildcards iterate arrays, collections, `Traversable` objects and public object properties.

`set()` and `forget()` write into objects in place. Methods that return a **new** collection (`mapPath`, `except`, `toTree`) clone objects on the path instead of mutating the originals.

---

## Keys containing dots

An exact top-level key always wins, so data from `dot()` or keys like domain names just work:

```php
Collection::make(['example.com' => 1])->get('example.com'); // 1
```

Deeper in the structure, escape the delimiter (or the wildcard) with a backslash:

```php
$c->get('hosts.example\.com.ip');
$c->set('hosts.example\.com.ip', '1.1.1.1');
$c->get('patterns.\*');             // the literal key '*'
```

Only `\.`, `\*`, `\[` and `\\` are escapes; any other backslash is kept, so class names like `App\Models\User` work as keys.

Pluck-style methods (`pluck`, `sortBy`, `groupBy`, `sum`…) also accept an array of literal segments: `pluck(['hosts', 'example.com'])`.

---

## Custom wildcard and delimiter

```php
$c = new Collection($data, wildcard: '#', delimiter: '/');
$c->get('users/#/name');

$c = Collection::make($data, '*', '->');
$c->get('user->address->city');
```

Both are kept by every collection derived from this one. They must be non-empty, must not overlap and must not contain a backslash.

---

## ArrayAccess

```php
$c['user.name'];            // get('user.name')
$c['user.name'] = 'Alice';  // set('user.name', 'Alice')
$c[] = 'pushed';            // push('pushed')
isset($c['user.name']);     // path exists AND value is not null
unset($c['user.name']);     // forget('user.name')
```

`$c->has('path')` checks only that the key exists (a `null` value counts as present); `isset()` also requires a non-null value.

---

## Where paths work

Anywhere a method takes a key, field or "by" argument:

- reading/writing: `get`, `set`, `put`, `has`, `hasAny`, `forget`, `pull`, `getOrPut`, `count`, `keys`, `collect`, `only`, `except`
- per item: `where*`, `filter`, `reject`, `contains`, `every`, `firstWhere`, `sole`, `partition`, `pluck`, `select`, `value`, `find`, `mapPath`, `evolve`
- reshaping: [`rename`, `remap`, `cast`](reshaping.md)
- grouping/sorting: `groupBy`, `keyBy`, `countBy`, `sortBy`, `unique`, `duplicates`
- aggregates: `sum`, `avg`, `min`, `max`, `minBy`, `maxBy`, `median`, `mode`, `standardDeviation`, `percentage`
- higher-order messages: `$users->sum->{'stats.score'}`

---

## Performance

Plain paths over arrays (`user.age`, `users.*.name`) take a fast route that reads values with native array access in a single loop. On 100 000 items `where('user.age', '>=', 18)`, `pluck('user.name')`, `groupBy()` and `sum()` run within about 1.5–2.5× of a hand-written `foreach`, and `sortBy('user.age')` is several times faster than a hand-written `uasort()`. Objects, filters and `**` use the full engine — same results, a little slower.

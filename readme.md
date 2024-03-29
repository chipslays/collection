# 📂 Collection

![GitHub Workflow Status](https://img.shields.io/github/workflow/status/chipslays/collection/Tests)
![Packagist Version](https://img.shields.io/packagist/v/chipslays/collection)

Simple library for manipulating array or object as collection.

> ℹ Supported dot-notation and asterisks rules.

The purpose of this library is to provide a generic class for interacting with arrays of data. Convenient support for dot notation and asterisk keys.

## Installation

```bash
$ composer require chipslays/collection
```

## Documentation

> Collection can created by helper function `collection()`.

#### `get(string $key [, $default = null, string $separator = '.'])`

Get item from collection by using dot notation.

```php
use Chipslays\Collection\Collection;

$collection = new Collection([
    'user' => [
        'name' => 'chipslays',
    ],
]);

$name = $collection->get('user.name'); // chipslays
$email = $collection->get('user.email', 'default@email.com'); // default@email.com

$array = $collection->user; // array('name' => 'chipslays')
$name = $collection->user['name']; // chipslays
```

```php
$collection = collection([
    'foo' => [
        'bar' => ['baz' => 1],
        'bam' => ['baz' => 2],
        'boo' => ['baz' => 3],
    ],
]);

$collection->get('foo.*.baz');
// Array
// (
//     [0] => 1
//     [1] => 2
//     [2] => 3
// )

$collection->get('foo.*');
// Array
// (
//     [0] => Array
//         (
//             [baz] => 1
//         )
//     [1] => Array
//         (
//             [baz] => 2
//         )
//     [2] => Array
//         (
//             [baz] => 3
//         )
// )
```


```php
$collection = collection([
    'foo' => [
        'bar' => ['baz' => 1],
    ],
]);

$collection->get('foo.*.baz');
// 1

$collection->get('foo.*');
// Array
// (
//     [baz] => 1
// )

```

#### `set(string $key, $value = null [, string $separator = '.']): Collection`

Set/overwrite item in collection using by dot notation key.

```php
use Chipslays\Collection\Collection;

$collection = new Collection([
    'user' => [
        'name' => 'chipslays',
    ],
]);

$collection->set('user.name', 'john doe');
$collection->set('user.email', 'john.doe@email.com');

$name = $collection->get('user.name'); // john doe
$email = $collection->get('user.email'); // john.doe@email.com
```

#### `has(string $key [, string $separator = '.']): bool`

Check exists item in collection using by dot notation key.

```php
use Chipslays\Collection\Collection;

$collection = new Collection([
    'user' => [
        'name' => 'chipslays',
    ],
]);

$hasName = $collection->has('user.name'); // true
$hasEmail = $collection->has('user.email'); // false
```
#### `first(): mixed`

Returns first item from collection.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(['foo', 'bar', 'baz']);

echo $collection->first(); // foo
```

#### `last(): mixed`

Returns last item from collection.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(['foo', 'bar', 'baz']);

echo $collection->last(); // baz
```

#### `shift(): mixed`

Getting first item and remove her from collection.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(['foo', 'bar', 'baz']);

echo $collection->shift(); // foo
echo $collection->count(); // 2
```

#### `values(): Collection`

Returns values without keys as collection.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(['color' => 'green', 'name' => 'apple']);

print_r($collection->values()); // collection(green, apple)
```

#### `keys(): Collection`

Returns keys without values as collection.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(['color' => 'green', 'name' => 'apple']);

print_r($collection->keys()); // collection(color, name)
```

#### `only(): Collection`

Returns only selected keys.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(['color' => 'green', 'name' => 'apple']);

print_r($collection->only(['color'])); // collection(color => green)
```

#### `push(): Collection`
#### `replace(): Collection`
#### `replaceRecursive(): Collection`
#### `merge(): Collection`
#### `mergeRecursive(): Collection`
#### `trim(): Collection`
#### `remove(...string $keys): Collection`
#### `limit(): Collection`
#### `chunk(int $size): Collection`
#### `each(callable $callback($item)): Collection`
#### `map(callable $callback($item)): Collection`
#### `mapWithKeys(callable $callback($item)): Collection`
#### `filter(callable $callback($item, $key) = null): Collection`
#### `where($key, $operator = null, $value = null): Collection`
#### `all(): array`
#### `collect(string $key, string $separator = '.')`
#### `reverse(bool $preserveKeys)`

#### `count(): int`

Get count of items in collection.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(range(1, 10));

echo $collection->count(); // 10
echo count($collection); // 10
```

#### `clear(): Collection`

Clear all items in collection.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(range(1, 10));

$collection->clear();
```

#### `toArray(): array`

Get collection items as array.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(range(1, 10));

$collection->toArray();
```

#### `toObject(): object`

Get collection items as object (stdClass).

```php
use Chipslays\Collection\Collection;

$collection = new Collection(range(1, 10));

$collection->toObject();
```

#### `__toString(): string`

Get items as printable string.

```php
use Chipslays\Collection\Collection;

$collection = new Collection(['one', 'two']);

echo (string) $collection;

/** output string */
Array
(
    [0] => one
    [1] => two
)
```

## 👀 See also

* [`chipslays/array`](https://github.com/chipslays/array) - Simple library for array manipulate.

## License
MIT

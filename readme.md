# 📂 Collection

Library for manipulating array or object as collection.

## Installation

```bash
$ composer require chipslays/collection
```

## Documentation

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


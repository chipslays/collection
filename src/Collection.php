<?php

declare(strict_types=1);

namespace Collection;

use ArrayAccess;
use ArrayIterator;
use BackedEnum;
use Closure;
use Collection\Concerns\AggregatesValues;
use Collection\Concerns\Conditionable;
use Collection\Concerns\FiltersItems;
use Collection\Concerns\Macroable;
use Collection\Concerns\ReshapesItems;
use Collection\Concerns\ResolvesPaths;
use Collection\Concerns\SortsItems;
use Collection\Contracts\Arrayable;
use Countable;
use Exception;
use InvalidArgumentException;
use IteratorAggregate;
use JsonException;
use JsonSerializable;
use Override;
use Random\Randomizer;
use Stringable;
use Traversable;
use UnexpectedValueException;
use UnitEnum;

/**
 * A Laravel-compatible collection with dot-notation and wildcard path support.
 *
 *   get('user.name')
 *   get('users.*.name')                 // keys of the parents are preserved
 *   get('users.*[id].name')             // ... or replaced by each child's 'id'
 *   get('hosts.example\.com')           // escaped delimiter
 *
 * Methods that transform return a new collection and preserve keys, exactly
 * like Laravel. Methods that mutate (set/put/push/pop/shift/prepend/forget/
 * pull/splice/transform/clear) change the collection in place.
 *
 * Subclasses must keep the constructor signature: methods create new
 * instances with new static($items, $wildcard, $delimiter).
 *
 * @phpstan-consistent-constructor
 *
 * @template-implements ArrayAccess<array-key, mixed>
 * @template-implements IteratorAggregate<array-key, mixed>
 */
class Collection implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable, Stringable
{
    use AggregatesValues;
    use Conditionable;
    use FiltersItems;
    use Macroable;
    use ReshapesItems;
    use ResolvesPaths;
    use SortsItems;

    /**
     * @var array<array-key, mixed>
     */
    protected array $items = [];

    protected bool $escapeWhenCastingToString = false;

    /**
     * Methods available as higher-order messages: $users->sum->price.
     *
     * @var list<string>
     */
    protected static array $proxies = [
        'average', 'avg', 'contains', 'countBy', 'doesntContain', 'each', 'every', 'filter',
        'first', 'flatMap', 'groupBy', 'keyBy', 'last', 'map', 'mapBy', 'max', 'maxBy', 'min',
        'minBy', 'partition', 'percentage', 'product', 'reject', 'skipUntil', 'skipWhile',
        'some', 'sortBy', 'sortByDesc', 'sum', 'takeUntil', 'takeWhile', 'unique',
    ];

    /**
     * Create a collection from any arrayable value, optionally with a custom wildcard and path delimiter.
     *
     * @param  mixed  $items  Array, collection, Arrayable, Traversable, JsonSerializable,
     *                        object with toArray(), or a single value (wrapped).
     *
     * @throws InvalidArgumentException on an unusable wildcard/delimiter pair
     *
     * @example new Collection([1, 2, 3])
     * @example new Collection(['a' => ['b' => 1]], wildcard: '%', delimiter: '/')
     */
    public function __construct(
        mixed $items = [],
        public readonly string $wildcard = '*',
        public readonly string $delimiter = '.',
    ) {
        if ($delimiter === '' || $wildcard === '') {
            throw new InvalidArgumentException('The wildcard and the delimiter must not be empty.');
        }

        if (str_contains($wildcard, $delimiter) || str_contains($delimiter, $wildcard)) {
            throw new InvalidArgumentException('The wildcard and the delimiter must not overlap.');
        }

        if (str_contains($wildcard.$delimiter, '\\')) {
            throw new InvalidArgumentException('The backslash is reserved for escaping in paths.');
        }

        $this->items = $this->getArrayableItems($items);
    }

    // -----------------------------------------------------------------
    // Creation
    // -----------------------------------------------------------------

    /**
     * Create a collection from any arrayable value.
     *
     * A string holding a JSON array or object is decoded; any other string
     * is wrapped as a single item (Laravel behaviour).
     *
     * @throws JsonException
     *
     * @example make([1, 2, 3])
     * @example make('{"user": {"name": "Alice"}}')
     * @example make($laravelCollection)
     */
    public static function make(mixed $items = [], string $wildcard = '*', string $delimiter = '.'): static
    {
        if (is_string($items) && self::looksLikeJson($items)) {
            return static::fromJson($items, wildcard: $wildcard, delimiter: $delimiter);
        }

        return new static($items, $wildcard, $delimiter);
    }

    /**
     * Create a collection from a JSON array or object.
     *
     * $depth and $flags are passed to json_decode(); JSON_THROW_ON_ERROR is always on.
     *
     * @throws JsonException on invalid JSON
     * @throws InvalidArgumentException when the JSON is not an array or object
     *
     * @example fromJson('{"user": {"name": "Alice"}}')
     * @example fromJson('[1, 2, 3]')  // [1, 2, 3]
     */
    public static function fromJson(
        string $json,
        int $depth = 512,
        int $flags = 0,
        string $wildcard = '*',
        string $delimiter = '.',
    ): static {
        $decoded = json_decode($json, true, $depth, $flags | JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('JSON must decode to an array or object, got '.get_debug_type($decoded).'.');
        }

        return new static($decoded, $wildcard, $delimiter);
    }

    /**
     * Wrap a value into a collection: a collection is copied, an array is used
     * as-is, null becomes empty, anything else becomes a single item.
     *
     * @example wrap([1, 2])  // [1, 2]
     * @example wrap('a')     // ['a']
     * @example wrap(null)    // []
     */
    public static function wrap(mixed $value): static
    {
        if ($value instanceof self) {
            return new static($value->items, $value->wildcard, $value->delimiter);
        }

        return new static(match (true) {
            $value === null => [],
            is_array($value) => $value,
            default => [$value],
        });
    }

    /**
     * Get the underlying array from a collection; any other value is returned unchanged.
     *
     * @example unwrap(collect([1, 2]))  // [1, 2]
     * @example unwrap('a')               // 'a'
     */
    public static function unwrap(mixed $value): mixed
    {
        return $value instanceof self ? $value->all() : $value;
    }

    /**
     * Create an empty collection.
     *
     * @example empty()->isEmpty()  // true
     */
    public static function empty(): static
    {
        return new static;
    }

    /**
     * Create a collection by invoking $callback $number times (with 1..$number).
     * Without a callback the numbers themselves are the items.
     *
     * @example times(3)                   // [1, 2, 3]
     * @example times(3, fn($i) => $i * 2) // [2, 4, 6]
     */
    public static function times(int $number, ?callable $callback = null): static
    {
        if ($number < 1) {
            return new static;
        }

        $range = range(1, $number);

        return new static($callback === null ? $range : array_map(static::adaptCallback($callback), $range));
    }

    /**
     * Create a collection of numbers (or letters) like range().
     *
     * @example range(1, 5)
     * @example range(0, 1, 0.25)
     * @example range('a', 'e')
     */
    public static function range(int|float|string $from, int|float|string $to, int|float $step = 1): static
    {
        return new static(range($from, $to, $step));
    }

    /**
     * Wrap the value at $path (or all items) into a new collection.
     *
     * @example collect('users.*.emails')->flatten()
     */
    public function collect(string|int|null $path = null, mixed $default = null): static
    {
        return $this->newInstance($this->getArrayableItems($this->get($path, $default)));
    }

    /**
     * A plain Collection instance (useful from subclasses).
     *
     * @example $custom->toBase()  // a plain Collection with the same items
     */
    public function toBase(): self
    {
        return new self($this->items, $this->wildcard, $this->delimiter);
    }

    // -----------------------------------------------------------------
    // Path access
    // -----------------------------------------------------------------

    /**
     * Value at a dot-notation path, or $default (a Closure default is called).
     *
     * An exact top-level key wins over path parsing, so get('a.b') returns
     * the item stored under the literal key 'a.b' when it exists.
     *
     * @example get('user.name')
     * @example get('users.*.name')
     * @example get('users.*[id].email')
     * @example get('missing', fn() => compute())
     */
    public function get(string|int|null $path = null, mixed $default = null): mixed
    {
        if ($path === null) {
            return $this->items;
        }

        if (array_key_exists($path, $this->items)) {
            return $this->items[$path];
        }

        $value = $this->dataGet($this->items, $this->toSegments($path));

        return $value === static::missing() ? static::resolveValue($default) : $value;
    }

    /**
     * Value at $path; when missing, store $value (a Closure is called) and return it.
     *
     * @example getOrPut('cache.token', fn() => generateToken())
     */
    public function getOrPut(string|int $path, mixed $value): mixed
    {
        if ($this->pathExists($path)) {
            return $this->get($path);
        }

        $value = static::resolveValue($value);
        $this->set($path, $value);

        return $value;
    }

    /**
     * Set a value at a path (mutates). Wildcards set every match.
     *
     * $overwrite = false only fills paths that do not exist yet.
     *
     * @example set('user.name', 'Alice')
     * @example set('users.*.active', true)
     * @example set('settings.theme', 'dark', overwrite: false)
     */
    public function set(string|int $path, mixed $value = null, bool $overwrite = true): static
    {
        $this->dataSet($this->items, $this->toSegments($path), $value, $overwrite);

        return $this;
    }

    /**
     * Alias for set().
     *
     * @example put('name', 'Alice')
     * @example put('user.name', 'Alice')
     */
    public function put(string|int $key, mixed $value): static
    {
        return $this->set($key, $value);
    }

    /**
     * Whether ALL given paths exist (a null value counts as existing).
     *
     * Use isset($collection['path']) to also require a non-null value.
     *
     * @example has('user.name')
     * @example has('id', 'email')
     * @example has(['id', 'email'])
     * @example has('users.*.email')   // at least one user has the key
     */
    public function has(string|int|array $key, string|int ...$more): bool
    {
        foreach (is_array($key) ? $key : [$key, ...$more] as $path) {
            if (!$this->pathExists($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for has().
     *
     * @example hasKey('user.name')
     */
    public function hasKey(string|int|array $key, string|int ...$more): bool
    {
        return $this->has($key, ...$more);
    }

    /**
     * Whether at least one of the given paths exists.
     *
     * @example hasAny('email', 'phone')
     * @example hasAny(['email', 'phone'])
     */
    public function hasAny(string|int|array $key, string|int ...$more): bool
    {
        foreach (is_array($key) ? $key : [$key, ...$more] as $path) {
            if ($this->pathExists($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alias for has() with several paths.
     *
     * @example hasAll('id', 'email')
     */
    public function hasAll(string|int ...$paths): bool
    {
        return $this->has($paths);
    }

    /**
     * Remove one or more paths (mutates). Wildcards remove every match.
     *
     * @example forget('user.token')
     * @example forget('users.*.password', 'meta')
     * @example forget(['a', 'b'])
     */
    public function forget(mixed $keys, string|int ...$more): static
    {
        $keys = is_string($keys) || is_int($keys) ? [$keys, ...$more] : $this->getArrayableItems($keys);

        foreach ($keys as $path) {
            if (array_key_exists($path, $this->items)) {
                unset($this->items[$path]);

                continue;
            }

            $this->dataForget($this->items, $this->toSegments($path));
        }

        return $this;
    }

    /**
     * Alias for forget().
     *
     * @example remove('user.token')
     */
    public function remove(mixed $keys, string|int ...$more): static
    {
        return $this->forget($keys, ...$more);
    }

    /**
     * Get the value at $path and remove it (mutates).
     *
     * @example pull('token')
     * @example pull('missing', 'n/a')  // 'n/a'
     */
    public function pull(string|int $path, mixed $default = null): mixed
    {
        $value = $this->get($path, $default);
        $this->forget($path);

        return $value;
    }

    /**
     * Count items; with a path, count the value there (scalar = 1, missing/null = 0).
     *
     * @example count()
     * @example count('users')
     * @example count('users.*.emails')
     */
    #[Override]
    public function count(string|int|null $path = null): int
    {
        if ($path === null) {
            return count($this->items);
        }

        $value = $this->get($path);

        return match (true) {
            $value === null => 0,
            is_countable($value) => count($value),
            default => 1,
        };
    }

    /**
     * Keys of the collection, or of the array at $path.
     *
     * @example make(['a' => 1, 'b' => 2])->keys()  // ['a', 'b']
     * @example keys('user')  // keys of the array at 'user'
     */
    public function keys(string|int|null $path = null): static
    {
        $value = $path === null ? $this->items : $this->get($path);

        return $this->newInstance(is_array($value) || $value instanceof self ? array_keys($this->getArrayableItems($value)) : []);
    }

    /**
     * The underlying array, unchanged.
     *
     * @example make([1, 2])->all()  // [1, 2]
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Re-index the collection with consecutive integer keys.
     *
     * @example make([3 => 'a', 5 => 'b'])->values()  // [0 => 'a', 1 => 'b']
     */
    public function values(): static
    {
        return $this->newInstance(array_values($this->items));
    }

    // -----------------------------------------------------------------
    // State
    // -----------------------------------------------------------------

    /**
     * Whether the collection has no items.
     *
     * @example make([])->isEmpty()   // true
     * @example make([1])->isEmpty()  // false
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Whether the collection has at least one item.
     *
     * @example make([1])->isNotEmpty()  // true
     */
    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    /**
     * Whether keys are 0..n-1 in order (array_is_list()).
     *
     * @example make([1, 2])->isList()      // true
     * @example make(['a' => 1])->isList()  // false
     */
    public function isList(): bool
    {
        return array_is_list($this->items);
    }

    /**
     * Whether the items are not a list.
     *
     * @example make(['a' => 1])->isAssoc()  // true
     * @example make([1, 2])->isAssoc()      // false
     */
    public function isAssoc(): bool
    {
        return !array_is_list($this->items);
    }

    // -----------------------------------------------------------------
    // Mutation (in place)
    // -----------------------------------------------------------------

    /**
     * Remove all items.
     *
     * @example clear()->isEmpty()  // true
     */
    public function clear(): static
    {
        $this->items = [];

        return $this;
    }

    /**
     * Append one or more values.
     *
     * @example push(4)
     * @example push(4, 5, 6)
     */
    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    /**
     * Append a single value.
     *
     * @example add(4)
     */
    public function add(mixed $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Put a value at the beginning, optionally under $key.
     *
     * @example prepend(0)
     * @example prepend('zero', 'key')
     */
    public function prepend(mixed $value, mixed $key = null): static
    {
        if (func_num_args() > 1 && $key !== null) {
            $this->items = [$key => $value] + $this->items;
        } else {
            array_unshift($this->items, $value);
        }

        return $this;
    }

    /**
     * Put one or more values at the beginning.
     *
     * @example unshift(1, 2)
     */
    public function unshift(mixed ...$values): static
    {
        array_unshift($this->items, ...array_values($values));

        return $this;
    }

    /**
     * Remove and return the last item; with $count > 1 a collection of removed items.
     *
     * @example make([1, 2, 3])->pop()   // 3
     * @example make([1, 2, 3])->pop(2)  // collection [3, 2]
     */
    public function pop(int $count = 1): mixed
    {
        if ($count < 1) {
            return $this->newInstance();
        }

        if ($count === 1) {
            return array_pop($this->items);
        }

        $results = [];

        for ($i = min($count, count($this->items)); $i > 0; $i--) {
            $results[] = array_pop($this->items);
        }

        return $this->newInstance($results);
    }

    /**
     * Remove and return the first item; with $count > 1 a collection of removed items.
     *
     * @throws InvalidArgumentException when $count is negative
     *
     * @example make([1, 2, 3])->shift()   // 1
     * @example make([1, 2, 3])->shift(2)  // collection [1, 2]
     */
    public function shift(int $count = 1): mixed
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Number of shifted items may not be less than zero.');
        }

        if ($this->items === []) {
            return null;
        }

        if ($count === 0) {
            return $this->newInstance();
        }

        if ($count === 1) {
            return array_shift($this->items);
        }

        $results = [];

        for ($i = min($count, count($this->items)); $i > 0; $i--) {
            $results[] = array_shift($this->items);
        }

        return $this->newInstance($results);
    }

    /**
     * Remove a portion of the items and return it; $replacement is inserted instead.
     *
     * @example splice(2)
     * @example splice(1, 2, ['x'])
     */
    public function splice(int $offset, ?int $length = null, mixed $replacement = []): static
    {
        if (func_num_args() === 1) {
            return $this->newInstance(array_splice($this->items, $offset));
        }

        return $this->newInstance(
            array_splice($this->items, $offset, $length, $this->getArrayableItems($replacement)),
        );
    }

    /**
     * Replace every item with $callback($item, $key) in place.
     *
     * @example make([1, 2, 3])->transform(fn($item, $key) => $item * 2)  // [2, 4, 6]
     */
    public function transform(callable $callback): static
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    // -----------------------------------------------------------------
    // Combining (new collection)
    // -----------------------------------------------------------------

    /**
     * array_merge() with the given items: string keys overwrite, integer keys append.
     *
     * @example make(['a' => 1])->merge(['b' => 2])  // ['a' => 1, 'b' => 2]
     * @example make([1, 2])->merge([3])  // [1, 2, 3]
     */
    public function merge(mixed $items): static
    {
        return $this->newInstance(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * array_merge_recursive() with the given items.
     *
     * @example make(['a' => [1]])->mergeRecursive(['a' => [2]])  // ['a' => [1, 2]]
     */
    public function mergeRecursive(mixed $items): static
    {
        return $this->newInstance(array_merge_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * array_replace() with the given items (integer keys are significant).
     *
     * @example make([1, 2, 3])->replace([1 => 'b'])  // [1, 'b', 3]
     */
    public function replace(mixed $items): static
    {
        return $this->newInstance(array_replace($this->items, $this->getArrayableItems($items)));
    }

    /**
     * array_replace_recursive() with the given items.
     *
     * @example make(['a' => ['x' => 1, 'y' => 2]])->replaceRecursive(['a' => ['y' => 3]])  // ['a' => ['x' => 1, 'y' => 3]]
     */
    public function replaceRecursive(mixed $items): static
    {
        return $this->newInstance(array_replace_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Append every value of $source (its keys are ignored).
     *
     * @example make([1, 2])->concat([3, 4])  // [1, 2, 3, 4]
     */
    public function concat(iterable $source): static
    {
        $items = $this->items;

        foreach ($source as $item) {
            $items[] = $item;
        }

        return $this->newInstance($items);
    }

    /**
     * Add keys that are missing here ($this + $items).
     *
     * @example make(['a' => 1])->union(['a' => 2, 'b' => 3])  // ['a' => 1, 'b' => 3]
     */
    public function union(mixed $items): static
    {
        return $this->newInstance($this->items + $this->getArrayableItems($items));
    }

    /**
     * Use the values of this collection as keys for $values.
     *
     * @example make(['a', 'b'])->combine([1, 2])  // ['a' => 1, 'b' => 2]
     */
    public function combine(mixed $values): static
    {
        return $this->newInstance(array_combine($this->items, $this->getArrayableItems($values)));
    }

    /**
     * Cartesian product with the given lists.
     *
     * @example make([1, 2])->crossJoin(['a', 'b'])  // [[1,'a'], [1,'b'], [2,'a'], [2,'b']]
     */
    public function crossJoin(mixed ...$lists): static
    {
        $results = [[]];

        foreach ([$this->items, ...array_map($this->getArrayableItems(...), $lists)] as $index => $list) {
            $append = [];

            foreach ($results as $product) {
                foreach ($list as $item) {
                    $product[$index] = $item;
                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return $this->newInstance($results);
    }

    /**
     * Zip with other lists: a collection of collections holding the i-th values.
     * Shorter lists are padded with null.
     *
     * @example make([1, 2])->zip(['a', 'b'])  // [[1, 'a'], [2, 'b']]
     */
    public function zip(mixed ...$items): static
    {
        $lists = [array_values($this->items), ...array_map(fn ($list) => array_values($this->getArrayableItems($list)), $items)];
        $length = max(array_map('count', $lists));
        $result = [];

        for ($i = 0; $i < $length; $i++) {
            $result[] = $this->newInstance(array_map(static fn ($list) => $list[$i] ?? null, $lists));
        }

        return $this->newInstance($result);
    }

    /**
     * Split tuples into collections of i-th values (inverse of zip()).
     *
     * @return list<static>
     *
     * @example make([[1, 'a'], [2, 'b']])->unzip()  // [[1, 2], ['a', 'b']]
     */
    public function unzip(): array
    {
        if ($this->items === []) {
            return [];
        }

        $tuples = array_map(fn ($item) => array_values($this->tupleOf($item)), array_values($this->items));
        $width = max(array_map('count', $tuples));
        $result = [];

        for ($i = 0; $i < $width; $i++) {
            $result[] = $this->newInstance(array_map(static fn ($tuple) => $tuple[$i] ?? null, $tuples));
        }

        return $result;
    }

    // -----------------------------------------------------------------
    // Set operations (keys preserved)
    // -----------------------------------------------------------------

    /**
     * Items whose values are not in $items (string comparison, like array_diff()).
     *
     * @example make([1, 2, 3])->diff([2, 3])  // [0 => 1]
     */
    public function diff(mixed $items): static
    {
        return $this->newInstance(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Items not in $items, compared by $callback($a, $b) returning 0 for equal.
     *
     * @example make(['a', 'B'])->diffUsing(['b'], 'strcasecmp')  // [0 => 'a']
     */
    public function diffUsing(mixed $items, callable $callback): static
    {
        return $this->newInstance(array_udiff($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Items whose key/value pair is not in $items.
     *
     * @example make(['a' => 1, 'b' => 2])->diffAssoc(['a' => 1, 'b' => 3])  // ['b' => 2]
     */
    public function diffAssoc(mixed $items): static
    {
        return $this->newInstance(array_diff_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Items whose key/value pair is not in $items, comparing keys with $callback.
     *
     * @example make(['a' => 1, 'b' => 2])->diffAssocUsing(['A' => 1], 'strcasecmp')  // ['b' => 2]
     */
    public function diffAssocUsing(mixed $items, callable $callback): static
    {
        return $this->newInstance(array_diff_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Items whose keys are not in $items.
     *
     * @example make(['a' => 1, 'b' => 2])->diffKeys(['a' => 9])  // ['b' => 2]
     */
    public function diffKeys(mixed $items): static
    {
        return $this->newInstance(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Items whose keys are not in $items, comparing keys with $callback.
     *
     * @example make(['a' => 1, 'b' => 2])->diffKeysUsing(['A' => 9], 'strcasecmp')  // ['b' => 2]
     */
    public function diffKeysUsing(mixed $items, callable $callback): static
    {
        return $this->newInstance(array_diff_ukey($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Items whose values are also in $items.
     *
     * @example make([1, 2, 3])->intersect([2, 3, 4])  // [1 => 2, 2 => 3]
     */
    public function intersect(mixed $items): static
    {
        return $this->newInstance(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Items also in $items, compared by $callback($a, $b) returning 0 for equal.
     *
     * @example make(['a', 'B'])->intersectUsing(['b'], 'strcasecmp')  // [1 => 'B']
     */
    public function intersectUsing(mixed $items, callable $callback): static
    {
        return $this->newInstance(array_uintersect($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Items whose key/value pair is also in $items.
     *
     * @example make(['a' => 1, 'b' => 2])->intersectAssoc(['a' => 1, 'b' => 3])  // ['a' => 1]
     */
    public function intersectAssoc(mixed $items): static
    {
        return $this->newInstance(array_intersect_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Items whose key/value pair is also in $items, comparing keys with $callback.
     *
     * @example make(['a' => 1, 'b' => 2])->intersectAssocUsing(['A' => 1], 'strcasecmp')  // ['a' => 1]
     */
    public function intersectAssocUsing(mixed $items, callable $callback): static
    {
        return $this->newInstance(array_intersect_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Items whose keys are also in $items.
     *
     * @example make(['a' => 1, 'b' => 2])->intersectByKeys(['a' => 0])  // ['a' => 1]
     */
    public function intersectByKeys(mixed $items): static
    {
        return $this->newInstance(array_intersect_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Values present in exactly one of the two collections (re-indexed).
     *
     * @example $old->symmetricDiff($new)  // added + removed values
     */
    public function symmetricDiff(mixed $items): static
    {
        $other = $this->getArrayableItems($items);

        return $this->newInstance(array_merge(
            array_values(array_diff($this->items, $other)),
            array_values(array_diff($other, $this->items)),
        ));
    }

    // -----------------------------------------------------------------
    // Transformation (new collection)
    // -----------------------------------------------------------------

    /**
     * Map every item with $callback($item, $key), keys preserved.
     *
     * Built-in functions receive only the value: map('trim') is safe.
     *
     * @example map(fn($item, $key) => $item * 2)
     * @example map('trim')
     */
    public function map(callable $callback): static
    {
        $callback = static::adaptCallback($callback);
        $result = [];

        foreach ($this->items as $key => $item) {
            $result[$key] = $callback($item, $key);
        }

        return $this->newInstance($result);
    }

    /**
     * Map to [key => value] pairs returned by the callback.
     *
     * @example mapWithKeys(fn($u) => [$u['id'] => $u['name']])
     */
    public function mapWithKeys(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            foreach ($this->getArrayableItems($callback($item, $key)) as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return $this->newInstance($result);
    }

    /**
     * Group values by the single [key => value] pair each callback returns (plain arrays).
     *
     * @example mapToDictionary(fn($u) => [$u['dept'] => $u['name']])
     */
    public function mapToDictionary(callable $callback): static
    {
        $dictionary = [];

        foreach ($this->items as $key => $item) {
            $pair = $this->getArrayableItems($callback($item, $key));

            if ($pair === []) {
                continue;
            }

            $dictionary[array_key_first($pair)][] = reset($pair);
        }

        return $this->newInstance($dictionary);
    }

    /**
     * Like mapToDictionary(), but every group is a collection.
     *
     * @example mapToGroups(fn($user) => [$user['role'] => $user['name']])  // ['admin' => ['Ann', 'Bob'], 'user' => ['Cid']]
     */
    public function mapToGroups(callable $callback): static
    {
        return $this->mapToDictionary($callback)->map(fn ($group) => $this->newInstance($group));
    }

    /**
     * Change only the keys: $callback($key, $value) returns the new key.
     *
     * @example mapKeys(fn($k) => strtolower($k))
     */
    public function mapKeys(callable $callback): static
    {
        $callback = static::adaptCallback($callback);
        $result = [];

        foreach ($this->items as $key => $value) {
            $result[static::normalizeKey($callback($key, $value))] = $value;
        }

        return $this->newInstance($result);
    }

    /**
     * Map each item by spreading it as arguments; the key is passed last.
     *
     * @example mapSpread(fn($x, $y) => $x + $y)
     */
    public function mapSpread(callable $callback): static
    {
        return $this->map(fn ($chunk, $key) => $callback(...[...array_values($this->tupleOf($chunk)), $key]));
    }

    /**
     * Map each item into a new $class($item, $key); backed enums use $class::from($item).
     *
     * @example mapInto(Money::class)
     * @example mapInto(Status::class)  // backed enum, uses Status::from($item)
     */
    public function mapInto(string $class): static
    {
        if (is_subclass_of($class, BackedEnum::class)) {
            return $this->map(static fn ($value) => $class::from($value));
        }

        return $this->map(static fn ($value, $key) => new $class($value, $key));
    }

    /**
     * Map and collapse one level.
     *
     * @example flatMap(fn($u) => $u['emails'])
     */
    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Map and drop null/false results in one pass (keys preserved).
     *
     * @example filterMap(fn($u) => $u['active'] ? $u['email'] : null)
     */
    public function filterMap(callable $callback): static
    {
        $callback = static::adaptCallback($callback);
        $result = [];

        foreach ($this->items as $key => $item) {
            $mapped = $callback($item, $key);

            if ($mapped !== null && $mapped !== false) {
                $result[$key] = $mapped;
            }
        }

        return $this->newInstance($result);
    }

    /**
     * Call $callback($item, $key) for every item; return false to stop.
     *
     * @example each(fn($item, $key) => print($item))
     * @example each(fn($item) => $item < 3)  // returning false stops the loop
     */
    public function each(callable $callback): static
    {
        $callback = static::adaptCallback($callback);

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Like each(), spreading every item as arguments (the key is passed last).
     *
     * @example make([[1, 2], [3, 4]])->eachSpread(fn($a, $b) => print($a + $b))
     */
    public function eachSpread(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback(...[...array_values($this->tupleOf($item)), $key]) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Reduce to a single value with $callback($carry, $item, $key).
     *
     * @example make([1, 2, 3])->reduce(fn($carry, $item) => $carry + $item, 0)  // 6
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;

        foreach ($this->items as $key => $item) {
            $carry = $callback($carry, $item, $key);
        }

        return $carry;
    }

    /**
     * Reduce to several values: the callback receives (...$carry, $item, $key)
     * and must return an array of new carry values.
     *
     * @throws UnexpectedValueException when the callback does not return an array
     *
     * @example [$sum, $count] = reduceSpread(fn($sum, $count, $v) => [$sum + $v, $count + 1], 0, 0)
     */
    public function reduceSpread(callable $callback, mixed ...$initial): array
    {
        $result = array_values($initial);

        foreach ($this->items as $key => $item) {
            $result = $callback(...[...$result, $item, $key]);

            if (!is_array($result)) {
                throw new UnexpectedValueException(sprintf(
                    '%s::reduceSpread() expects the reducer to return an array, got %s.',
                    static::class,
                    get_debug_type($result),
                ));
            }

            $result = array_values($result);
        }

        return $result;
    }

    /**
     * Running reduce: every intermediate carry, keys preserved.
     *
     * @example make([1, 2, 3])->scan(fn($carry, $v) => $carry + $v, 0)  // [1, 3, 6]
     */
    public function scan(callable $callback, mixed $initial = null): static
    {
        $carry = $initial;
        $result = [];

        foreach ($this->items as $key => $item) {
            $carry = $callback($carry, $item, $key);
            $result[$key] = $carry;
        }

        return $this->newInstance($result);
    }

    /**
     * Transform the value(s) at $path inside every item, leaving the rest intact.
     *
     * Wildcards apply the callback to each match. The callback receives
     * ($value, $itemKey). Objects on the path are cloned, never mutated.
     *
     * @example mapPath('price', fn($p) => round($p * 1.2, 2))
     * @example mapPath('tags.*.name', strtoupper(...))
     */
    public function mapPath(string|int|array $path, callable $callback): static
    {
        $segments = $this->toSegments($path);
        $callback = static::adaptCallback($callback);
        $result = [];

        foreach ($this->items as $key => $item) {
            $this->dataTransform($item, $segments, static fn ($value) => $callback($value, $key), true);
            $result[$key] = $item;
        }

        return $this->newInstance($result);
    }

    /**
     * Apply several mapPath() transformations at once.
     *
     * @example evolve(['price' => fn($p) => $p * 1.2, 'meta.slug' => 'strtolower'])
     */
    public function evolve(array $transformers): static
    {
        $result = $this;

        foreach ($transformers as $path => $callback) {
            $result = $result->mapPath((string) $path, $callback);
        }

        return $result;
    }

    /**
     * Values at a path (or callback) across all items; missing paths give null.
     *
     * @example pluck('name')
     * @example pluck('name', 'id')
     * @example pluck('users.*.email')
     * @example pluck(fn($u) => $u['first'] . ' ' . $u['last'], 'id')
     */
    public function pluck(mixed $value, mixed $key = null): static
    {
        $values = $this->retrieveAll($value);

        if ($key === null) {
            return $this->newInstance(array_values($values));
        }

        $result = [];

        foreach ($this->retrieveAll($key) as $itemKey => $resultKey) {
            if (!is_int($resultKey) && !is_string($resultKey)) {
                $resultKey = static::normalizeKey($resultKey);
            }

            $result[$resultKey] = $values[$itemKey];
        }

        return $this->newInstance($result);
    }

    /**
     * Flatten nested arrays/collections into a list, $depth levels deep.
     *
     * @example flatten()   // [[1, [2]], [3]] -> [1, 2, 3]
     * @example flatten(1)  // [[1, [2]], [3]] -> [1, [2], 3]
     */
    public function flatten(int|float $depth = INF): static
    {
        return $this->newInstance(self::flattenItems($this->items, $depth));
    }

    /**
     * Merge every nested array/collection into one list (array_merge semantics);
     * other values are dropped.
     *
     * @example make([[1, 2], [3]])->collapse()  // [1, 2, 3]
     */
    public function collapse(): static
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $results[] = $values->all();
            } elseif (is_array($values)) {
                $results[] = $values;
            }
        }

        return $this->newInstance(array_merge([], ...$results));
    }

    /**
     * Like collapse(), but keys are preserved (array_replace semantics).
     *
     * @example make([['a' => 1], ['b' => 2]])->collapseWithKeys()  // ['a' => 1, 'b' => 2]
     */
    public function collapseWithKeys(): static
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $results[] = $values->all();
            } elseif (is_array($values)) {
                $results[] = $values;
            }
        }

        return $this->newInstance($results === [] ? [] : array_replace(...$results));
    }

    /**
     * Flatten nested arrays into delimiter-joined keys. Empty arrays are kept.
     *
     * @example dot()  // ['user' => ['name' => 'A']] -> ['user.name' => 'A']
     */
    public function dot(string $prefix = ''): static
    {
        return $this->newInstance($this->dotItems($this->items, $prefix));
    }

    /**
     * Expand delimiter-joined keys into nested arrays (inverse of dot()).
     *
     * The wildcard has no special meaning here; escaped delimiters stay literal.
     *
     * @example make(['user.name' => 'Alice', 'user.age' => 30])->undot()  // ['user' => ['name' => 'Alice', 'age' => 30]]
     */
    public function undot(): static
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $this->dataSet($result, $this->toSegments($key, wildcards: false), $value);
        }

        return $this->newInstance($result);
    }

    /**
     * Swap keys and values.
     *
     * @example make(['a' => 1, 'b' => 2])->flip()  // [1 => 'a', 2 => 'b']
     */
    public function flip(): static
    {
        return $this->newInstance(array_flip($this->items));
    }

    /**
     * Rows become columns; missing cells are null.
     *
     * @example make([[1, 2], [3, 4]])->transpose()  // [[1, 3], [2, 4]]
     */
    public function transpose(): static
    {
        if ($this->items === []) {
            return $this->newInstance();
        }

        $rows = array_map(fn ($row) => array_values($this->tupleOf($row)), array_values($this->items));
        $width = max(array_map('count', $rows));
        $result = [];

        for ($column = 0; $column < $width; $column++) {
            $result[] = array_map(static fn ($row) => $row[$column] ?? null, $rows);
        }

        return $this->newInstance($result);
    }

    /**
     * Pad to $size with $value (negative size pads at the start).
     *
     * @example make([1, 2])->pad(4, 0)   // [1, 2, 0, 0]
     * @example make([1, 2])->pad(-4, 0)  // [0, 0, 1, 2]
     */
    public function pad(int $size, mixed $value = null): static
    {
        return $this->newInstance(array_pad($this->items, $size, $value));
    }

    /**
     * Repeat all values $multiplier times.
     *
     * @example make(['a', 'b'])->multiply(2)  // ['a', 'b', 'a', 'b']
     */
    public function multiply(int $multiplier): static
    {
        $result = [];

        for ($i = 0; $i < $multiplier; $i++) {
            foreach ($this->items as $item) {
                $result[] = $item;
            }
        }

        return $this->newInstance($result);
    }

    /**
     * Every $step-th item starting at $offset (re-indexed).
     *
     * @throws InvalidArgumentException when $step < 1
     *
     * @example make(['a', 'b', 'c', 'd'])->nth(2)     // ['a', 'c']
     * @example make(['a', 'b', 'c', 'd'])->nth(2, 1)  // ['b', 'd']
     */
    public function nth(int $step, int $offset = 0): static
    {
        if ($step < 1) {
            throw new InvalidArgumentException('Step value must be at least 1.');
        }

        $result = [];
        $position = 0;

        foreach ($this->slice($offset)->items as $item) {
            if ($position % $step === 0) {
                $result[] = $item;
            }

            $position++;
        }

        return $this->newInstance($result);
    }

    /**
     * Keep only the given paths (keys preserved, nested structure kept).
     *
     * @example only('id', 'name')
     * @example only(['user.name', 'meta'])
     * @example only('users.*.email')
     */
    public function only(mixed $keys, string|int ...$more): static
    {
        if ($keys === null) {
            return $this->newInstance($this->items);
        }

        $keys = is_string($keys) || is_int($keys) ? [$keys, ...$more] : $this->getArrayableItems($keys);

        return $this->newInstance($this->pickPaths($this->items, $keys));
    }

    /**
     * Remove the given paths (keys preserved). Objects on the path are cloned.
     *
     * @example except('password')
     * @example except(['user.token', 'users.*.password'])
     */
    public function except(mixed $keys, string|int ...$more): static
    {
        if ($keys === null) {
            return $this->newInstance($this->items);
        }

        $keys = is_string($keys) || is_int($keys) ? [$keys, ...$more] : $this->getArrayableItems($keys);
        $items = $this->items;

        foreach ($keys as $path) {
            if (array_key_exists($path, $items)) {
                unset($items[$path]);

                continue;
            }

            $this->dataForget($items, $this->toSegments($path), true);
        }

        return $this->newInstance($items);
    }

    /**
     * Keep only the given paths inside every item (keys preserved).
     *
     * @example select('id', 'name')
     * @example select(['id', 'user.email'])
     */
    public function select(mixed $keys, string|int ...$more): static
    {
        if ($keys === null) {
            return $this->newInstance($this->items);
        }

        $keys = is_string($keys) || is_int($keys) ? [$keys, ...$more] : $this->getArrayableItems($keys);

        return $this->map(fn ($item) => $this->pickPaths($item, $keys));
    }

    /**
     * Group items by a path, a callback, or several levels.
     *
     * When the group value is an array the item joins every group in it.
     *
     * @example groupBy('role')
     * @example groupBy(['country', 'city'])            // nested groups
     * @example groupBy('tags')                         // item in each of its tags
     * @example groupBy(fn($u) => $u['age'] >= 18 ? 'adult' : 'minor', preserveKeys: true)
     */
    public function groupBy(mixed $groupBy, bool $preserveKeys = false): static
    {
        $nextGroups = [];

        if (!$this->useAsCallable($groupBy) && is_array($groupBy)) {
            $nextGroups = $groupBy;
            $groupBy = array_shift($nextGroups);
        }

        $results = [];

        foreach ($this->retrieveAll($groupBy) as $key => $groupKeys) {
            $item = $this->items[$key];

            if (is_int($groupKeys) || is_string($groupKeys)) {
                if ($preserveKeys) {
                    $results[$groupKeys][$key] = $item;
                } else {
                    $results[$groupKeys][] = $item;
                }

                continue;
            }

            foreach (is_array($groupKeys) ? $groupKeys : [$groupKeys] as $groupKey) {
                if (!is_int($groupKey) && !is_string($groupKey)) {
                    $groupKey = static::normalizeKey($groupKey);
                }

                if ($preserveKeys) {
                    $results[$groupKey][$key] = $item;
                } else {
                    $results[$groupKey][] = $item;
                }
            }
        }

        $result = $this->newInstance(array_map(fn ($group) => $this->newInstance($group), $results));

        return $nextGroups === []
            ? $result
            : $result->map(static fn (self $group) => $group->groupBy($nextGroups, $preserveKeys));
    }

    /**
     * Key items by a path or callback (later items win on duplicates).
     *
     * @example keyBy('id')
     * @example keyBy(fn($u) => strtolower($u['email']))
     */
    public function keyBy(mixed $keyBy): static
    {
        $result = [];

        foreach ($this->retrieveAll($keyBy) as $key => $resultKey) {
            if (!is_int($resultKey) && !is_string($resultKey)) {
                $resultKey = static::normalizeKey($resultKey);
            }

            $result[$resultKey] = $this->items[$key];
        }

        return $this->newInstance($result);
    }

    /**
     * Alias for keyBy().
     *
     * @example mapBy('id')
     */
    public function mapBy(mixed $key): static
    {
        return $this->keyBy($key);
    }

    /**
     * Remove duplicates (keys preserved; first occurrence wins).
     *
     * Without $key and non-strict, array_unique() with $flags is used.
     *
     * @example unique()
     * @example unique('email')
     * @example unique(fn($u) => strtolower($u['email']), strict: true)
     * @example unique(flags: SORT_STRING)
     */
    public function unique(mixed $key = null, bool $strict = false, int $flags = SORT_REGULAR): static
    {
        if ($key === null && !$strict) {
            return $this->newInstance(array_unique($this->items, $flags));
        }

        // Scalars are tracked in a hash map (O(1)); other values fall back to in_array().
        $seen = [];
        $exists = [];
        $result = [];

        foreach ($this->retrieveAll($key) as $itemKey => $id) {
            $hash = self::comparisonHash($id, $strict);

            if ($hash !== null) {
                if (isset($seen[$hash])) {
                    continue;
                }

                $seen[$hash] = true;
            } elseif (in_array($id, $exists, $strict)) {
                continue;
            } else {
                $exists[] = $id;
            }

            $result[$itemKey] = $this->items[$itemKey];
        }

        return $this->newInstance($result);
    }

    /**
     * unique() with strict comparison.
     *
     * @example make([1, '1', 2, 2])->uniqueStrict()  // [1, '1', 2]
     */
    public function uniqueStrict(mixed $key = null): static
    {
        return $this->unique($key, true);
    }

    /**
     * Values that repeat an earlier value (keyed like the source).
     *
     * With a path or callback the retrieved values are returned, like Laravel.
     *
     * @example duplicates()          // [2 => 'a']
     * @example duplicates('email')   // [3 => 'x@y.z']
     */
    public function duplicates(mixed $callback = null, bool $strict = false): static
    {
        $items = $this->newInstance($this->retrieveAll($callback));
        $unique = $items->unique(null, $strict)->all();
        $compare = $strict
            ? static fn ($a, $b) => $a === $b
            : static fn ($a, $b) => $a == $b;

        $duplicates = [];

        foreach ($items->items as $key => $value) {
            if ($unique !== [] && $compare($value, reset($unique))) {
                array_shift($unique);
            } else {
                $duplicates[$key] = $value;
            }
        }

        return $this->newInstance($duplicates);
    }

    /**
     * duplicates() with strict comparison.
     *
     * @example make([1, '1', 1])->duplicatesStrict()  // [2 => 1]
     */
    public function duplicatesStrict(mixed $callback = null): static
    {
        return $this->duplicates($callback, true);
    }

    /**
     * Build a tree from a flat list linked by id / parent id.
     *
     * Roots are items whose parent is missing or equals $rootId. Items whose
     * parent does not exist are dropped, or become roots with $orphansAsRoots.
     * Cycles are cut. Objects are cloned before children are attached.
     *
     * @example $categories->toTree()
     * @example $nodes->toTree('id', 'parent_id', 'children', rootId: 0)
     */
    public function toTree(
        string|int $idKey = 'id',
        string|int $parentKey = 'parent_id',
        string|int $childrenKey = 'children',
        mixed $rootId = null,
        bool $orphansAsRoots = false,
    ): static {
        $missing = static::missing();
        $idSegs = $this->toSegments($idKey);
        $parentSeg = $this->toSegments($parentKey);
        $rootKey = $rootId === null ? null : (string) static::normalizeKey($rootId);

        $nodes = [];
        $children = [];
        $roots = [];

        foreach ($this->items as $item) {
            $id = $this->dataGet($item, $idSegs);

            if ($id === $missing || $id === null) {
                continue;
            }

            $nodes[(string) static::normalizeKey($id)] = $item;
        }

        foreach ($nodes as $id => $node) {
            $parent = $this->dataGet($node, $parentSeg);
            $parentKey = $parent === $missing || $parent === null ? null : (string) static::normalizeKey($parent);

            if ($parentKey === $rootKey) {
                $roots[] = $id;
            } elseif ($parentKey !== null && isset($nodes[$parentKey])) {
                $children[$parentKey][] = $id;
            } elseif ($orphansAsRoots) {
                $roots[] = $id;
            }
        }

        $childrenSegments = $this->toSegments([$childrenKey]);

        $build = function (string $id, array $path) use (&$build, $nodes, $children, $childrenSegments) {
            $node = $nodes[$id];
            $kids = [];
            $path[$id] = true;

            foreach ($children[$id] ?? [] as $childId) {
                if (!isset($path[$childId])) {
                    $kids[] = $build((string) $childId, $path);
                }
            }

            $this->dataSet($node, $childrenSegments, $kids, true, true);

            return $node;
        };

        return $this->newInstance(array_map(static fn ($id) => $build((string) $id, []), $roots));
    }

    /**
     * Insert $value at position $index and re-index (negative counts from the end).
     *
     * @example insert(0, 'first')
     * @example insert(-1, 'before last')
     */
    public function insert(int $index, mixed $value): static
    {
        $items = array_values($this->items);
        $count = count($items);
        $index = $index < 0 ? max(0, $count + $index) : min($index, $count);

        array_splice($items, $index, 0, [$value]);

        return $this->newInstance($items);
    }

    // -----------------------------------------------------------------
    // Slicing & chunking (keys preserved)
    // -----------------------------------------------------------------

    /**
     * array_slice(); keys are preserved unless $preserveKeys is false.
     *
     * @example make([1, 2, 3, 4])->slice(2)     // [2 => 3, 3 => 4]
     * @example make([1, 2, 3, 4])->slice(1, 2)  // [1 => 2, 2 => 3]
     * @example make([1, 2, 3, 4])->slice(2, preserveKeys: false)  // [3, 4]
     */
    public function slice(int $offset, ?int $length = null, bool $preserveKeys = true): static
    {
        return $this->newInstance(array_slice($this->items, $offset, $length, $preserveKeys));
    }

    /**
     * First $limit items, or the last ones for a negative limit.
     *
     * @example make([1, 2, 3])->take(2)   // [1, 2]
     * @example make([1, 2, 3])->take(-2)  // [1 => 2, 2 => 3]
     */
    public function take(int $limit): static
    {
        return $limit < 0 ? $this->slice($limit, abs($limit)) : $this->slice(0, $limit);
    }

    /**
     * Everything after the first $count items.
     *
     * @example make([1, 2, 3])->skip(1)  // [1 => 2, 2 => 3]
     */
    public function skip(int $count): static
    {
        return $this->slice($count);
    }

    /**
     * Items of a 1-based page.
     *
     * @example forPage(2, 15)  // items 16-30
     */
    public function forPage(int $page, int $perPage): static
    {
        return $this->slice(max(0, ($page - 1) * $perPage), $perPage);
    }

    /**
     * Page items plus metadata. 'data' is a re-indexed collection.
     *
     * @return array{data: static, total: int, per_page: int, current_page: int, last_page: int, from: int|null, to: int|null}
     *
     * @example paginate(10)
     * @example paginate(perPage: 10, page: 2)['data']
     */
    public function paginate(int $perPage, int $page = 1): array
    {
        $total = count($this->items);
        $lastPage = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * max(0, $perPage);
        $slice = $this->slice($offset, max(0, $perPage))->values();

        return [
            'data' => $slice,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $slice->isEmpty() ? null : $offset + 1,
            'to' => $slice->isEmpty() ? null : $offset + $slice->count(),
        ];
    }

    /**
     * Chunks of $size; an empty collection for $size < 1.
     *
     * @example make([1, 2, 3, 4, 5])->chunk(2, preserveKeys: false)  // [[1, 2], [3, 4], [5]]
     * @example make([1, 2, 3])->chunk(2)  // [[0 => 1, 1 => 2], [2 => 3]] - keys are preserved by default
     */
    public function chunk(int $size, bool $preserveKeys = true): static
    {
        if ($size < 1) {
            return $this->newInstance();
        }

        return $this->newInstance(array_map(
            fn ($chunk) => $this->newInstance($chunk),
            array_chunk($this->items, $size, $preserveKeys),
        ));
    }

    /**
     * Start a new chunk whenever $callback($item, $key, $currentChunk) returns false.
     *
     * @example chunkWhile(fn($v, $k, $chunk) => $v === $chunk->last() + 1)  // consecutive runs
     */
    public function chunkWhile(callable $callback): static
    {
        $chunks = [];
        $chunk = [];

        foreach ($this->items as $key => $item) {
            if ($chunk !== [] && !$callback($item, $key, $this->newInstance($chunk))) {
                $chunks[] = $this->newInstance($chunk);
                $chunk = [];
            }

            $chunk[$key] = $item;
        }

        if ($chunk !== []) {
            $chunks[] = $this->newInstance($chunk);
        }

        return $this->newInstance($chunks);
    }

    /**
     * Sliding windows of $size items, advancing $step items each time.
     *
     * @throws InvalidArgumentException when $size or $step < 1
     *
     * @example make([1, 2, 3, 4])->sliding(2)  // [[1, 2], [2, 3], [3, 4]]
     */
    public function sliding(int $size = 2, int $step = 1): static
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Size value must be at least 1.');
        }

        if ($step < 1) {
            throw new InvalidArgumentException('Step value must be at least 1.');
        }

        $windows = (int) floor((count($this->items) - $size) / $step) + 1;
        $result = [];

        for ($i = 0; $i < $windows; $i++) {
            $result[] = $this->slice($i * $step, $size);
        }

        return $this->newInstance($result);
    }

    /**
     * Split into $numberOfGroups groups whose sizes differ by at most one.
     *
     * @throws InvalidArgumentException when $numberOfGroups < 1
     *
     * @example make(range(1, 10))->split(3)  // sizes 4, 3, 3
     */
    public function split(int $numberOfGroups): static
    {
        if ($numberOfGroups < 1) {
            throw new InvalidArgumentException('Number of groups must be at least 1.');
        }

        $count = count($this->items);
        $groupSize = intdiv($count, $numberOfGroups);
        $remain = $count % $numberOfGroups;
        $groups = [];
        $start = 0;

        for ($i = 0; $i < $numberOfGroups && $start < $count; $i++) {
            $size = $groupSize + ($i < $remain ? 1 : 0);
            $groups[] = $this->newInstance(array_slice($this->items, $start, $size));
            $start += $size;
        }

        return $this->newInstance($groups);
    }

    /**
     * Split into $numberOfGroups groups, filling the first groups completely.
     *
     * @throws InvalidArgumentException when $numberOfGroups < 1
     *
     * @example make(range(1, 10))->splitIn(3)  // sizes 4, 4, 2
     */
    public function splitIn(int $numberOfGroups): static
    {
        if ($numberOfGroups < 1) {
            throw new InvalidArgumentException('Number of groups must be at least 1.');
        }

        return $this->chunk((int) ceil(count($this->items) / $numberOfGroups));
    }

    /**
     * Reverse the order; keys are preserved unless $preserveKeys is false.
     *
     * @example make([1, 2, 3])->reverse()        // [2 => 3, 1 => 2, 0 => 1]
     * @example make([1, 2, 3])->reverse(false)  // [3, 2, 1]
     */
    public function reverse(bool $preserveKeys = true): static
    {
        return $this->newInstance(array_reverse($this->items, $preserveKeys));
    }

    /**
     * Random order (re-indexed). Pass a Randomizer for a seeded/secure engine.
     *
     * @example shuffle(new Randomizer(new \Random\Engine\Mt19937(42)))
     */
    public function shuffle(?Randomizer $randomizer = null): static
    {
        $items = $this->items;

        if ($randomizer !== null) {
            return $this->newInstance($randomizer->shuffleArray($items));
        }

        shuffle($items);

        return $this->newInstance($items);
    }

    /**
     * One random item, or a collection of $number random items.
     *
     * $number may be a callable receiving the collection. Pass a Randomizer
     * for a seeded/secure engine.
     *
     * @throws InvalidArgumentException when more items are requested than available
     *
     * @example random()       // a single item
     * @example random(3)      // collection of 3 items
     * @example random(1)      // collection of 1 item
     */
    public function random(mixed $number = null, bool $preserveKeys = false, ?Randomizer $randomizer = null): mixed
    {
        $requested = match (true) {
            $number === null => 1,
            $this->useAsCallable($number) => (int) $number($this),
            default => (int) $number,
        };

        $count = count($this->items);

        if ($requested < 0 || $requested > $count) {
            throw new InvalidArgumentException(
                "You requested {$requested} items, but there are only {$count} items available.",
            );
        }

        if ($requested === 0) {
            return $this->newInstance();
        }

        $keys = $randomizer !== null
            ? $randomizer->pickArrayKeys($this->items, $requested)
            : (array) array_rand($this->items, $requested);

        if ($number === null) {
            return $this->items[$keys[0]];
        }

        $result = [];

        foreach ($keys as $key) {
            if ($preserveKeys) {
                $result[$key] = $this->items[$key];
            } else {
                $result[] = $this->items[$key];
            }
        }

        return $this->newInstance($result);
    }

    // -----------------------------------------------------------------
    // Strings
    // -----------------------------------------------------------------

    /**
     * Join values; $finalGlue (when not empty) joins the last two.
     *
     * @example join(', ', ' and ')  // 'a, b and c'
     */
    public function join(string $glue = '', ?string $finalGlue = ''): string
    {
        if ($finalGlue === null || $finalGlue === '') {
            return $this->implode($glue);
        }

        $count = count($this->items);

        if ($count === 0) {
            return '';
        }

        $values = array_values($this->items);
        $last = array_pop($values);

        return $count === 1 ? (string) $last : implode($glue, $values).$finalGlue.$last;
    }

    /**
     * Join values; for arrays/objects the first argument is a path (or callback).
     *
     * @example implode(', ')               // scalar items
     * @example implode('name', ', ')       // path, then glue
     * @example implode(fn($u) => $u['name'], ', ')
     */
    public function implode(mixed $value = '', ?string $glue = null): string
    {
        if ($this->useAsCallable($value)) {
            return implode($glue ?? '', $this->map($value)->all());
        }

        $first = $this->first();

        if (is_array($first) || (is_object($first) && !$first instanceof Stringable)) {
            return implode($glue ?? '', $this->pluck($value)->all());
        }

        return implode((string) $value, $this->items);
    }

    // -----------------------------------------------------------------
    // Pipelines & debugging
    // -----------------------------------------------------------------

    /**
     * Pass the collection to $callback and return its result.
     *
     * @example pipe(fn($collection) => $collection->sum())
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Return new $class($this).
     *
     * @example pipeInto(ResponseData::class)
     */
    public function pipeInto(string $class): mixed
    {
        return new $class($this);
    }

    /**
     * Pass the collection through callables in order; each gets the previous result.
     *
     * @example pipeThrough([fn($c) => $c->filter(), fn($c) => $c->count()])
     */
    public function pipeThrough(array $callbacks): mixed
    {
        return array_reduce($callbacks, static fn ($carry, $callback) => $callback($carry), $this);
    }

    /**
     * Call $callback($this) for side effects and return $this.
     *
     * @example tap(fn($collection) => logger($collection->count()))
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Dump the items (and any extra values) and keep chaining.
     *
     * @example where('active', true)->dump()->pluck('name')
     */
    public function dump(mixed ...$args): static
    {
        foreach ([$this->items, ...$args] as $value) {
            function_exists('dump') ? dump($value) : var_dump($value);
        }

        return $this;
    }

    /**
     * Dump the items (and any extra values) and end the script.
     *
     * @example where('active', true)->dd()
     */
    public function dd(mixed ...$args): never
    {
        if (function_exists('dd')) {
            dd($this->items, ...$args);
        }

        foreach ([$this->items, ...$args] as $value) {
            var_dump($value);
        }

        exit(1);
    }

    /**
     * A copy whose nested collections are copied too (other objects are shared).
     *
     * @example $copy = $original->copy();  // changing nested collections of $copy leaves $original untouched
     */
    public function copy(): static
    {
        $copy = clone $this;
        $copy->items = self::copyCollections($this->items);

        return $copy;
    }

    /**
     * Assert that every item is of one of the given types.
     *
     * Types: int, float, string, bool, array, null, numeric, scalar, object,
     * callable, iterable, countable, mixed, or a class/interface name.
     *
     * @throws UnexpectedValueException
     *
     * @example ensure('int')
     * @example ensure(['int', 'float'])
     * @example ensure(User::class)
     */
    public function ensure(string|array $types): static
    {
        $types = (array) $types;

        foreach ($this->items as $index => $item) {
            foreach ($types as $type) {
                if (self::isOfType($item, $type)) {
                    continue 2;
                }
            }

            throw new UnexpectedValueException(sprintf(
                "Collection should only include [%s] items, but '%s' found at position %s.",
                implode(', ', $types),
                get_debug_type($item),
                $index,
            ));
        }

        return $this;
    }

    // -----------------------------------------------------------------
    // Conversion
    // -----------------------------------------------------------------

    /**
     * Items as a plain array; nested collections/Arrayable values are converted too.
     *
     * @example make([make([1]), 2])->toArray()  // [[1], 2]
     */
    public function toArray(): array
    {
        return self::arrayify($this->items);
    }

    /**
     * Get the data to serialize with json_encode().
     *
     * @example json_encode(make(['a' => 1]))  // '{"a":1}'
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return array_map(static fn ($value) => match (true) {
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            $value instanceof Arrayable => $value->toArray(),
            default => $value,
        }, $this->items);
    }

    /**
     * JSON string. $flags/$depth go to json_encode(); JSON_THROW_ON_ERROR is always on.
     *
     * @throws JsonException
     *
     * @example make(['a' => 1])->toJson()  // '{"a":1}'
     * @example toJson(JSON_UNESCAPED_UNICODE)
     */
    public function toJson(int $flags = 0, int $depth = 512): string
    {
        return json_encode($this->jsonSerialize(), $flags | JSON_THROW_ON_ERROR, $depth);
    }

    /**
     * Pretty-printed JSON string.
     *
     * @throws JsonException
     *
     * @example toPrettyJson(JSON_UNESCAPED_SLASHES)
     */
    public function toPrettyJson(int $flags = 0, int $depth = 512): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | $flags, $depth);
    }

    /**
     * HTML-escape the JSON produced by string casting.
     *
     * @example echo $collection->escapeWhenCastingToString();  // safe to print into HTML
     */
    public function escapeWhenCastingToString(bool $escape = true): static
    {
        $this->escapeWhenCastingToString = $escape;

        return $this;
    }

    /**
     * JSON string of the items, HTML-escaped when escapeWhenCastingToString() is on.
     *
     * @example (string) make(['a' => 1])  // '{"a":1}'
     */
    public function __toString(): string
    {
        return $this->escapeWhenCastingToString
            ? htmlspecialchars($this->toJson(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : $this->toJson();
    }

    /**
     * Iterate over the items, so a collection can be used in foreach.
     *
     * @example foreach ($collection as $key => $item) { ... }
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    // -----------------------------------------------------------------
    // ArrayAccess (dot-notation aware)
    // -----------------------------------------------------------------

    /**
     * isset($c['a.b']) - the path exists and its value is not null.
     *
     * @example isset($collection['user.name'])
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->get(self::offsetPath($offset)) !== null;
    }

    /**
     * Get the value at a dot-notation path with array syntax.
     *
     * @example $collection['user.name']
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get(self::offsetPath($offset));
    }

    /**
     * Set the value at a dot-notation path with array syntax; $c[] = $value appends.
     *
     * @example $collection['user.name'] = 'Alice'
     * @example $collection[] = 4
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->set(self::offsetPath($offset), $value);
        }
    }

    /**
     * Remove the value at a dot-notation path with array syntax.
     *
     * @example unset($collection['user.token'])
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->forget(self::offsetPath($offset));
    }

    // -----------------------------------------------------------------
    // Higher-order messages
    // -----------------------------------------------------------------

    /**
     * Register a method usable as a higher-order message.
     *
     * @example Collection::proxy('sortBy')  // enables $collection->sortBy->name
     */
    public static function proxy(string $method): void
    {
        static::$proxies[] = $method;
    }

    /**
     * Create a higher-order message proxy for a registered method.
     *
     * $users->map->name, $users->sum->{'stats.score'}, $users->each->save()
     *
     * @throws Exception for unknown properties
     *
     * @example $users->map->name
     * @example $users->sum->{'stats.score'}
     * @example $users->each->save()
     */
    public function __get(string $key): HigherOrderCollectionProxy
    {
        if (!in_array($key, static::$proxies, true)) {
            throw new Exception("Property [{$key}] does not exist on this collection instance.");
        }

        return new HigherOrderCollectionProxy($this, $key, fn ($item, $path) => $this->valueAt($item, $path));
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * A new collection with the same wildcard and delimiter.
     */
    protected function newInstance(array $items = []): static
    {
        return new static($items, $this->wildcard, $this->delimiter);
    }

    /**
     * Convert any supported value into an array of items.
     */
    protected function getArrayableItems(mixed $items): array
    {
        return match (true) {
            is_array($items) => $items,
            $items === null => [],
            $items instanceof self => $items->all(),
            $items instanceof Arrayable => $items->toArray(),
            $items instanceof Traversable => iterator_to_array($items),
            $items instanceof JsonSerializable => (array) $items->jsonSerialize(),
            $items instanceof UnitEnum => [$items],
            is_object($items) && method_exists($items, 'toArray') => (array) $items->toArray(),
            default => (array) $items,
        };
    }

    /**
     * A value used as a closure default is called; anything else is returned as-is.
     */
    protected static function resolveValue(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }

    private function pathExists(string|int $path): bool
    {
        return array_key_exists($path, $this->items) || $this->dataHas($this->items, $this->toSegments($path));
    }

    /**
     * Merge the picked sub-structures of several paths.
     */
    private function pickPaths(mixed $target, array $paths): array
    {
        $missing = static::missing();
        $result = [];

        foreach ($paths as $path) {
            if (is_array($target) && (is_string($path) || is_int($path)) && array_key_exists($path, $target)) {
                $picked = [$path => $target[$path]];
            } else {
                $picked = $this->dataPick($target, $this->toSegments($path));
            }

            if ($picked !== $missing) {
                $result = array_replace_recursive($result, $picked);
            }
        }

        return $result;
    }

    /**
     * An item as positional arguments for spread-style methods.
     */
    private function tupleOf(mixed $item): array
    {
        return is_array($item) || $item instanceof self || $item instanceof Traversable
            ? $this->getArrayableItems($item)
            : [$item];
    }

    private function dotItems(array $items, string $prefix): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.$this->delimiter.$key;

            if ($value instanceof self) {
                $value = $value->all();
            }

            if (is_array($value) && $value !== []) {
                $result += $this->dotItems($value, $fullKey);
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    private static function flattenItems(array $items, int|float $depth): array
    {
        $result = [];

        foreach ($items as $item) {
            if ($item instanceof self) {
                $item = $item->all();
            }

            if (!is_array($item) || $depth < 1) {
                $result[] = $item;

                continue;
            }

            foreach ($depth <= 1 ? $item : self::flattenItems($item, $depth - 1) as $value) {
                $result[] = $value;
            }
        }

        return $result;
    }

    private static function arrayify(array $items): array
    {
        foreach ($items as $key => $value) {
            if ($value instanceof Arrayable) {
                $items[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $items[$key] = self::arrayify($value);
            }
        }

        return $items;
    }

    private static function copyCollections(array $items): array
    {
        foreach ($items as $key => $value) {
            if ($value instanceof self) {
                $items[$key] = $value->copy();
            } elseif (is_array($value)) {
                $items[$key] = self::copyCollections($value);
            }
        }

        return $items;
    }

    private static function isOfType(mixed $item, string $type): bool
    {
        return match (strtolower($type)) {
            'int', 'integer' => is_int($item),
            'float', 'double' => is_float($item),
            'string' => is_string($item),
            'bool', 'boolean' => is_bool($item),
            'array' => is_array($item),
            'null' => $item === null,
            'numeric' => is_numeric($item),
            'scalar' => is_scalar($item),
            'object' => is_object($item),
            'callable' => is_callable($item),
            'iterable' => is_iterable($item),
            'countable' => is_countable($item),
            'mixed' => true,
            default => $item instanceof $type || get_debug_type($item) === $type,
        };
    }

    /**
     * A hash that is equal for two scalars exactly when they compare equal
     * (=== when strict, == otherwise); null for values that need in_array().
     */
    private static function comparisonHash(mixed $value, bool $strict): ?string
    {
        if ($strict) {
            return match (true) {
                is_int($value) => 'i:'.$value,
                is_string($value) => 's:'.$value,
                is_float($value) && !is_nan($value) => 'f:'.sprintf('%.17g', $value),
                is_bool($value) => 'b:'.(int) $value,
                $value === null => 'n',
                default => null,
            };
        }

        if (is_string($value) && !is_numeric($value)) {
            return 's:'.$value;
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            $number = $value + 0;

            if (is_float($number)) {
                if (!is_finite($number)) {
                    return null;
                }

                if (floor($number) === $number && abs($number) < 2 ** 53) {
                    $number = (int) $number;
                }
            }

            return 'n:'.(is_int($number) ? $number : sprintf('%.17g', $number));
        }

        return null;
    }

    private static function looksLikeJson(string $value): bool
    {
        $trimmed = ltrim($value);

        return ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) && json_validate($value);
    }

    private static function offsetPath(mixed $offset): string|int
    {
        return is_int($offset) || is_string($offset) ? $offset : (string) static::normalizeKey($offset);
    }
}

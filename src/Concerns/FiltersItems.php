<?php

declare(strict_types=1);

namespace Collection\Concerns;

use Collection\Exceptions\ItemNotFoundException;
use Collection\Exceptions\MultipleItemsFoundException;
use InvalidArgumentException;
use Stringable;

/**
 * Filtering, searching and item lookup.
 *
 * Filtering methods return a new collection and preserve keys (call
 * values() to re-index). Conditions accept where()-style arguments:
 *
 *   (callable)               -> callback($item, $key)
 *   ('path')                 -> value at path is truthy
 *   ('path', $value)         -> value at path == $value
 *   ('path', '>=', $value)   -> any of = == === != <> !== < > <= >= <=>
 *
 * A missing path reads as null, like Laravel's data_get().
 */
trait FiltersItems
{
    /**
     * Keep items passing the test; without arguments removes falsy items.
     *
     * Same arguments as where(), except that ('path', $value) compares
     * strictly (===). Use where() for Laravel's loose comparison.
     *
     * @example filter()
     * @example filter(fn($v, $k) => $v > 1)
     * @example filter('active')
     * @example filter('role', 'admin')
     * @example filter('age', '>=', 18)
     */
    public function filter(mixed $callback = null, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 0 || ($callback === null && func_num_args() === 1)) {
            return $this->newInstance(array_filter($this->items));
        }

        return $this->filterBy(func_get_args(), strictPair: true, keep: true);
    }

    /**
     * Remove items passing the test; without arguments removes truthy items.
     *
     * @example reject(fn($u) => $u['banned'])
     * @example reject('active')
     * @example reject('role', 'guest')
     */
    public function reject(mixed $callback = null, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 0 || ($callback === null && func_num_args() === 1)) {
            return $this->newInstance(array_filter($this->items, static fn ($item) => !$item));
        }

        return $this->filterBy(func_get_args(), strictPair: true, keep: false);
    }

    /**
     * Keep items matching a where()-style condition.
     *
     * @example where('active')
     * @example where('role', 'admin')              // loose ==
     * @example where('age', '>=', 18)
     * @example where('users.*.active', '!=', [])  // wildcard paths work too
     */
    public function where(mixed $key, mixed $operator = null, mixed $value = null): static
    {
        return $this->filterBy(func_get_args(), strictPair: false, keep: true);
    }

    /**
     * Keep items whose value at $key is strictly equal (===) to $value.
     *
     * @example whereStrict('status', 1)  // int 1 only, not '1'
     */
    public function whereStrict(mixed $key, mixed $value): static
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Keep items whose value at $key is in $values (loose by default).
     *
     * @example whereIn('role', ['admin', 'editor'])
     */
    public function whereIn(mixed $key, iterable $values, bool $strict = false): static
    {
        $values = $this->getArrayableItems($values);

        return $this->keepByValue($key, static fn ($value) => in_array($value, $values, $strict));
    }

    /**
     * Keep items whose value at $key is in $values, compared strictly.
     *
     * @example whereInStrict('id', [1, 2])  // '1' does not match
     */
    public function whereInStrict(mixed $key, iterable $values): static
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Keep items whose value at $key is not in $values (loose by default).
     *
     * @example whereNotIn('role', ['guest', 'banned'])
     */
    public function whereNotIn(mixed $key, iterable $values, bool $strict = false): static
    {
        $values = $this->getArrayableItems($values);

        return $this->keepByValue($key, static fn ($value) => !in_array($value, $values, $strict));
    }

    /**
     * Keep items whose value at $key is not in $values, compared strictly.
     *
     * @example whereNotInStrict('id', [1, 2])
     */
    public function whereNotInStrict(mixed $key, iterable $values): static
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Keep items whose value at $key is null or missing; without $key, items that are null.
     *
     * @example whereNull('deleted_at')
     */
    public function whereNull(mixed $key = null): static
    {
        return $this->whereStrict($key, null);
    }

    /**
     * Keep items whose value at $key exists and is not null.
     *
     * @example whereNotNull('email')
     */
    public function whereNotNull(mixed $key = null): static
    {
        return $this->where($key, '!==', null);
    }

    /**
     * Keep items whose value at $key is within [min, max] (inclusive).
     *
     * @example whereBetween('age', [18, 65])
     */
    public function whereBetween(mixed $key, iterable $values): static
    {
        $values = $this->getArrayableItems($values);

        return $this->where($key, '>=', reset($values))->where($key, '<=', end($values));
    }

    /**
     * Keep items whose value at $key is outside [min, max].
     *
     * @example whereNotBetween('age', [18, 65])
     */
    public function whereNotBetween(mixed $key, iterable $values): static
    {
        $values = $this->getArrayableItems($values);
        $min = reset($values);
        $max = end($values);

        return $this->keepByValue($key, static fn ($value) => $value < $min || $value > $max);
    }

    /**
     * Keep items (or values at $path) that are instances of the given class(es).
     *
     * @example whereInstanceOf(User::class)
     * @example whereInstanceOf([Admin::class, Moderator::class])
     * @example whereInstanceOf(PaymentService::class, 'service')
     */
    public function whereInstanceOf(string|array $type, mixed $path = null): static
    {
        $types = (array) $type;
        $retrieve = $this->valueRetriever($path);

        return $this->filter(static function ($item) use ($types, $retrieve) {
            $target = $retrieve($item);

            foreach ($types as $class) {
                if ($target instanceof $class) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Keep items whose string value at $path contains $needle.
     *
     * @example whereContains('name', 'ali')
     * @example whereContains('email', '@GMAIL', ignoreCase: true)
     */
    public function whereContains(mixed $path, string $needle, bool $ignoreCase = false, ?string $encoding = null): static
    {
        return $this->whereString($path, $needle, $ignoreCase, $encoding, str_contains(...));
    }

    /**
     * Keep items whose string value at $path starts with $prefix.
     *
     * @example whereStartsWith('name', 'Al')
     * @example whereStartsWith('name', 'al', ignoreCase: true)
     */
    public function whereStartsWith(mixed $path, string $prefix, bool $ignoreCase = false, ?string $encoding = null): static
    {
        return $this->whereString($path, $prefix, $ignoreCase, $encoding, str_starts_with(...));
    }

    /**
     * Keep items whose string value at $path ends with $suffix.
     *
     * @example whereEndsWith('email', '@example.com')
     */
    public function whereEndsWith(mixed $path, string $suffix, bool $ignoreCase = false, ?string $encoding = null): static
    {
        return $this->whereString($path, $suffix, $ignoreCase, $encoding, str_ends_with(...));
    }

    /**
     * Keep items whose string value at $path matches a PCRE pattern.
     *
     * $offset is passed to preg_match() (byte offset where matching starts).
     *
     * @throws InvalidArgumentException when the pattern is invalid
     *
     * @example whereMatches('email', '/^[a-z]+@gmail\.com$/i')
     * @example whereMatches('code', '/^\d+$/', offset: 3)
     */
    public function whereMatches(mixed $path, string $pattern, int $offset = 0): static
    {
        $error = null;
        set_error_handler(static function (int $level, string $message) use (&$error) {
            $error = $message;

            return true;
        });

        try {
            $valid = preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }

        if (!$valid) {
            throw new InvalidArgumentException(sprintf('Invalid pattern [%s]: %s', $pattern, $error ?? preg_last_error_msg()));
        }

        $retrieve = $this->valueRetriever($path);

        return $this->filter(static function ($item) use ($retrieve, $pattern, $offset) {
            $value = $retrieve($item);

            if (!is_string($value) && !$value instanceof Stringable) {
                return false;
            }

            $result = preg_match($pattern, (string) $value, offset: $offset);

            if ($result === false) {
                throw new InvalidArgumentException(sprintf('Pattern [%s] failed: %s.', $pattern, preg_last_error_msg()));
            }

            return $result === 1;
        });
    }

    /**
     * Split into [passing, failing] collections (keys preserved).
     *
     * @example [$admins, $others] = $users->partition('role', 'admin')
     * @example [$even, $odd] = $numbers->partition(fn($n) => $n % 2 === 0)
     */
    public function partition(mixed $key, mixed $operator = null, mixed $value = null): static
    {
        $predicate = $this->operatorForWhere(...func_get_args());
        $passed = [];
        $failed = [];

        foreach ($this->items as $itemKey => $item) {
            if ($predicate($item, $itemKey)) {
                $passed[$itemKey] = $item;
            } else {
                $failed[$itemKey] = $item;
            }
        }

        return $this->newInstance([$this->newInstance($passed), $this->newInstance($failed)]);
    }

    /**
     * First item, or the first passing $callback. $default may be a Closure.
     *
     * @example first()
     * @example first(fn($u) => $u['active'], 'nobody')
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $this->items === []
                ? static::resolveValue($default)
                : $this->items[array_key_first($this->items)];
        }

        $callback = static::adaptCallback($callback);

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return static::resolveValue($default);
    }

    /**
     * Last item, or the last passing $callback. $default may be a Closure.
     *
     * @example make([1, 2, 3])->last()  // 3
     * @example last(fn($item) => $item < 3)  // 2
     * @example last(null, 'none')  // 'none' for an empty collection
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $this->items === []
                ? static::resolveValue($default)
                : $this->items[array_key_last($this->items)];
        }

        $callback = static::adaptCallback($callback);

        foreach (array_reverse($this->items, true) as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return static::resolveValue($default);
    }

    /**
     * First item matching where()-style arguments, or throw.
     *
     * @throws ItemNotFoundException
     *
     * @example firstOrFail()
     * @example firstOrFail('role', 'admin')
     */
    public function firstOrFail(mixed $key = null, mixed $operator = null, mixed $value = null): mixed
    {
        $predicate = func_num_args() === 0 || ($key === null && func_num_args() === 1)
            ? null
            : $this->operatorForWhere(...func_get_args());

        $missing = static::missing();
        $item = $this->first($predicate, $missing);

        if ($item === $missing) {
            throw new ItemNotFoundException;
        }

        return $item;
    }

    /**
     * First item matching where()-style arguments, or null.
     *
     * @example firstWhere('active')
     * @example firstWhere('age', '>=', 18)
     */
    public function firstWhere(mixed $key, mixed $operator = null, mixed $value = null): mixed
    {
        return $this->first($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Key of the first item, or of the first item passing $callback.
     *
     * @example make(['a' => 1, 'b' => 2])->firstKey()  // 'a'
     * @example make(['a' => 1, 'b' => 2])->firstKey(fn($v) => $v > 1)  // 'b'
     */
    public function firstKey(?callable $callback = null): string|int|null
    {
        if ($callback === null) {
            return array_key_first($this->items);
        }

        $callback = static::adaptCallback($callback);

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Key of the last item, or of the last item passing $callback.
     *
     * @example make(['a' => 1, 'b' => 2])->lastKey()  // 'b'
     * @example make(['a' => 1, 'b' => 2])->lastKey(fn($v) => $v < 2)  // 'a'
     */
    public function lastKey(?callable $callback = null): string|int|null
    {
        if ($callback === null) {
            return array_key_last($this->items);
        }

        $callback = static::adaptCallback($callback);

        foreach (array_reverse($this->items, true) as $key => $item) {
            if ($callback($item, $key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Value at $path from the first item where that path exists.
     *
     * @example value('email')
     * @example value('price', 0)
     */
    public function value(string|int|array $path, mixed $default = null): mixed
    {
        $segments = $this->toSegments($path);

        foreach ($this->items as $item) {
            if ($this->dataHas($item, $segments)) {
                return $this->dataGet($item, $segments);
            }
        }

        return static::resolveValue($default);
    }

    /**
     * First item whose value at $path is strictly equal to $value.
     *
     * @example find('id', 42)
     * @example find('user.email', 'a@b.c', default: [])
     */
    public function find(string|int|array $path, mixed $value, mixed $default = null): mixed
    {
        $segments = $this->toSegments($path);

        foreach ($this->items as $item) {
            if ($this->dataHas($item, $segments) && $this->dataGet($item, $segments) === $value) {
                return $item;
            }
        }

        return static::resolveValue($default);
    }

    /**
     * The only item matching the criteria; throws for zero or several.
     *
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     *
     * @example sole()
     * @example sole('id', 42)
     * @example sole(fn($u) => $u['email'] === $email)
     */
    public function sole(mixed $key = null, mixed $operator = null, mixed $value = null): mixed
    {
        $items = $this->matching(func_get_args());
        $count = $items->count();

        if ($count === 0) {
            throw new ItemNotFoundException;
        }

        if ($count > 1) {
            throw new MultipleItemsFoundException($count);
        }

        return $items->first();
    }

    /**
     * Whether exactly one item matches the criteria.
     *
     * @example hasSole('role', 'admin')
     * @example hasSole(fn($user) => $user->isOwner())
     */
    public function hasSole(mixed $key = null, mixed $operator = null, mixed $value = null): bool
    {
        return $this->matching(func_get_args())->count() === 1;
    }

    /**
     * Whether more than one item matches the criteria.
     *
     * @example hasMany('role', 'admin')
     * @example hasMany(fn($user) => $user->isActive())
     */
    public function hasMany(mixed $key = null, mixed $operator = null, mixed $value = null): bool
    {
        return $this->matching(func_get_args())->count() > 1;
    }

    /**
     * Whether exactly one item exists (or matches $callback).
     *
     * @deprecated Use hasSole() - kept for Laravel compatibility.
     */
    public function containsOneItem(?callable $callback = null): bool
    {
        return $this->hasSole($callback);
    }

    /**
     * Whether more than one item exists (or matches $callback).
     *
     * @deprecated Use hasMany() - kept for Laravel compatibility.
     */
    public function containsManyItems(?callable $callback = null): bool
    {
        return $this->hasMany($callback);
    }

    /**
     * Whether the collection contains a value, a passing item, or a matching path.
     *
     * One argument: callable -> any item passes; otherwise loose in_array().
     * Two or three arguments: where()-style condition.
     *
     * @example contains('Alice')
     * @example contains(fn($v) => $v > 10)
     * @example contains('role', 'admin')
     * @example contains('age', '>', 65)
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1 && !$this->useAsCallable($key)) {
            return in_array($key, $this->items);
        }

        $predicate = $this->operatorForWhere(...func_get_args());

        foreach ($this->items as $itemKey => $item) {
            if ($predicate($item, $itemKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strict variant of contains(): ('value'), (callable) or ('path', $value).
     *
     * @example make([1, 2])->containsStrict(1)    // true
     * @example make([1, 2])->containsStrict('1')  // false
     * @example containsStrict('id', 1)
     */
    public function containsStrict(mixed $key, mixed $value = null): bool
    {
        if (func_num_args() === 2) {
            return $this->contains($key, '===', $value);
        }

        if ($this->useAsCallable($key)) {
            return $this->contains($key);
        }

        return in_array($key, $this->items, true);
    }

    /**
     * Whether the collection does not contain the value; the opposite of contains().
     *
     * @example make([1, 2])->doesntContain(3)  // true
     * @example doesntContain('role', 'admin')
     */
    public function doesntContain(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        return !$this->contains(...func_get_args());
    }

    /**
     * Whether the collection does not contain the value, compared strictly.
     *
     * @example make([1, 2])->doesntContainStrict('1')  // true
     */
    public function doesntContainStrict(mixed $key, mixed $value = null): bool
    {
        return !$this->containsStrict(...func_get_args());
    }

    /**
     * Alias for contains().
     *
     * @example make([1, 2])->some(2)  // true
     * @example some(fn($item) => $item > 1)
     */
    public function some(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        return $this->contains(...func_get_args());
    }

    /**
     * Whether every item passes the test. True for an empty collection.
     *
     * @example every(fn($u) => $u['active'])
     * @example every('active')
     * @example every('age', '>=', 18)
     */
    public function every(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        $predicate = func_num_args() === 1
            ? $this->valueRetriever($key)
            : $this->operatorForWhere(...func_get_args());

        foreach ($this->items as $itemKey => $item) {
            if (!$predicate($item, $itemKey)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Key of the first item equal to $value (loose by default) or passing a callable; false if none.
     *
     * @example search('Alice')
     * @example search('1', strict: true)
     * @example search(fn($v, $k) => $v['active'])
     */
    public function search(mixed $value, bool $strict = false): string|int|false
    {
        if (!$this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }

        $value = static::adaptCallback($value);

        foreach ($this->items as $key => $item) {
            if ($value($item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Item right after the first item equal to $value (or passing a callable).
     *
     * @example after(3)
     * @example after(fn($u) => $u['id'] === 2)
     */
    public function after(mixed $value, bool $strict = false): mixed
    {
        $matches = $this->matcher($value, $strict);
        $found = false;

        foreach ($this->items as $key => $item) {
            if ($found) {
                return $item;
            }

            $found = (bool) $matches($item, $key);
        }

        return null;
    }

    /**
     * Item right before the first item equal to $value (or passing a callable).
     *
     * @example make([1, 2, 3])->before(3)  // 2
     * @example make([1, 2, 3])->before(1)  // null
     * @example before(fn($item) => $item > 2)  // 2
     */
    public function before(mixed $value, bool $strict = false): mixed
    {
        $matches = $this->matcher($value, $strict);
        $previous = null;

        foreach ($this->items as $key => $item) {
            if ($matches($item, $key)) {
                return $previous;
            }

            $previous = $item;
        }

        return null;
    }

    /**
     * Items before the first one equal to $value (strict) or passing a callable.
     *
     * @example make([1, 2, 3, 4])->takeUntil(3)  // [1, 2]
     * @example takeUntil(fn($item) => $item >= 3)  // [1, 2]
     */
    public function takeUntil(mixed $value): static
    {
        $predicate = $this->equalityPredicate($value);
        $result = [];

        foreach ($this->items as $key => $item) {
            if ($predicate($item, $key)) {
                break;
            }

            $result[$key] = $item;
        }

        return $this->newInstance($result);
    }

    /**
     * Items while they are equal to $value (strict) or pass a callable.
     *
     * @example make([1, 1, 2, 1])->takeWhile(1)  // [1, 1]
     * @example takeWhile(fn($item) => $item < 3)
     */
    public function takeWhile(mixed $value): static
    {
        $predicate = $this->equalityPredicate($value);

        return $this->takeUntil(static fn ($item, $key) => !$predicate($item, $key));
    }

    /**
     * Items from the first one equal to $value (strict) or passing a callable.
     *
     * @example make([1, 2, 3, 4])->skipUntil(3)  // [2 => 3, 3 => 4]
     * @example skipUntil(fn($item) => $item >= 3)
     */
    public function skipUntil(mixed $value): static
    {
        $predicate = $this->equalityPredicate($value);
        $result = [];
        $found = false;

        foreach ($this->items as $key => $item) {
            if (!$found && !$predicate($item, $key)) {
                continue;
            }

            $found = true;
            $result[$key] = $item;
        }

        return $this->newInstance($result);
    }

    /**
     * Items after the leading run equal to $value (strict) or passing a callable.
     *
     * @example make([1, 1, 2, 1])->skipWhile(1)  // [2 => 2, 3 => 1]
     * @example skipWhile(fn($item) => $item < 2)
     */
    public function skipWhile(mixed $value): static
    {
        $predicate = $this->equalityPredicate($value);

        return $this->skipUntil(static fn ($item, $key) => !$predicate($item, $key));
    }

    /**
     * Items matching sole()-style arguments; no arguments (or null) means all items.
     */
    private function matching(array $arguments): static
    {
        if ($arguments === [] || (count($arguments) === 1 && $arguments[0] === null)) {
            return $this;
        }

        return $this->filter(...$arguments);
    }

    /**
     * Keep (or drop) items matching where()-style arguments.
     *
     * A path condition reads every value in one pass and compares in a plain
     * loop - no per-item callbacks. $strictPair makes ('path', $value) use ===.
     */
    private function filterBy(array $arguments, bool $strictPair, bool $keep): static
    {
        $key = $arguments[0];

        if ($this->useAsCallable($key)) {
            $callback = static::adaptCallback($key);
            $result = [];

            foreach ($this->items as $itemKey => $item) {
                if ((bool) $callback($item, $itemKey) === $keep) {
                    $result[$itemKey] = $item;
                }
            }

            return $this->newInstance($result);
        }

        [$operator, $value] = match (count($arguments)) {
            1 => ['=', true],
            2 => [$strictPair ? '===' : '=', $arguments[1]],
            default => [static::assertOperator($arguments[1]), $arguments[2]],
        };

        $result = [];
        $valueIsObject = is_object($value);

        foreach ($this->retrieveAll($key) as $itemKey => $retrieved) {
            // Objects need the Laravel object/string rules; scalars are compared inline.
            $passes = $valueIsObject || is_object($retrieved)
                ? static::compareValues($retrieved, $operator, $value)
                : match ($operator) {
                    '=', '==' => $retrieved == $value,
                    '===' => $retrieved === $value,
                    '!=', '<>' => $retrieved != $value,
                    '!==' => $retrieved !== $value,
                    '<' => $retrieved < $value,
                    '>' => $retrieved > $value,
                    '<=' => $retrieved <= $value,
                    '>=' => $retrieved >= $value,
                    default => (bool) ($retrieved <=> $value),
                };

            if ($passes === $keep) {
                $result[$itemKey] = $this->items[$itemKey];
            }
        }

        return $this->newInstance($result);
    }

    /**
     * Keep items whose retrieved value passes $test.
     */
    private function keepByValue(mixed $key, callable $test): static
    {
        $result = [];

        foreach ($this->retrieveAll($key) as $itemKey => $value) {
            if ($test($value)) {
                $result[$itemKey] = $this->items[$itemKey];
            }
        }

        return $this->newInstance($result);
    }

    private function matcher(mixed $value, bool $strict): callable
    {
        if ($this->useAsCallable($value)) {
            return static::adaptCallback($value);
        }

        return $strict
            ? static fn ($item) => $item === $value
            : static fn ($item) => $item == $value;
    }

    /**
     * Pass/fail check shared by where-string filters.
     */
    private function whereString(
        mixed $path,
        string $needle,
        bool $ignoreCase,
        ?string $encoding,
        callable $compare,
    ): static {
        $retrieve = $this->valueRetriever($path);

        if ($ignoreCase) {
            $needle = mb_strtolower($needle, $encoding);
        }

        return $this->filter(static function ($item) use ($retrieve, $needle, $ignoreCase, $encoding, $compare) {
            $value = $retrieve($item);

            if (!is_string($value) && !$value instanceof Stringable) {
                return false;
            }

            $value = (string) $value;

            return $compare($ignoreCase ? mb_strtolower($value, $encoding) : $value, $needle);
        });
    }
}

<?php

namespace Chipslays\Collection;

use Chipslays\Arr\Arr;
use Countable;
use ArrayAccess;
use Iterator;
use stdClass;

class Collection implements Countable, ArrayAccess, Iterator
{
    /**
     * @var array
     */
    protected $data = [];

    protected $position = 0;

    /**
     * @param array|stdClass $items
     */
    public function __construct($data = [])
    {
        $this->data = (array) $data;
    }

    /**
     * Get item from collection by using dot notation.
     *
     * @param string $key
     * @param mixed $default
     * @param string $separator
     *
     * @return mixed
     */
    public function get(string $key, $default = null, string $separator = '.')
    {
        return Arr::get($this->data, $key, $default, $separator);
    }

    /**
     * Set/overwrite item in collection using by dot notation key.
     *
     * @param string $key
     * @param mixed $value
     * @param string $separator
     *
     * @return Collection
     */
    public function set(string $key, $value = null, string $separator = '.'): Collection
    {
        Arr::set($this->data, $key, $value, $separator);
        return $this;
    }

    /**
     * Add item to end of items.
     *
     * @param mixed $value
     *
     * @return Collection
     */
    public function push($value): Collection
    {
        $this->data[] = $value;
        return $this;
    }

    /**
     * Replaces elements from passed arrays into the current items recursively.
     *
     * @param array $items
     *
     * @return Collection
     */
    public function replace(array ...$items): Collection
    {
        $this->data = array_replace_recursive($this->data, ...$items);
        return $this;
    }

    /**
     * Replaces elements from passed arrays into the current items.
     *
     * @param array $items
     *
     * @return Collection
     */
    public function replaceRecursive(array ...$items): Collection
    {
        $this->data = array_replace_recursive($this->data, ...$items);
        return $this;
    }

    /**
     * Merge one or more items.
     *
     * @param array $items
     *
     * @return Collection
     */
    public function merge(array ...$items): Collection
    {
        $this->data = array_merge($this->data, ...$items);
        return $this;
    }

    /**
     * Merge one or more items recursively
     *
     * @param array $items
     *
     * @return Collection
     */
    public function mergeRecursive(array ...$items): Collection
    {
        $this->data = array_merge_recursive($this->data, ...$items);
        return $this;
    }

    /**
     * Return first value from collection.
     *
     * @return mixed
     */
    public function first()
    {
        return array_values($this->data)[0] ?? null;
    }

    /**
     * Return last value from collection.
     *
     * @return mixed
     */
    public function last()
    {
        return $this->data !== [] ? end($this->data) : null;
    }

    /**
     * @param int $count
     * @return mixed
     */
    public function limit(int $count)
    {
        return new static(array_chunk($this->data, $count, true)[0] ?? []);
    }

    /**
     * @return mixed
     */
    public function only($keys)
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->data[$key] ?? null;
        }

        return new static($items);
    }

    /**
     * @param string $key
     * @param string $separator
     * @return static
     */
    public function collect(string $key, string $separator = '.')
    {
        return new static($this->get($key, [], $separator));
    }

    /**
     * Check exists item in collection using by dot notation key.
     *
     * @param string $key
     * @param string $separator
     *
     * @return bool
     */
    public function has(string $key, string $separator = '.'): bool
    {
        return Arr::has($this->data, $key, $separator);
    }

    /**
     * Get count of items in collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Clear all items in collection.
     *
     * @return Collection
     */
    public function clear(): Collection
    {
        $this->data = [];
        return $this;
    }

    /**
     * Execute a callback over each item.
     *
     * @param callable $callback
     * @return Collection
     */
    public function each(callable $callback): Collection
    {
        foreach ($this->data as $key => $item) {
            if (call_user_func($callback, $item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Run a map over each of the items.
     *
     * @param callable $callback
     * @return static
     */
    public function map(callable $callback): Collection
    {
        $keys = array_keys($this->data);

        $items = array_map($callback, $this->data, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param callable $callback
     * @return static
     */
    public function mapWithKeys(callable $callback): Collection
    {
        $result = [];

        foreach ($this->data as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Run a filter over each of the items.
     *
     * @param callable|null $callback
     * @return static
     */
    public function filter(callable $callback = null): Collection
    {
        if ($callback) {
            return new static(Arr::where($this->data, $callback));
        }

        return new static(array_filter($this->data));
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return static
     */
    public function where($key, $operator = null, $value = null): Collection
    {
        if (func_num_args() === 1) {
            $value = true;
            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $callback = function ($item) use ($key, $operator, $value) {
            $retrieved = Arr::get($item, $key);

            switch ($operator) {
                case '=':
                    return $retrieved == $value;
                    break;

                case '==':
                    return $retrieved === $value;
                    break;

                case '!=':
                    return $retrieved != $value;
                    break;

                case '!==':
                    return $retrieved !== $value;
                    break;

                case '<':
                    return $retrieved < $value;
                    break;

                case '>':
                    return $retrieved > $value;
                    break;

                case '<=':
                    return $retrieved <= $value;
                    break;

                case '>=':
                    return $retrieved >= $value;
                    break;

                case '<>':
                    return $retrieved <> $value;
                    break;

                default:
                    break;
            }
        };

        return $this->filter($callback);
    }

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @param int $size
     * @return static
     */
    public function chunk($size)
    {
        if ($size <= 0) {
            return new static;
        }

        $chunks = [];

        foreach (array_chunk($this->data, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed
     */
    public function shift()
    {
        return array_shift($this->data);
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static
     */
    public function values()
    {
        return new static(array_values($this->data));
    }

    /**
     * @return static
     */
    public function keys()
    {
        return new static(array_keys($this->data));
    }

    /**
     * Get collection items as array.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->toArray();
    }

    /**
     * Get collection items as array.
     *
     * @return string
     */
    public function toJson($flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->data, $flags);
    }

    /**
     * Get collection items as object.
     *
     * @return object
     */
    public function toObject(): object
    {
        return (object) $this->data;
    }

    /**
     * Get collection items as array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get items as printable string.
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this->data, true);
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value = null)
    {
        return $this->set($key, $value);
    }

    public function __isset($key)
    {
        return $this->has($key);
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Remove items by key name.
     *
     * @param string ...$keys
     * @return static
     */
    public function remove(...$keys)
    {
        $items = $this->data;

        foreach ($keys as $key) {
            foreach ($items as &$value) {
                if (!isset($value[$key])) continue;
                unset($value[$key]);
            }
        }

        return new static($items);
    }

    /**
     * @return static
     */
    public function trim()
    {
        $items = array_filter($this->data);

        return new static($items);
    }

    public function rewind(): void {
        $this->position = 0;
    }

    public function current(): mixed {
        return $this->data[$this->position];
    }

    public function key(): mixed {
        return $this->position;
    }

    public function next(): void {
        ++$this->position;
    }

    public function valid(): bool {
        return isset($this->data[$this->position]);
    }

    /**
     * Return an collection with elements in reverse order.
     *
     * @param bool $preserveKeys
     * @return static
     */
    public function reverse(bool $preserveKeys = false)
    {
        return new static(array_reverse($this->data, $preserveKeys));
    }

    /**
     * Modify collection by given callback.
     *
     * @param callable $callback
     * @return static
     */
    public function callback(callable $callback)
    {
        return new static(call_user_func($callback, $this->data));
    }
}

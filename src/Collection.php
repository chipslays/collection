<?php

namespace Chipslays\Collection;

use Countable;
use ArrayAccess;
use stdClass;
use Chipslays\Arr\Arr;

class Collection implements Countable, ArrayAccess
{
    /**
     * @var array
     */
    protected $items = [];

    /**
     * @param array|stdClass $array 
     */
    public function __construct($items = [])
    {
        $this->items = (array) $items;
    }

    /**
     * Get item from collection by using dot notation.
     *
     * @param string $key
     * @param mixed $default
     * @param string $separator
     * 
     * @return $mixed
     */
    public function get(string $key, $default = null, string $separator = '.')
    {
        return Arr::get($this->items, $key, $default, $separator);
    }

    /**
     * Set/overwrite item in collection using by dot notation key.
     *
     * @param string $key
     * @param mixed $default
     * @param string $separator
     * 
     * @return Collection
     */
    public function set(string $key, $value = null, string $separator = '.')
    {
        Arr::set($this->items, $key, $value, $separator);
        return $this;
    }

    /**
     * Check exists item in collection using by dot notation key.
     *
     * @param string $key
     * @param string $separator
     * 
     * @return boolean
     */
    public function has(string $key, string $separator = '.')
    {
        return Arr::has($this->items, $key, $separator);
    }

    /**
     * Get count of items in collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * Clear all items in collection.
     *
     * @return Collection
     */
    public function clear()
    {
        $this->items = [];
        return $this;
    }

    /**
     * Execute a callback over each item.
     *
     * @param  callable  $callback
     * @return Collection
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if (call_user_func($callback, $item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Run a map over each of the items.
     *
     * @param  callable  $callback
     * @return static
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapWithKeys(callable $callback)
    {
        $result = [];

        foreach ($this->items as $key => $value) {
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
     * @param  callable|null  $callback
     * @return static
     */
    public function filter(callable $callback = null)
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return static
     */
    public function where($key, $operator = null, $value = null)
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
     * Get collection items as array. 
     *
     * @return array
     */
    public function all()
    {
        return $this->toArray();
    }

    /**
     * Get collection items as array. 
     *
     * @return array
     */
    public function toJson($flags = JSON_PRETTY_PRINT)
    {
        return json_encode($this->items, $flags);
    }

    /**
     * Get collection items as object. 
     *
     * @return object
     */
    public function toObject()
    {
        return (object) $this->items;
    }

    public function toArray()
    {
        return $this->items;
    }

    /**
     * Get items as printable string.
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this->items, true);
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

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->items[$offset]) ? $this->items[$offset] : null;
    }
}

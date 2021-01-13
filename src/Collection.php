<?php

namespace Chipslays\Collection;

use Countable;
use Chipslays\Arr\Arr;

class Collection implements Countable
{
    /**
     * @var array
     */
    protected $items = [];

    /**
     * Create collection.
     * 
     * @param array|object $array
     */
    public function __construct($array = [])
    {
        $this->items = (array) $array;
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
     * Get collection items as array. 
     *
     * @return array
     */
    public function toArray()
    {
        return $this->items;
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

    /**
     * Get items as printable string.
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this->items, true);
    }
}
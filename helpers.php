<?php

declare(strict_types=1);

use Collection\Collection;

if (!function_exists('collection')) {
    /**
     * Create a new Collection instance.
     *
     * @see Collection::make()
     *
     * @param  mixed  $items  Array, collection, iterable, object or JSON string
     * @param  string  $wildcard  Wildcard segment token (default '*')
     * @param  string  $delimiter  Path delimiter (default '.')
     */
    function collection(
        mixed $items = [],
        string $wildcard = '*',
        string $delimiter = '.'
    ): Collection {
        return Collection::make($items, $wildcard, $delimiter);
    }
}

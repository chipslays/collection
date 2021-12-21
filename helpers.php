<?php

use Chipslays\Collection\Collection;

if (!function_exists('collection')) {
    /**
     * @param array|stdClass $items
     * @return Collection
     */
    function collection($items): Collection
    {
        return new Collection($items);
    }
}

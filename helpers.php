<?php 

use Chipslays\Collection\Collection;

if (! function_exists('collection')) {
    /**
     * @param array $array
     * @return Collection
     */
    function collection($array) : Collection {
        return new Collection($array);
    }
}
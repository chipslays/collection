<?php

use Chipslays\Collection\Collection;

require __DIR__ . '/vendor/autoload.php';

// $c = new Collection([
//     ['a' => '123'],
//     ['b' => '123'],
//     ['c' => '123'],
// ]);

// print_r($c->all());
// print_r($c->remove('a', 'c')->trim()->values()->all());


// print_r($c->keys()->first());
// die;


print_r($result = (new Collection([
    ['a' => '123'],
    ['b' => '123'],
    ['c' => '123'],
]))->remove('a', 'c')->trim()->values()->first());
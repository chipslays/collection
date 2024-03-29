<?php

use PHPUnit\Framework\TestCase;

use Chipslays\Collection\Collection;

final class CollectionTest extends TestCase
{
    public function testCollectionGet()
    {
        $collection = new Collection([
            'user' => [
                'name' => 'chipslays',
            ],
        ]);

        $name = $collection->get('user.name');
        $email = $collection->get('user.email', 'default@email.com');

        $this->assertEquals('chipslays', $name);
        $this->assertEquals('default@email.com', $email);
    }

    public function testCollectionSet()
    {
        $collection = new Collection([
            'user' => [
                'name' => 'chipslays',
            ],
        ]);

        $name = $collection
            ->set('user.name', 'john doe')
            ->get('user.name');

        $email = $collection
            ->set('user.email', 'john.doe@email.com')
            ->get('user.email');

        $this->assertEquals('john doe', $name);
        $this->assertEquals('john.doe@email.com', $email);
    }

    public function testCollectionHas()
    {
        $collection = new Collection([
            'user' => [
                'name' => 'chipslays',
            ],
        ]);

        $hasName = $collection->has('user.name');
        $hasEmail = $collection->has('user.email');

        $this->assertTrue($hasName);
        $this->assertFalse($hasEmail);
    }

    public function testCollectionCount()
    {
        $collection = new Collection(range(1, 10));

        $this->assertEquals(10, $collection->count());
        $this->assertEquals(10, count($collection));
    }

    public function testCollectionToArray()
    {
        $collection = new Collection(range(1, 10));

        $this->assertEquals(range(1, 10), $collection->toArray());
    }

    public function testCollectionToObject()
    {
        $collection = new Collection(range(1, 10));

        $this->assertEquals((object) range(1, 10), $collection->toObject());
    }

    public function testCollectionToString()
    {
        $collection = new Collection(range(1, 10));

        $this->assertEquals(print_r(range(1, 10), true), (string) $collection);
    }

    public function testCollectionWhere()
    {
        $collection = new Collection([
            [
                'user' => 'name1',
            ],
            [
                'user' => 'name2',
            ],
            [
                'user' => 'name3',
            ],
        ]);

        $this->assertEquals([1 => ['user' => 'name2']], $collection->where('user', 'name2')->all());
    }

    public function testCollectionMap()
    {
        $result = (new Collection(range(1, 5)))->map(function ($item) {
            return ++$item;
        })->all();

        $this->assertEquals(range(2, 6), $result);
    }

    public function testCollectionMapWithKeys()
    {
        $result = (new Collection(range(1, 5)))->mapWithKeys(function ($item) {
            return [$item => $item];
        })->all();

        $this->assertEquals([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5], $result);
    }

    public function testCollectionFilter()
    {
        $result = (new Collection(range(1, 5)))->filter(function ($value, $key) {
            return $value > 2;
        })->values()->all();

        $this->assertEquals([3, 4, 5], $result);
    }

    public function testCollectionСollectMethod()
    {
        $result = (new Collection(['data' => range(1,5)]))->collect('data')->map(function ($item) {
            return 1;
        })->toArray();

        $this->assertEquals([1, 1, 1, 1, 1], $result);
    }

    public function testCollectionOnly()
    {
        $result = (new Collection(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]))->only(['a', 'd', 'c'])->all();

        $this->assertEquals(['a' => 1, 'd' => 4, 'c' => 3], $result);
    }

    public function testCollectionLimit()
    {
        $result = (new Collection(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]))->limit(2)->all();

        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function testCollectionReplaceRecursive()
    {
        $result = (new Collection([
            'names' => [
                'first' => 'test',
            ]
        ]))->replaceRecursive([
            'names' => [
                'first' => 'replaced',
            ]
        ])->all();

        $this->assertEquals([
            'names' => [
                'first' => 'replaced',
            ]
        ], $result);
    }

    public function testCollectionRemove()
    {
        $result = (new Collection([
            ['a' => '123'],
            ['b' => '123'],
            ['c' => '123'],
        ]))->remove('a', 'c')->trim()->first();

        $this->assertEquals(['b' => '123'], $result);
    }

    public function testCollectionTrim()
    {
        $result = (new Collection([
            [],
            ['b' => '123'],
            null,
            false,
        ]))->trim()->values()->all();

        $this->assertEquals([['b' => '123']], $result);
    }

    public function testCollectionReverse()
    {
        $result = (new Collection([1, 2, 3]))->reverse()->all();
        $this->assertEquals([3, 2, 1], $result);
    }

    public function testCollectionCallback()
    {
        $result = (new Collection([1, 3, 5, 4, 2]))->callback(function ($items) {
            sort($items);
            return $items;
        })->all();
        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }
}
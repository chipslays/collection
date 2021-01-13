<?php

use PHPUnit\Framework\TestCase;

use Chipslays\Collection\Collection;

final class ArrTest extends TestCase
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
}
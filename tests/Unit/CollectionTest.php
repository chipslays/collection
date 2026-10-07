<?php

declare(strict_types=1);

use Collection\Collection;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function users(): array
{
    return [
        ['id' => 1, 'name' => 'Alice', 'role' => 'admin',  'active' => true,
            'emails' => [['address' => 'alice@x.com', 'verified' => true]]],
        ['id' => 2, 'name' => 'Bob',   'role' => 'editor', 'active' => false,
            'emails' => [['address' => 'bob@x.com', 'verified' => false], ['address' => 'bob2@x.com', 'verified' => true]]],
        ['id' => 3, 'name' => 'Carol', 'role' => 'viewer', 'active' => true,
            'emails' => [['address' => 'carol@x.com', 'verified' => true]]],
    ];
}

// ---------------------------------------------------------------------------
// static make() / constructor
// ---------------------------------------------------------------------------

test('make() returns a Collection instance', function () {
    expect(Collection::make())->toBeInstanceOf(Collection::class);
});

test('make() accepts initial items', function () {
    $c = Collection::make(['x' => 1]);
    expect($c->get('x'))->toBe(1);
});

test('constructor default is empty collection', function () {
    $c = new Collection;
    expect($c->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// get()
// ---------------------------------------------------------------------------

test('get() with null path returns all items', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->get())->toBe(['a' => 1]);
    expect($c->get(null))->toBe(['a' => 1]);
});

test('get() with empty string reads the empty key', function () {
    expect(Collection::make(['a' => 1])->get('', 'none'))->toBe('none');
    expect(Collection::make(['' => 'x'])->get(''))->toBe('x');
});

test('get() plain path returns scalar value', function () {
    $c = Collection::make(['app' => ['name' => 'Collection', 'debug' => false]]);
    expect($c->get('app.name'))->toBe('Collection');
});

test('get() returns false value (not confused with missing)', function () {
    $c = Collection::make(['app' => ['debug' => false]]);
    expect($c->get('app.debug'))->toBeFalse();
});

test('get() returns null value', function () {
    $c = Collection::make(['key' => null]);
    expect($c->get('key'))->toBeNull();
});

test('get() missing path returns default null', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->get('missing'))->toBeNull();
});

test('get() missing path returns provided default', function () {
    $c = Collection::make([]);
    expect($c->get('missing.key', 'fallback'))->toBe('fallback');
});

test('get() deeply nested path', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.0.name'))->toBe('Alice');
});

test('get() wildcard returns values keyed by parent index', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.*.name'))->toBe([0 => 'Alice', 1 => 'Bob', 2 => 'Carol']);
});

test('get() wildcard on empty array returns default', function () {
    // wildcard over empty array with remaining segments -> sentinel -> default returned
    $c = Collection::make(['users' => []]);
    expect($c->get('users.*.name'))->toBeNull();
    expect($c->get('users.*.name', []))->toBe([]);
});

test('get() nested wildcard preserves parent keys', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.*.emails.*.address'))->toBe([
        0 => [0 => 'alice@x.com'],
        1 => [0 => 'bob@x.com', 1 => 'bob2@x.com'],
        2 => [0 => 'carol@x.com'],
    ]);
});

test('get() nested wildcard boolean values', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.*.emails.*.verified'))->toBe([
        0 => [0 => true],
        1 => [0 => false, 1 => true],
        2 => [0 => true],
    ]);
});

test('get() wildcard on missing key returns default', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.*.missing', 'x'))->toBe('x');
});

// ---------------------------------------------------------------------------
// get() - wildcard key extraction *[key]
// ---------------------------------------------------------------------------

test('get() *[key] uses field value as result key instead of index', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.*[id].name'))->toBe([1 => 'Alice', 2 => 'Bob', 3 => 'Carol']);
});

test('get() *[key] with nested wildcard', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.*[id].emails.*.address'))->toBe([
        1 => [0 => 'alice@x.com'],
        2 => [0 => 'bob@x.com', 1 => 'bob2@x.com'],
        3 => [0 => 'carol@x.com'],
    ]);
});

test('get() *[key] falls back to index when key field is missing', function () {
    $items = [
        ['id' => 10, 'name' => 'Alice'],
        ['name' => 'Bob'],       // no id
        ['id' => 30, 'name' => 'Carol'],
    ];
    $c = Collection::make(['users' => $items]);
    // item without 'id' falls back to its numeric index (1)
    expect($c->get('users.*[id].name'))->toBe([10 => 'Alice', 1 => 'Bob', 30 => 'Carol']);
});

test('get() *[key] on boolean values', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->get('users.*[id].active'))->toBe([1 => true, 2 => false, 3 => true]);
});

// ---------------------------------------------------------------------------
// set()
// ---------------------------------------------------------------------------

test('set() creates a new key', function () {
    $c = Collection::make(['a' => ['b' => 1]]);
    $c->set('a.c', 99);
    expect($c->get('a.c'))->toBe(99);
});

test('set() overwrites existing key', function () {
    $c = Collection::make(['a' => ['b' => 1]]);
    $c->set('a.b', 42);
    expect($c->get('a.b'))->toBe(42);
});

test('set() creates intermediate arrays', function () {
    $c = Collection::make([]);
    $c->set('x.y.z', 'deep');
    expect($c->get('x.y.z'))->toBe('deep');
});

test('set() replaces non-array intermediate with array', function () {
    $c = Collection::make(['a' => 'scalar']);
    $c->set('a.b', 1);
    expect($c->get('a.b'))->toBe(1);
});

test('set() wildcard updates every matching node', function () {
    $c = Collection::make(['users' => users()]);
    $c->set('users.*.active', true);
    expect($c->get('users.*.active'))->toBe([0 => true, 1 => true, 2 => true]);
});

test('set() returns static for chaining', function () {
    $c = Collection::make();
    expect($c->set('a', 1))->toBe($c);
});

// ---------------------------------------------------------------------------
// has()
// ---------------------------------------------------------------------------

test('has() returns true for existing non-null value', function () {
    $c = Collection::make(['app' => ['name' => 'Test']]);
    expect($c->has('app.name'))->toBeTrue();
});

test('has() returns true for null value (key exists, like Laravel)', function () {
    $c = Collection::make(['key' => null]);
    expect($c->has('key'))->toBeTrue();
    expect(isset($c['key']))->toBeFalse();
});

test('has() returns true for false value (only null means absent)', function () {
    // has() checks non-null - false !== null, so key is considered present
    $c = Collection::make(['app' => ['debug' => false]]);
    expect($c->has('app.debug'))->toBeTrue();
});

test('has() returns false for missing path', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->has('missing'))->toBeFalse();
});

test('has() wildcard returns true when at least one match exists', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->has('users.*.name'))->toBeTrue();
});

test('has() wildcard returns false when no matches', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->has('users.*.nonexistent'))->toBeFalse();
});

test('has() nested wildcard', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->has('users.*.emails.*.verified'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// hasKey()
// ---------------------------------------------------------------------------

test('hasKey() returns true for existing key with null value', function () {
    $c = Collection::make(['key' => null]);
    expect($c->hasKey('key'))->toBeTrue();
});

test('hasKey() returns true for existing key with false value', function () {
    $c = Collection::make(['app' => ['debug' => false]]);
    expect($c->hasKey('app.debug'))->toBeTrue();
});

test('hasKey() returns false for missing key', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->hasKey('missing'))->toBeFalse();
});

test('hasKey() wildcard returns true when at least one key exists', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->hasKey('users.*.name'))->toBeTrue();
});

test('hasKey() wildcard returns false when no key exists', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->hasKey('users.*.nonexistent'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// remove()
// ---------------------------------------------------------------------------

test('remove() deletes an existing key', function () {
    $c = Collection::make(['a' => ['b' => 1, 'c' => 2]]);
    $c->remove('a.b');
    expect($c->get('a.b', 'gone'))->toBe('gone');
});

test('remove() sibling keys stay intact', function () {
    $c = Collection::make(['a' => ['b' => 1, 'c' => 2]]);
    $c->remove('a.b');
    expect($c->get('a.c'))->toBe(2);
});

test('remove() non-existent key does not throw', function () {
    $c = Collection::make(['a' => 1]);
    $c->remove('a.b.c');
    expect($c->all())->toBe(['a' => 1]);
});

test('remove() wildcard removes matching key in every parent', function () {
    $c = Collection::make(['users' => users()]);
    $c->remove('users.*.role');
    expect($c->hasKey('users.0.role'))->toBeFalse();
    expect($c->get('users.0.name'))->toBe('Alice');
});

test('remove() returns static for chaining', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->remove('a'))->toBe($c);
});

// ---------------------------------------------------------------------------
// count()
// ---------------------------------------------------------------------------

test('count() without path counts top-level items', function () {
    $c = Collection::make(['app' => [], 'users' => users()]);
    expect($c->count())->toBe(2);
});

test('count() with path counts array at path', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->count('users'))->toBe(3);
});

test('count() nested path', function () {
    $c = Collection::make(['users' => users()]);
    expect($c->count('users.1.emails'))->toBe(2);
});

test('count() missing path returns 0', function () {
    $c = Collection::make([]);
    expect($c->count('nonexistent'))->toBe(0);
});

test('count() scalar value returns 1', function () {
    $c = Collection::make(['app' => ['name' => 'Test']]);
    expect($c->count('app.name'))->toBe(1);
});

test('count() satisfies Countable interface', function () {
    $c = Collection::make([1, 2, 3]);
    expect(count($c))->toBe(3);
});

// ---------------------------------------------------------------------------
// all() / clear()
// ---------------------------------------------------------------------------

test('all() returns items array', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->all())->toBe([1, 2, 3]);
});

test('clear() empties the collection', function () {
    $c = Collection::make([1, 2, 3]);
    $c->clear();
    expect($c->all())->toBe([]);
});

test('clear() returns static for chaining', function () {
    $c = Collection::make([1]);
    expect($c->clear())->toBe($c);
});

// ---------------------------------------------------------------------------
// isEmpty() / isNotEmpty()
// ---------------------------------------------------------------------------

test('isEmpty() returns true for empty collection', function () {
    $c = Collection::make();
    expect($c->isEmpty())->toBeTrue();
});

test('isEmpty() returns false for non-empty collection', function () {
    $c = Collection::make([1]);
    expect($c->isEmpty())->toBeFalse();
});

test('isNotEmpty() returns true for non-empty collection', function () {
    $c = Collection::make([1]);
    expect($c->isNotEmpty())->toBeTrue();
});

test('isNotEmpty() returns false for empty collection', function () {
    $c = Collection::make();
    expect($c->isNotEmpty())->toBeFalse();
});

// ---------------------------------------------------------------------------
// push()
// ---------------------------------------------------------------------------

test('push() appends value to end', function () {
    $c = Collection::make([1, 2]);
    $c->push(3);
    expect($c->all())->toBe([1, 2, 3]);
});

test('push() returns static for chaining', function () {
    $c = Collection::make();
    expect($c->push(1))->toBe($c);
});

// ---------------------------------------------------------------------------
// merge()
// ---------------------------------------------------------------------------

test('merge() merges array into collection', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->merge(['b' => 2])->all())->toBe(['a' => 1, 'b' => 2]);
});

test('merge() merges another Collection', function () {
    $c1 = Collection::make(['a' => 1]);
    $c2 = Collection::make(['b' => 2]);
    expect($c1->merge($c2)->all())->toBe(['a' => 1, 'b' => 2]);
});

test('merge() overwrites duplicate keys', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->merge(['a' => 99])->get('a'))->toBe(99);
});

test('merge() returns a new collection and does not mutate', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->merge(['b' => 2]))->not->toBe($c);
    expect($c->all())->toBe(['a' => 1]);
});

// ---------------------------------------------------------------------------
// filter()
// ---------------------------------------------------------------------------

test('filter() returns new collection with matching items', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $result = $c->filter(fn ($n) => $n % 2 === 0);
    expect($result->all())->toBe([1 => 2, 3 => 4]);
});

test('filter() returns new Collection instance', function () {
    $c = Collection::make([1, 2]);
    expect($c->filter(fn () => true))->toBeInstanceOf(Collection::class);
});

test('filter() does not mutate original', function () {
    $c = Collection::make([1, 2, 3]);
    $c->filter(fn ($n) => $n > 1);
    expect($c->all())->toBe([1, 2, 3]);
});

// ---------------------------------------------------------------------------
// map()
// ---------------------------------------------------------------------------

test('map() transforms every item', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->map(fn ($n) => $n * 2)->all())->toBe([2, 4, 6]);
});

test('map() returns new Collection instance', function () {
    $c = Collection::make([1]);
    expect($c->map(fn ($n) => $n))->toBeInstanceOf(Collection::class);
});

test('map() does not mutate original', function () {
    $c = Collection::make([1, 2]);
    $c->map(fn ($n) => $n * 10);
    expect($c->all())->toBe([1, 2]);
});

// ---------------------------------------------------------------------------
// sort() / ksort()
// ---------------------------------------------------------------------------

test('sort() sorts values naturally', function () {
    $c = Collection::make([3, 1, 4, 1, 5]);
    expect($c->sort()->values()->all())->toBe([1, 1, 3, 4, 5]);
    expect($c->sort()->keys()->all())->toBe([1, 3, 0, 2, 4]);
});

test('sort() uses custom comparator', function () {
    $c = Collection::make([1, 3, 2]);
    expect($c->sort(fn ($a, $b) => $b <=> $a)->all())->toBe([1 => 3, 2 => 2, 0 => 1]);
});

test('sort() accepts native sort flags', function () {
    $c = Collection::make(['img12', 'img10', 'IMG2']);
    expect($c->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all())->toBe(['IMG2', 'img10', 'img12']);
});

test('sort() returns new Collection instance', function () {
    $c = Collection::make([2, 1]);
    expect($c->sort())->toBeInstanceOf(Collection::class);
});

test('ksort() sorts keys naturally', function () {
    $c = Collection::make(['b' => 2, 'a' => 1]);
    expect($c->ksort()->all())->toBe(['a' => 1, 'b' => 2]);
});

test('ksort() uses custom comparator', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    expect($c->ksort(fn ($a, $b) => $b <=> $a)->all())->toBe(['b' => 2, 'a' => 1]);
});

// ---------------------------------------------------------------------------
// first() / last()
// ---------------------------------------------------------------------------

test('first() returns first item', function () {
    $c = Collection::make([10, 20, 30]);
    expect($c->first())->toBe(10);
});

test('first() returns default for empty collection', function () {
    $c = Collection::make();
    expect($c->first(null, 'default'))->toBe('default');
});

test('first() with callback returns first matching item', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->first(fn ($n) => $n > 2))->toBe(3);
});

test('first() with callback returns default when no match', function () {
    $c = Collection::make([1, 2]);
    expect($c->first(fn ($n) => $n > 10, 'none'))->toBe('none');
});

test('last() returns last item', function () {
    $c = Collection::make([10, 20, 30]);
    expect($c->last())->toBe(30);
});

test('last() returns default for empty collection', function () {
    $c = Collection::make();
    expect($c->last(null, 'default'))->toBe('default');
});

// ---------------------------------------------------------------------------
// pluck()
// ---------------------------------------------------------------------------

test('pluck() extracts values by path', function () {
    $c = Collection::make(users());
    expect($c->pluck('name')->all())->toBe([0 => 'Alice', 1 => 'Bob', 2 => 'Carol']);
});

test('pluck() extracts nested values', function () {
    $c = Collection::make(users());
    expect($c->pluck('emails.0.address')->all())->toBe([
        0 => 'alice@x.com',
        1 => 'bob@x.com',
        2 => 'carol@x.com',
    ]);
});

test('pluck() yields null for non-array items', function () {
    $c = Collection::make([['name' => 'Alice'], 'scalar', ['name' => 'Bob']]);
    expect($c->pluck('name')->all())->toBe(['Alice', null, 'Bob']);
});

test('pluck() yields null where path is missing', function () {
    $c = Collection::make([['name' => 'Alice'], ['age' => 30]]);
    expect($c->pluck('name')->all())->toBe(['Alice', null]);
});

test('pluck() reads objects and nested collections', function () {
    $c = Collection::make([
        (object) ['user' => ['name' => 'Alice']],
        ['user' => Collection::make(['name' => 'Bob'])],
    ]);
    expect($c->pluck('user.name')->all())->toBe(['Alice', 'Bob']);
});

// ---------------------------------------------------------------------------
// flatten()
// ---------------------------------------------------------------------------

test('flatten() collapses nested arrays to a single list', function () {
    $c = Collection::make([[1, [2, 3]], [4]]);
    expect($c->flatten()->all())->toBe([1, 2, 3, 4]);
});

test('flatten() with depth=1 flattens only one level', function () {
    $c = Collection::make([[1, [2, 3]], [4]]);
    expect($c->flatten(1)->all())->toBe([1, [2, 3], 4]);
});

test('flatten() returns new Collection instance', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->flatten())->toBeInstanceOf(Collection::class);
});

// ---------------------------------------------------------------------------
// toJson() / fromJson()
// ---------------------------------------------------------------------------

test('toJson() encodes items to JSON', function () {
    $c = Collection::make(['key' => 'value', 'nested' => ['deep' => true]]);
    expect($c->toJson())->toBe('{"key":"value","nested":{"deep":true}}');
});

test('fromJson() creates Collection from JSON string', function () {
    $json = '{"key":"value","nested":{"deep":true}}';
    $c = Collection::fromJson($json);
    expect($c->get('nested.deep'))->toBeTrue();
    expect($c->get('key'))->toBe('value');
});

test('fromJson() round-trip preserves data', function () {
    $json = '{"key":"value","nested":{"deep":true}}';
    expect(Collection::fromJson($json)->toJson())->toBe($json);
});

test('fromJson() accepts custom wildcard and delimiter', function () {
    $json = '{"a":{"b":1}}';
    $c = Collection::fromJson($json, wildcard: '?', delimiter: '/');
    expect($c->get('a/b'))->toBe(1);
});

// ---------------------------------------------------------------------------
// ArrayAccess
// ---------------------------------------------------------------------------

test('offsetGet returns value at dot-notation path', function () {
    $c = Collection::make(['user' => ['name' => 'Alice']]);
    expect($c['user.name'])->toBe('Alice');
});

test('offsetExists returns true for existing path', function () {
    $c = Collection::make(['user' => ['name' => 'Alice']]);
    expect(isset($c['user.name']))->toBeTrue();
});

test('offsetExists returns false for missing path', function () {
    $c = Collection::make(['user' => ['name' => 'Alice']]);
    expect(isset($c['user.missing']))->toBeFalse();
});

test('offsetSet sets value at dot-notation path', function () {
    $c = Collection::make(['user' => ['name' => 'Alice']]);
    $c['user.age'] = 30;
    expect($c->get('user.age'))->toBe(30);
});

test('offsetSet with null offset appends value', function () {
    $c = Collection::make([1, 2]);
    $c[] = 3;
    expect(in_array(3, $c->all()))->toBeTrue();
});

test('offsetUnset removes value at dot-notation path', function () {
    $c = Collection::make(['user' => ['name' => 'Alice', 'age' => 30]]);
    unset($c['user.age']);
    expect($c->has('user.age'))->toBeFalse();
    expect($c->get('user.name'))->toBe('Alice');
});

// ---------------------------------------------------------------------------
// IteratorAggregate
// ---------------------------------------------------------------------------

test('collection is iterable via foreach', function () {
    $items = ['a' => 1, 'b' => 2, 'c' => 3];
    $c = Collection::make($items);
    $out = [];
    foreach ($c as $k => $v) {
        $out[$k] = $v;
    }
    expect($out)->toBe($items);
});

// ---------------------------------------------------------------------------
// Custom wildcard and delimiter
// ---------------------------------------------------------------------------

test('custom delimiter works for get()', function () {
    $c = Collection::make(['user' => ['name' => 'Alice']], wildcard: '*', delimiter: '/');
    expect($c->get('user/name'))->toBe('Alice');
});

test('custom wildcard works for get()', function () {
    $c = Collection::make(['users' => users()], wildcard: '?', delimiter: '.');
    expect($c->get('users.?.name'))->toBe([0 => 'Alice', 1 => 'Bob', 2 => 'Carol']);
});

// ---------------------------------------------------------------------------
// collection() helper function
// ---------------------------------------------------------------------------

test('collection() helper creates Collection instance', function () {
    $c = collection(['x' => 1]);
    expect($c)->toBeInstanceOf(Collection::class);
    expect($c->get('x'))->toBe(1);
});

test('collection() helper accepts custom wildcard and delimiter', function () {
    $c = collection(['a' => ['b' => 1]], '?', '/');
    expect($c->get('a/b'))->toBe(1);
});

// ---------------------------------------------------------------------------
// wrap()
// ---------------------------------------------------------------------------

test('wrap() copies a Collection, keeping wildcard and delimiter', function () {
    $c = new Collection(['a' => ['b' => 1]], '#', '/');
    $wrapped = Collection::wrap($c);
    expect($wrapped)->not->toBe($c);
    expect($wrapped->get('a/b'))->toBe(1);
});

test('wrap() turns null into an empty collection', function () {
    expect(Collection::wrap(null)->all())->toBe([]);
});

test('wrap() wraps array into Collection', function () {
    $c = Collection::wrap([1, 2, 3]);
    expect($c->all())->toBe([1, 2, 3]);
});

test('wrap() wraps scalar into single-element Collection', function () {
    $c = Collection::wrap('hello');
    expect($c->all())->toBe(['hello']);
});

// ---------------------------------------------------------------------------
// keys()
// ---------------------------------------------------------------------------

test('keys() returns top-level keys', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    expect($c->keys()->all())->toBe(['a', 'b', 'c']);
});

test('keys() returns keys at a dot-notation path', function () {
    $c = Collection::make(['user' => ['id' => 1, 'name' => 'Alice', 'age' => 30]]);
    expect($c->keys('user')->all())->toBe(['id', 'name', 'age']);
});

test('keys() returns empty array for non-array path', function () {
    $c = Collection::make(['name' => 'Alice']);
    expect($c->keys('name')->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// contains()
// ---------------------------------------------------------------------------

test('contains() finds an existing value (strict)', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->contains(2))->toBeTrue();
});

test('contains() returns false for missing value', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->contains(99))->toBeFalse();
});

test('contains() uses loose comparison, containsStrict() strict', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->contains('1'))->toBeTrue();
    expect($c->containsStrict('1'))->toBeFalse();
});

test('contains() treats strings as values, never as callables', function () {
    expect(Collection::make(['trim', 'x'])->contains('trim'))->toBeTrue();
    expect(Collection::make(['x'])->contains('strlen'))->toBeFalse();
});

test('contains() with callback returns true when match found', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->contains(fn ($v) => $v > 3))->toBeTrue();
});

test('contains() with callback returns false when no match', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->contains(fn ($v) => $v > 10))->toBeFalse();
});

// ---------------------------------------------------------------------------
// search()
// ---------------------------------------------------------------------------

test('search() returns key of matching value', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    expect($c->search(2))->toBe('b');
});

test('search() returns false when value not found', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->search(99))->toBeFalse();
});

test('search() with callback returns key of first matching item', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->search(fn ($v) => $v > 2))->toBe(2);
});

// ---------------------------------------------------------------------------
// sum() / avg() / min() / max()
// ---------------------------------------------------------------------------

test('sum() sums all top-level scalars', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->sum())->toBe(10);
});

test('sum() sums plucked path values', function () {
    $c = Collection::make([['price' => 10], ['price' => 20], ['price' => 30]]);
    expect($c->sum('price'))->toBe(60);
});

test('avg() returns average', function () {
    expect(Collection::make([2, 4, 6])->avg())->toBe(4);
    expect(Collection::make([1, 2])->avg())->toBe(1.5);
});

test('avg() ignores null values', function () {
    expect(Collection::make([['p' => 2], ['p' => null], ['x' => 1]])->avg('p'))->toBe(2);
});

test('sum() with a path named like a PHP function', function () {
    $c = Collection::make([['count' => 2], ['count' => 3]]);
    expect($c->sum('count'))->toBe(5);
    expect($c->max('count'))->toBe(3);
});

test('avg() returns null for empty collection', function () {
    $c = Collection::make();
    expect($c->avg())->toBeNull();
});

test('avg() with path', function () {
    $c = Collection::make([['score' => 10], ['score' => 20]]);
    expect($c->avg('score'))->toBe(15);
});

test('min() returns minimum value', function () {
    $c = Collection::make([3, 1, 4, 1, 5]);
    expect($c->min())->toBe(1);
});

test('min() returns null for empty collection', function () {
    $c = Collection::make();
    expect($c->min())->toBeNull();
});

test('min() with path', function () {
    $c = Collection::make([['age' => 30], ['age' => 20], ['age' => 25]]);
    expect($c->min('age'))->toBe(20);
});

test('max() returns maximum value', function () {
    $c = Collection::make([3, 1, 4, 1, 5]);
    expect($c->max())->toBe(5);
});

test('max() returns null for empty collection', function () {
    $c = Collection::make();
    expect($c->max())->toBeNull();
});

test('max() with path', function () {
    $c = Collection::make([['age' => 30], ['age' => 20], ['age' => 25]]);
    expect($c->max('age'))->toBe(30);
});

// ---------------------------------------------------------------------------
// only() / except()
// ---------------------------------------------------------------------------

test('only() keeps specified top-level keys', function () {
    $c = Collection::make(['id' => 1, 'name' => 'Alice', 'password' => 'secret']);
    expect($c->only(['id', 'name'])->all())->toBe(['id' => 1, 'name' => 'Alice']);
});

test('only() returns new instance', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->only(['a']))->not->toBe($c);
});

test('except() excludes specified top-level keys', function () {
    $c = Collection::make(['id' => 1, 'name' => 'Alice', 'password' => 'secret']);
    expect($c->except(['password'])->all())->toBe(['id' => 1, 'name' => 'Alice']);
});

test('except() returns new instance', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    expect($c->except(['b']))->not->toBe($c);
});

// ---------------------------------------------------------------------------
// values()
// ---------------------------------------------------------------------------

test('values() re-indexes to sequential integers', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    expect($c->values()->all())->toBe([1, 2, 3]);
});

test('values() returns new instance', function () {
    $c = Collection::make([1, 2]);
    expect($c->values())->not->toBe($c);
});

// ---------------------------------------------------------------------------
// reverse()
// ---------------------------------------------------------------------------

test('reverse() reverses item order', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->reverse()->all())->toBe([2 => 3, 1 => 2, 0 => 1]);
    expect($c->reverse(preserveKeys: false)->all())->toBe([3, 2, 1]);
});

test('reverse() with preserveKeys=true keeps original keys', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    expect($c->reverse(true)->all())->toBe(['c' => 3, 'b' => 2, 'a' => 1]);
});

test('reverse() returns new instance', function () {
    $c = Collection::make([1, 2]);
    expect($c->reverse())->not->toBe($c);
});

// ---------------------------------------------------------------------------
// unique()
// ---------------------------------------------------------------------------

test('unique() removes duplicate scalars', function () {
    $c = Collection::make([1, 2, 2, 3, 1]);
    expect($c->unique()->all())->toBe([0 => 1, 1 => 2, 3 => 3]);
});

test('unique() is loose by default, strict on request', function () {
    expect(Collection::make([1, '1'])->unique()->count())->toBe(1);
    expect(Collection::make([['x' => 1], ['x' => '1']])->unique('x')->count())->toBe(1);
    expect(Collection::make([['x' => 1], ['x' => '1']])->unique('x', strict: true)->count())->toBe(2);
});

test('unique() with path deduplicates by nested value', function () {
    $c = Collection::make([
        ['email' => 'a@x.com', 'name' => 'Alice'],
        ['email' => 'b@x.com', 'name' => 'Bob'],
        ['email' => 'a@x.com', 'name' => 'Alice2'],
    ]);
    expect($c->unique('email')->all())->toHaveCount(2);
});

test('unique() with callback', function () {
    $c = Collection::make([1, -1, 2, -2, 3]);
    $result = $c->unique(fn ($v) => abs($v));
    expect($result->all())->toHaveCount(3);
});

// ---------------------------------------------------------------------------
// slice()
// ---------------------------------------------------------------------------

test('slice() returns portion from offset', function () {
    $c = Collection::make([10, 20, 30, 40, 50]);
    expect($c->slice(1, 3)->all())->toBe([1 => 20, 2 => 30, 3 => 40]);
    expect($c->slice(1, 3, preserveKeys: false)->all())->toBe([20, 30, 40]);
});

test('slice() with negative offset takes from end', function () {
    $c = Collection::make([10, 20, 30, 40, 50]);
    expect($c->slice(-2)->values()->all())->toBe([40, 50]);
});

test('slice() returns new instance', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->slice(0))->not->toBe($c);
});

// ---------------------------------------------------------------------------
// chunk()
// ---------------------------------------------------------------------------

test('chunk() splits into equal parts', function () {
    $c = Collection::make([1, 2, 3, 4, 5, 6]);
    $chunks = $c->chunk(2);
    expect($chunks->count())->toBe(3);
    expect($chunks->first()->all())->toBe([1, 2]);
});

test('chunk() last chunk can be smaller', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $chunks = $c->chunk(2);
    expect($chunks->count())->toBe(3);
    expect($chunks->last()->all())->toBe([4 => 5]);
    expect($c->chunk(2, preserveKeys: false)->last()->all())->toBe([5]);
});

test('chunk() returns empty collection for size <= 0', function () {
    $c = Collection::make([1, 2]);
    expect($c->chunk(0)->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// join()
// ---------------------------------------------------------------------------

test('join() concatenates with glue', function () {
    $c = Collection::make(['Alice', 'Bob', 'Carol']);
    expect($c->join(', '))->toBe('Alice, Bob, Carol');
});

test('join() with finalGlue changes last separator', function () {
    $c = Collection::make(['Alice', 'Bob', 'Carol']);
    expect($c->join(', ', ' and '))->toBe('Alice, Bob and Carol');
});

test('join() returns empty string for empty collection', function () {
    $c = Collection::make();
    expect($c->join(', ', ' and '))->toBe('');
});

test('join() with single item returns that item', function () {
    $c = Collection::make(['Alice']);
    expect($c->join(', ', ' and '))->toBe('Alice');
});

// ---------------------------------------------------------------------------
// pop() / shift() / prepend()
// ---------------------------------------------------------------------------

test('pop() removes and returns last item', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->pop())->toBe(3);
    expect($c->all())->toBe([1, 2]);
});

test('shift() removes and returns first item', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->shift())->toBe(1);
    expect($c->all())->toBe([2, 3]);
});

test('prepend() adds item to beginning', function () {
    $c = Collection::make([2, 3]);
    $c->prepend(1);
    expect($c->first())->toBe(1);
    expect($c->count())->toBe(3);
});

test('prepend() with key adds associative item at front', function () {
    $c = Collection::make(['b' => 2]);
    $c->prepend(1, 'a');
    expect($c->keys()->all())->toBe(['a', 'b']);
});

test('prepend() returns static for chaining', function () {
    $c = Collection::make([2]);
    expect($c->prepend(1))->toBe($c);
});

// ---------------------------------------------------------------------------
// pull()
// ---------------------------------------------------------------------------

test('pull() returns value and removes it', function () {
    $c = Collection::make(['user' => ['name' => 'Alice', 'token' => 'abc']]);
    $token = $c->pull('user.token');
    expect($token)->toBe('abc');
    expect($c->hasKey('user.token'))->toBeFalse();
    expect($c->get('user.name'))->toBe('Alice');
});

test('pull() returns default for missing path', function () {
    $c = Collection::make([]);
    expect($c->pull('missing', 'default'))->toBe('default');
});

// ---------------------------------------------------------------------------
// each()
// ---------------------------------------------------------------------------

test('each() iterates over all items', function () {
    $c = Collection::make([1, 2, 3]);
    $sum = 0;
    $c->each(function ($v) use (&$sum) {
        $sum += $v;
    });
    expect($sum)->toBe(6);
});

test('each() stops when callback returns false', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $count = 0;
    $c->each(function ($v) use (&$count) {
        $count++;

        return $v < 3;
    });
    expect($count)->toBe(3);
});

test('each() returns static for chaining', function () {
    $c = Collection::make([1]);
    expect($c->each(fn () => null))->toBe($c);
});

// ---------------------------------------------------------------------------
// reduce()
// ---------------------------------------------------------------------------

test('reduce() aggregates to single value', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->reduce(fn ($carry, $v) => $carry + $v, 0))->toBe(10);
});

test('reduce() with initial value', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->reduce(fn ($carry, $v) => $carry.$v, 'x'))->toBe('x123');
});

// ---------------------------------------------------------------------------
// pipe() / tap()
// ---------------------------------------------------------------------------

test('pipe() passes collection to callback and returns result', function () {
    $c = Collection::make([1, 2, 3]);
    $result = $c->pipe(fn ($col) => $col->sum());
    expect($result)->toBe(6);
});

test('tap() passes collection to callback and returns $this', function () {
    $c = Collection::make([1, 2, 3]);
    $log = null;
    $returned = $c->tap(function ($col) use (&$log) {
        $log = $col->count();
    });
    expect($log)->toBe(3);
    expect($returned)->toBe($c);
});

// ---------------------------------------------------------------------------
// copy()
// ---------------------------------------------------------------------------

test('copy() returns a clone', function () {
    $c = Collection::make([1, 2, 3]);
    $copy = $c->copy();
    expect($copy)->not->toBe($c);
    expect($copy->all())->toBe($c->all());
});

test('copy() is independent from original', function () {
    $c = Collection::make([1, 2, 3]);
    $copy = $c->copy();
    $copy->push(4);
    expect($c->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// JsonSerializable / Stringable
// ---------------------------------------------------------------------------

test('json_encode() uses JsonSerializable and produces correct JSON', function () {
    $c = Collection::make(['key' => 'value', 'n' => 42]);
    expect(json_encode($c))->toBe('{"key":"value","n":42}');
});

test('(string) cast uses __toString()', function () {
    $c = Collection::make(['key' => 'value']);
    expect((string) $c)->toBe('{"key":"value"}');
});

// =============================================================================
// Static factory: times() and range()
// =============================================================================

test('times() generates n items via callback', function () {
    $c = Collection::times(5, fn ($i) => $i * 2);
    expect($c->all())->toBe([2, 4, 6, 8, 10]);
});

test('times() with n=0 returns empty collection', function () {
    expect(Collection::times(0, fn ($i) => $i)->isEmpty())->toBeTrue();
});

test('times() with negative n returns empty collection', function () {
    expect(Collection::times(-3, fn ($i) => $i)->isEmpty())->toBeTrue();
});

test('range() generates integer range', function () {
    expect(Collection::range(1, 5)->all())->toBe([1, 2, 3, 4, 5]);
});

test('range() respects custom step', function () {
    expect(Collection::range(0, 10, 2)->all())->toBe([0, 2, 4, 6, 8, 10]);
});

test('range() generates float range', function () {
    expect(Collection::range(0, 1, 0.5)->all())->toBe([0.0, 0.5, 1.0]);
});

// =============================================================================
// Enhanced filter()
// =============================================================================

test('filter() with path truthy check returns matching items', function () {
    $c = Collection::make([
        ['name' => 'Alice', 'active' => true],
        ['name' => 'Bob',   'active' => false],
        ['name' => 'Carol', 'active' => true],
    ]);
    expect($c->filter('active')->pluck('name')->all())->toBe([0 => 'Alice', 1 => 'Carol']);
});

test('filter() with path and value returns exact matches', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['role' => 'editor'],
        ['role' => 'admin'],
    ]);
    expect($c->filter('role', 'admin')->count())->toBe(2);
});

test('filter() with callback still works', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->filter(fn ($v) => $v % 2 === 0)->values()->all())->toBe([2, 4]);
});

test('filter() passes the key to the callback', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    expect($c->filter(fn ($v, $k) => $k === 'b')->all())->toBe(['b' => 2]);
});

test('filter() without arguments removes falsy items', function () {
    expect(Collection::make([1, 0, null, 'x', ''])->filter()->all())->toBe([0 => 1, 3 => 'x']);
});

test('filter() with path skips non-array items', function () {
    $c = Collection::make([['active' => true], 'string', 42]);
    expect($c->filter('active')->count())->toBe(1);
});

// =============================================================================
// Enhanced pluck()
// =============================================================================

test('pluck() with key re-indexes by that path', function () {
    $c = Collection::make([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
    expect($c->pluck('name', 'id')->all())->toBe([1 => 'Alice', 2 => 'Bob']);
});

test('pluck() with nested key path', function () {
    $c = Collection::make([
        ['meta' => ['slug' => 'alice'], 'title' => 'Alice Post'],
        ['meta' => ['slug' => 'bob'],   'title' => 'Bob Post'],
    ]);
    expect($c->pluck('title', 'meta.slug')->all())->toBe(['alice' => 'Alice Post', 'bob' => 'Bob Post']);
});

test('pluck() without key keeps original index', function () {
    $c = Collection::make([
        ['id' => 10, 'v' => 'a'],
        ['id' => 20, 'v' => 'b'],
    ]);
    expect($c->pluck('v')->values()->all())->toBe(['a', 'b']);
});

// =============================================================================
// Enhanced contains()
// =============================================================================

test('contains() with path and value finds match', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['role' => 'editor'],
    ]);
    expect($c->contains('role', 'admin'))->toBeTrue();
    expect($c->contains('role', 'viewer'))->toBeFalse();
});

test('contains() with path and null value via func_num_args', function () {
    $c = Collection::make([
        ['status' => null],
        ['status' => 'ok'],
    ]);
    expect($c->contains('status', null))->toBeTrue();
});

test('contains() still works for scalar and callback', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->contains(2))->toBeTrue();
    expect($c->contains(fn ($v) => $v > 2))->toBeTrue();
    expect($c->contains(99))->toBeFalse();
});

// =============================================================================
// find()
// =============================================================================

test('find() returns first item matching path value', function () {
    $c = Collection::make([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Carol'],
    ]);
    expect($c->find('id', 2))->toBe(['id' => 2, 'name' => 'Bob']);
});

test('find() on nested path', function () {
    $c = Collection::make([
        ['user' => ['email' => 'a@x.com']],
        ['user' => ['email' => 'b@x.com']],
    ]);
    expect($c->find('user.email', 'b@x.com'))->toBe(['user' => ['email' => 'b@x.com']]);
});

test('find() returns default when not found', function () {
    $c = Collection::make([['id' => 1]]);
    expect($c->find('id', 99))->toBeNull();
    expect($c->find('id', 99, 'missing'))->toBe('missing');
});

test('find() skips non-array items', function () {
    $c = Collection::make(['hello', ['id' => 1]]);
    expect($c->find('id', 1))->toBe(['id' => 1]);
});

// =============================================================================
// median()
// =============================================================================

test('median() of odd-count set', function () {
    expect(Collection::make([3, 1, 4, 1, 5])->median())->toBe(3);
});

test('median() of even-count set averages two middle values', function () {
    expect(Collection::make([1, 2, 3, 4])->median())->toBe(2.5);
});

test('median() with path', function () {
    $c = Collection::make([
        ['score' => 10],
        ['score' => 20],
        ['score' => 30],
    ]);
    expect($c->median('score'))->toBe(20);
});

test('median() returns null for empty collection', function () {
    expect(Collection::make()->median())->toBeNull();
});

// =============================================================================
// countBy()
// =============================================================================

test('countBy() without argument counts scalar occurrences', function () {
    $c = Collection::make(['a', 'b', 'a', 'c', 'b', 'b']);
    expect($c->countBy()->all())->toBe(['a' => 2, 'b' => 3, 'c' => 1]);
});

test('countBy() with string path groups by field', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['role' => 'editor'],
        ['role' => 'admin'],
    ]);
    expect($c->countBy('role')->all())->toBe(['admin' => 2, 'editor' => 1]);
});

test('countBy() with callback', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $result = $c->countBy(fn ($v) => $v % 2 === 0 ? 'even' : 'odd');
    expect($result->all())->toBe(['odd' => 3, 'even' => 2]);
});

test('countBy() counts missing paths under the empty key', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['no_role' => true],
    ]);
    expect($c->countBy('role')->all())->toBe(['admin' => 1, '' => 1]);
});

test('countBy() normalises null and bool keys', function () {
    expect(Collection::make([null, null, true, false])->countBy()->all())->toBe(['' => 2, 1 => 1, 0 => 1]);
});

// =============================================================================
// sortBy()
// =============================================================================

test('sortBy() sorts by field ascending', function () {
    $c = Collection::make([
        ['name' => 'Charlie'],
        ['name' => 'Alice'],
        ['name' => 'Bob'],
    ]);
    expect($c->sortBy('name')->pluck('name')->all())->toBe([0 => 'Alice', 1 => 'Bob', 2 => 'Charlie']);
});

test('sortBy() with desc=true sorts descending', function () {
    $c = Collection::make([
        ['age' => 30],
        ['age' => 25],
        ['age' => 35],
    ]);
    expect($c->sortBy('age', descending: true)->pluck('age')->values()->all())->toBe([35, 30, 25]);
});

test('sortBy() sorts missing paths as null (first ascending)', function () {
    $c = Collection::make([
        ['name' => 'Bob'],
        ['other' => true],
        ['name' => 'Alice'],
    ]);
    expect($c->sortBy('name')->pluck('name')->all())->toBe([null, 'Alice', 'Bob']);
});

test('sortBy() with callback', function () {
    $c = Collection::make(['banana', 'apple', 'cherry']);
    expect($c->sortBy(fn ($v) => strlen($v))->values()->all())->toBe(['apple', 'banana', 'cherry']);
});

test('sortBy() preserves keys and accepts sort flags', function () {
    $c = Collection::make(['x' => ['v' => 'img12'], 'y' => ['v' => 'img2']]);
    expect($c->sortBy('v', SORT_NATURAL)->keys()->all())->toBe(['y', 'x']);
});

test('sortBy() on nested path', function () {
    $c = Collection::make([
        ['user' => ['age' => 40]],
        ['user' => ['age' => 20]],
        ['user' => ['age' => 30]],
    ]);
    expect($c->sortBy('user.age')->pluck('user.age')->values()->all())->toBe([20, 30, 40]);
});

// =============================================================================
// groupBy()
// =============================================================================

test('groupBy() groups items by path', function () {
    $c = Collection::make([
        ['role' => 'admin',  'name' => 'Alice'],
        ['role' => 'editor', 'name' => 'Bob'],
        ['role' => 'admin',  'name' => 'Carol'],
    ]);
    $groups = $c->groupBy('role');
    expect($groups->has('admin'))->toBeTrue();
    expect($groups->get('admin')->count())->toBe(2);
    expect($groups->get('editor')->count())->toBe(1);
});

test('groupBy() with callback', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $groups = $c->groupBy(fn ($v) => $v % 2 === 0 ? 'even' : 'odd');
    expect($groups->get('even')->all())->toBe([2, 4]);
    expect($groups->get('odd')->all())->toBe([1, 3, 5]);
});

test('groupBy() puts items with a missing path under the empty key', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['no_role' => true],
    ]);
    $groups = $c->groupBy('role');
    expect($groups->keys()->all())->toBe(['admin', '']);
});

test('groupBy() with an array value puts the item in every group', function () {
    $c = Collection::make([
        ['name' => 'a', 'tags' => ['php', 'js']],
        ['name' => 'b', 'tags' => ['php']],
    ]);
    expect($c->groupBy('tags')->map->count()->all())->toBe(['php' => 2, 'js' => 1]);
});

test('groupBy() with several levels', function () {
    $c = Collection::make([
        ['country' => 'RU', 'city' => 'Moscow'],
        ['country' => 'RU', 'city' => 'Kazan'],
        ['country' => 'DE', 'city' => 'Berlin'],
    ]);
    $groups = $c->groupBy(['country', 'city']);
    expect($groups->get('RU')->keys()->all())->toBe(['Moscow', 'Kazan']);
    expect($groups->toArray()['DE']['Berlin'][0]['city'])->toBe('Berlin');
});

// =============================================================================
// mapBy()
// =============================================================================

test('mapBy() re-keys collection by path', function () {
    $c = Collection::make([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
    $keyed = $c->mapBy('id');
    expect($keyed->has('1'))->toBeTrue();
    expect($keyed->get('1'))->toBe(['id' => 1, 'name' => 'Alice']);
});

test('mapBy() with nested path', function () {
    $c = Collection::make([
        ['meta' => ['slug' => 'alice'], 'name' => 'Alice'],
        ['meta' => ['slug' => 'bob'],   'name' => 'Bob'],
    ]);
    $keyed = $c->mapBy('meta.slug');
    expect($keyed->has('alice'))->toBeTrue();
    expect($keyed->get('bob')['name'])->toBe('Bob');
});

test('mapBy() with callback', function () {
    $c = Collection::make([
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob',   'age' => 25],
    ]);
    $keyed = $c->mapBy(fn ($item) => strtolower($item['name']));
    expect($keyed->has('alice'))->toBeTrue();
});

// =============================================================================
// where()
// =============================================================================

test('where() with 2 args filters by strict equality', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['role' => 'editor'],
        ['role' => 'admin'],
    ]);
    expect($c->where('role', 'admin')->count())->toBe(2);
});

test('where() with 3 args uses operator', function () {
    $c = Collection::make([
        ['age' => 15],
        ['age' => 18],
        ['age' => 25],
    ]);
    expect($c->where('age', '>=', 18)->count())->toBe(2);
    expect($c->where('age', '<', 18)->count())->toBe(1);
    expect($c->where('age', '!=', 18)->count())->toBe(2);
});

test('where() with 1 arg delegates to filter truthy check', function () {
    $c = Collection::make([
        ['active' => true],
        ['active' => false],
        ['active' => true],
    ]);
    expect($c->where('active')->count())->toBe(2);
});

test('where() on nested path', function () {
    $c = Collection::make([
        ['user' => ['score' => 100]],
        ['user' => ['score' => 50]],
        ['user' => ['score' => 75]],
    ]);
    expect($c->where('user.score', '>', 60)->count())->toBe(2);
});

// =============================================================================
// flatMap()
// =============================================================================

test('flatMap() maps and flattens one level', function () {
    $c = Collection::make([
        ['tags' => ['php', 'oop']],
        ['tags' => ['js', 'ts']],
    ]);
    $tags = $c->flatMap(fn ($item) => $item['tags']);
    expect($tags->all())->toBe(['php', 'oop', 'js', 'ts']);
});

// =============================================================================
// collapse()
// =============================================================================

test('collapse() merges nested arrays into one flat array', function () {
    $c = Collection::make([[1, 2], [3, 4], [5]]);
    expect($c->collapse()->all())->toBe([1, 2, 3, 4, 5]);
});

test('collapse() drops scalars between arrays', function () {
    $c = Collection::make([[1, 2], 3, [4, 5]]);
    expect($c->collapse()->all())->toBe([1, 2, 4, 5]);
});

test('collapse() merges associative arrays', function () {
    $c = Collection::make([['a' => 1], ['b' => 2], ['a' => 3]]);
    expect($c->collapse()->all())->toBe(['a' => 3, 'b' => 2]);
});

test('collapse() handles nested Collections', function () {
    $inner = Collection::make([10, 20]);
    $c = Collection::make([$inner, [30, 40]]);
    expect($c->collapse()->all())->toBe([10, 20, 30, 40]);
});

// =============================================================================
// undot()
// =============================================================================

test('undot() converts dot-notation keys to nested array', function () {
    $c = Collection::make(['user.name' => 'Alice', 'user.age' => 30]);
    expect($c->undot()->all())->toBe(['user' => ['name' => 'Alice', 'age' => 30]]);
});

test('undot() is inverse of dot()', function () {
    $original = ['a' => ['b' => 1, 'c' => 2]];
    $c = Collection::make($original);
    expect($c->dot()->undot()->all())->toBe($original);
});

// =============================================================================
// nth()
// =============================================================================

test('nth() returns every nth item', function () {
    $c = Collection::make([1, 2, 3, 4, 5, 6]);
    expect($c->nth(2)->all())->toBe([1, 3, 5]);
});

test('nth() with offset', function () {
    $c = Collection::make([1, 2, 3, 4, 5, 6]);
    expect($c->nth(2, 1)->all())->toBe([2, 4, 6]);
});

// =============================================================================
// pad()
// =============================================================================

test('pad() pads collection at end', function () {
    $c = Collection::make([1, 2]);
    expect($c->pad(5, 0)->all())->toBe([1, 2, 0, 0, 0]);
});

test('pad() with negative size pads at beginning', function () {
    $c = Collection::make([1, 2]);
    expect($c->pad(-4, 0)->all())->toBe([0, 0, 1, 2]);
});

test('pad() with size smaller than count is a no-op', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->pad(2, 0)->count())->toBe(3);
});

// =============================================================================
// diff()
// =============================================================================

test('diff() returns items not present in given array', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->diff([2, 4])->all())->toBe([0 => 1, 2 => 3, 4 => 5]);
});

test('diff() accepts another Collection', function () {
    $a = Collection::make([1, 2, 3]);
    $b = Collection::make([2, 3]);
    expect($a->diff($b)->all())->toBe([1]);
});

// =============================================================================
// intersect()
// =============================================================================

test('intersect() returns items present in both', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->intersect([2, 4, 6])->all())->toBe([1 => 2, 3 => 4]);
});

test('intersect() accepts another Collection', function () {
    $a = Collection::make(['a', 'b', 'c']);
    $b = Collection::make(['b', 'c', 'd']);
    expect($a->intersect($b)->values()->all())->toBe(['b', 'c']);
});

// =============================================================================
// partition()
// =============================================================================

test('partition() splits into passing and failing', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    [$even, $odd] = $c->partition(fn ($v) => $v % 2 === 0);
    expect(array_values($even->all()))->toBe([2, 4]);
    expect(array_values($odd->all()))->toBe([1, 3, 5]);
});

test('partition() preserves original keys', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    [$gt1, $rest] = $c->partition(fn ($v) => $v > 1);
    expect($gt1->all())->toBe(['b' => 2, 'c' => 3]);
    expect($rest->all())->toBe(['a' => 1]);
});

// =============================================================================
// when() and unless()
// =============================================================================

test('when() executes then-callback when condition is true', function () {
    $c = Collection::make([1, 2, 3]);
    $result = $c->when(true, fn ($c) => $c->filter(fn ($v) => $v > 1));
    expect($result->values()->all())->toBe([2, 3]);
});

test('when() accepts any truthy value and passes it to the callback', function () {
    $c = Collection::make([['n' => 2], ['n' => 1]]);
    expect($c->when('n', fn ($c, $field) => $c->sortBy($field))->pluck('n')->all())->toBe([1, 2]);
    expect($c->when(0, fn ($c) => $c->clear())->count())->toBe(2);
});

test('when() returns $this when condition is false and no else', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->when(false, fn ($c) => $c->filter(fn ($v) => $v > 1)))->toBe($c);
});

test('when() executes else-callback when condition is false', function () {
    $c = Collection::make([1, 2, 3]);
    $result = $c->when(false, fn ($c) => $c->filter(fn ($v) => $v > 1), fn ($c) => $c->reverse());
    expect($result->values()->all())->toBe([3, 2, 1]);
});

test('when() accepts callable condition', function () {
    $c = Collection::make([1, 2, 3]);
    $result = $c->when(fn ($c) => $c->count() > 2, fn ($c) => $c->push(4));
    expect($result->count())->toBe(4);
});

test('unless() executes callback when condition is false', function () {
    $c = Collection::make([1, 2, 3]);
    $result = $c->unless(false, fn ($c) => $c->filter(fn ($v) => $v > 1));
    expect($result->values()->all())->toBe([2, 3]);
});

test('unless() does not execute callback when condition is true', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->unless(true, fn ($c) => $c->filter(fn ($v) => $v > 1)))->toBe($c);
});

// ---------------------------------------------------------------------------
// where() – operator semantics
// ---------------------------------------------------------------------------

test('where() with 2 args uses loose == comparison', function () {
    $c = Collection::make([
        ['count' => 5],
        ['count' => '5'],
        ['count' => 6],
    ]);
    // loose: int 5 == string '5'
    expect($c->where('count', 5)->count())->toBe(2);
});

test('where() with strict === operator', function () {
    $c = Collection::make([
        ['count' => 5],
        ['count' => '5'],
        ['count' => 6],
    ]);
    expect($c->where('count', '===', 5)->count())->toBe(1);
    expect($c->where('count', '===', 5)->first()['count'])->toBe(5);
});

test('where() with != operator (loose not-equal)', function () {
    $c = Collection::make([
        ['val' => 0],
        ['val' => false],
        ['val' => 1],
    ]);
    // loose: 0 == false, so both are "equal" to false
    expect($c->where('val', '!=', false)->count())->toBe(1);
});

test('where() with !== operator (strict not-equal)', function () {
    $c = Collection::make([
        ['val' => 0],
        ['val' => false],
        ['val' => 1],
    ]);
    expect($c->where('val', '!==', false)->count())->toBe(2);
});

test('where() with <> operator same as !=', function () {
    $c = Collection::make([['n' => 1], ['n' => 2], ['n' => 3]]);
    expect($c->where('n', '<>', 2)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// filter() – null-value handling
// ---------------------------------------------------------------------------

test('filter() by path with null value matches only null items', function () {
    $c = Collection::make([
        ['status' => null],
        ['status' => false],
        ['status' => 0],
        ['status' => 'active'],
    ]);
    $result = $c->filter('status', null);
    expect($result->count())->toBe(1);
    expect($result->first()['status'])->toBeNull();
});

test('filter() by path with false does not match null', function () {
    $c = Collection::make([
        ['flag' => null],
        ['flag' => false],
        ['flag' => true],
    ]);
    expect($c->filter('flag', false)->count())->toBe(1);
    expect($c->filter('flag', false)->first()['flag'])->toBe(false);
});

// ---------------------------------------------------------------------------
// last() – with callback
// ---------------------------------------------------------------------------

test('last() with callback returns last matching item', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->last(fn ($v) => $v < 4))->toBe(3);
});

test('last() with callback returns default when no match', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->last(fn ($v) => $v > 10, 'none'))->toBe('none');
});

test('last() without callback still returns last item', function () {
    $c = Collection::make([10, 20, 30]);
    expect($c->last())->toBe(30);
});

// ---------------------------------------------------------------------------
// mapWithKeys()
// ---------------------------------------------------------------------------

test('mapWithKeys() produces key-value pairs', function () {
    $c = Collection::make([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
    $result = $c->mapWithKeys(fn ($u) => [$u['id'] => $u['name']]);
    expect($result->all())->toBe([1 => 'Alice', 2 => 'Bob']);
});

test('mapWithKeys() can swap keys and values', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    $result = $c->mapWithKeys(fn ($v, $k) => [$v => $k]);
    expect($result->all())->toBe([1 => 'a', 2 => 'b']);
});

// ---------------------------------------------------------------------------
// whereIn() / whereNotIn()
// ---------------------------------------------------------------------------

test('whereIn() filters items where path value is in array', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['role' => 'editor'],
        ['role' => 'viewer'],
    ]);
    $result = $c->whereIn('role', ['admin', 'editor']);
    expect($result->count())->toBe(2);
    expect($result->pluck('role')->values()->all())->toBe(['admin', 'editor']);
});

test('whereIn() strict mode does not match by type coercion', function () {
    $c = Collection::make([['n' => 1], ['n' => '1'], ['n' => 2]]);
    expect($c->whereIn('n', [1], strict: true)->count())->toBe(1);
});

test('whereIn() loose mode matches by type coercion', function () {
    $c = Collection::make([['n' => 1], ['n' => '1'], ['n' => 2]]);
    expect($c->whereIn('n', [1], strict: false)->count())->toBe(2);
});

test('whereNotIn() filters items where path value is NOT in array', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['role' => 'editor'],
        ['role' => 'viewer'],
    ]);
    $result = $c->whereNotIn('role', ['admin', 'editor']);
    expect($result->count())->toBe(1);
    expect($result->first()['role'])->toBe('viewer');
});

// ---------------------------------------------------------------------------
// whereNull() / whereNotNull()
// ---------------------------------------------------------------------------

test('whereNull() returns items where path value is null', function () {
    $c = Collection::make([
        ['email' => null],
        ['email' => 'a@b.c'],
        ['email' => ''],
    ]);
    $result = $c->whereNull('email');
    expect($result->count())->toBe(1);
    expect($result->first()['email'])->toBeNull();
});

test('whereNull() without path returns top-level null items', function () {
    $c = Collection::make([null, 0, false, '', null, 'x']);
    expect($c->whereNull()->count())->toBe(2);
});

test('whereNotNull() returns items where path exists and is not null', function () {
    $c = Collection::make([
        ['email' => null],
        ['email' => 'a@b.c'],
        [],
    ]);
    $result = $c->whereNotNull('email');
    expect($result->count())->toBe(1);
    expect($result->first()['email'])->toBe('a@b.c');
});

test('whereNotNull() without path excludes null top-level items', function () {
    $c = Collection::make([null, 1, null, 'x']);
    expect($c->whereNotNull()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// sole()
// ---------------------------------------------------------------------------

test('sole() returns the only item in the collection', function () {
    $c = Collection::make([42]);
    expect($c->sole())->toBe(42);
});

test('sole() with path+value returns single matching item', function () {
    $c = Collection::make([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
    expect($c->sole('id', 1)['name'])->toBe('Alice');
});

test('sole() with callback returns single matching item', function () {
    $c = Collection::make([['v' => 1], ['v' => 2], ['v' => 3]]);
    expect($c->sole(fn ($i) => $i['v'] === 2)['v'])->toBe(2);
});

test('sole() throws UnderflowException when no item matches', function () {
    $c = Collection::make([['id' => 1], ['id' => 2]]);
    expect(fn () => $c->sole('id', 99))->toThrow(UnderflowException::class);
});

test('sole() throws OverflowException when multiple items match', function () {
    $c = Collection::make([['id' => 1], ['id' => 1]]);
    expect(fn () => $c->sole('id', 1))->toThrow(OverflowException::class);
});

test('sole() throws OverflowException on collection with multiple items', function () {
    $c = Collection::make([1, 2]);
    expect(fn () => $c->sole())->toThrow(OverflowException::class);
});

// ---------------------------------------------------------------------------
// duplicates()
// ---------------------------------------------------------------------------

test('duplicates() returns items repeated by value', function () {
    $c = Collection::make([1, 2, 2, 3, 3, 3]);
    expect($c->duplicates()->values()->all())->toBe([2, 3, 3]);
});

test('duplicates() with path groups by field', function () {
    $c = Collection::make([
        ['email' => 'a@b.c'],
        ['email' => 'x@y.z'],
        ['email' => 'a@b.c'],
    ]);
    $dupes = $c->duplicates('email');
    expect($dupes->all())->toBe([2 => 'a@b.c']);
});

test('duplicates() is loose by default', function () {
    expect(Collection::make([1, '1', 2])->duplicates()->all())->toBe([1 => '1']);
    expect(Collection::make([1, '1', 2])->duplicatesStrict()->all())->toBe([]);
});

test('duplicates() with callback groups by callback result', function () {
    $c = Collection::make([
        ['n' => 1], ['n' => 2], ['n' => 1],
    ]);
    $dupes = $c->duplicates(fn ($i) => $i['n']);
    expect($dupes->all())->toBe([2 => 1]);
});

test('duplicates() returns empty for unique items', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->duplicates()->isEmpty())->toBeTrue();
});

// ---------------------------------------------------------------------------
// takeUntil() / takeWhile()
// ---------------------------------------------------------------------------

test('takeUntil() stops before the matching value', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->takeUntil(4)->all())->toBe([1, 2, 3]);
});

test('takeUntil() with callback stops when callback returns true', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->takeUntil(fn ($v) => $v > 3)->all())->toBe([1, 2, 3]);
});

test('takeUntil() returns all items when value never found', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->takeUntil(99)->all())->toBe([1, 2, 3]);
});

test('takeWhile() takes items while callback is true', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->takeWhile(fn ($v) => $v < 4)->all())->toBe([1, 2, 3]);
});

test('takeWhile() with scalar value takes while items match', function () {
    $c = Collection::make([2, 2, 3, 2]);
    expect($c->takeWhile(2)->all())->toBe([2, 2]);
});

test('takeWhile() returns empty when first item fails', function () {
    $c = Collection::make([5, 1, 2]);
    expect($c->takeWhile(fn ($v) => $v < 3)->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// skipUntil() / skipWhile()
// ---------------------------------------------------------------------------

test('skipUntil() skips until value found, keeps rest including match', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->skipUntil(3)->all())->toBe([2 => 3, 3 => 4, 4 => 5]);
});

test('skipUntil() with callback skips until callback returns true', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->skipUntil(fn ($v) => $v >= 3)->values()->all())->toBe([3, 4, 5]);
});

test('skipUntil() returns empty when value never found', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->skipUntil(99)->all())->toBe([]);
});

test('skipWhile() skips items while callback is true', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    expect($c->skipWhile(fn ($v) => $v < 3)->values()->all())->toBe([3, 4, 5]);
});

test('skipWhile() with scalar value skips while items match', function () {
    $c = Collection::make([2, 2, 3, 2]);
    expect($c->skipWhile(2)->all())->toBe([2 => 3, 3 => 2]);
});

test('skipWhile() returns all items when first item fails', function () {
    $c = Collection::make([5, 1, 2]);
    expect($c->skipWhile(fn ($v) => $v < 3)->all())->toBe([5, 1, 2]);
});

// ---------------------------------------------------------------------------
// zip()
// ---------------------------------------------------------------------------

test('zip() zips two arrays together', function () {
    $c = Collection::make([1, 2, 3]);
    $result = $c->zip(['a', 'b', 'c']);
    expect($result->toArray())->toBe([[1, 'a'], [2, 'b'], [3, 'c']]);
});

test('zip() fills null for missing elements', function () {
    $c = Collection::make([1, 2, 3]);
    $result = $c->zip(['a', 'b']);
    expect($result->toArray())->toBe([[1, 'a'], [2, 'b'], [3, null]]);
});

test('zip() with multiple arrays', function () {
    $c = Collection::make([1, 2]);
    $result = $c->zip(['a', 'b'], ['x', 'y']);
    expect($result->toArray())->toBe([[1, 'a', 'x'], [2, 'b', 'y']]);
});

test('zip() with empty collection returns zipped input', function () {
    $c = Collection::make([]);
    $result = $c->zip([1, 2]);
    expect($result->toArray())->toBe([[null, 1], [null, 2]]);
});

// ---------------------------------------------------------------------------
// unwrap()
// ---------------------------------------------------------------------------

test('unwrap() returns array from collection', function () {
    $c = Collection::make([1, 2, 3]);
    expect(Collection::unwrap($c))->toBe([1, 2, 3]);
});

test('unwrap() returns array as-is', function () {
    expect(Collection::unwrap([4, 5]))->toBe([4, 5]);
});

test('unwrap() returns non-collections unchanged', function () {
    expect(Collection::unwrap('hello'))->toBe('hello');
});

// ---------------------------------------------------------------------------
// hasAny()
// ---------------------------------------------------------------------------

test('hasAny() returns true when at least one path exists', function () {
    $c = Collection::make(['a' => 1, 'b' => null]);
    expect($c->hasAny('a', 'missing'))->toBeTrue();
});

test('hasAny() returns false when all paths are missing', function () {
    $c = Collection::make(['a' => null]);
    expect($c->hasAny('x', 'y'))->toBeFalse();
    expect($c->hasAny('x', 'a'))->toBeTrue();
    expect($c->hasAny(['x', 'a']))->toBeTrue();
});

// ---------------------------------------------------------------------------
// mergeRecursive()
// ---------------------------------------------------------------------------

test('mergeRecursive() deep-merges arrays', function () {
    $c = Collection::make(['a' => ['x' => 1], 'b' => 2]);
    $r = $c->mergeRecursive(['a' => ['y' => 2], 'c' => 3]);
    expect($r->get('a'))->toBe(['x' => 1, 'y' => 2]);
    expect($r->get('c'))->toBe(3);
});

// ---------------------------------------------------------------------------
// concat()
// ---------------------------------------------------------------------------

test('concat() appends values re-indexed', function () {
    $c = Collection::make([1, 2]);
    expect($c->concat(['x' => 3, 'y' => 4])->all())->toBe([1, 2, 3, 4]);
    expect($c->all())->toBe([1, 2]);
});

// ---------------------------------------------------------------------------
// union()
// ---------------------------------------------------------------------------

test('union() fills missing keys', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    $r = $c->union(['b' => 99, 'c' => 3]);
    expect($r->all())->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
});

// ---------------------------------------------------------------------------
// put()
// ---------------------------------------------------------------------------

test('put() sets a key', function () {
    $c = Collection::make(['a' => 1]);
    $c->put('b', 2);
    expect($c->get('b'))->toBe(2);
});

// ---------------------------------------------------------------------------
// reject()
// ---------------------------------------------------------------------------

test('reject() removes items matching callback', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->reject(fn ($v) => $v % 2 === 0)->values()->all())->toBe([1, 3]);
});

test('reject() with path/value removes matching items', function () {
    $c = Collection::make([['active' => true], ['active' => false]]);
    expect($c->reject('active', true)->values()->first())->toBe(['active' => false]);
});

// ---------------------------------------------------------------------------
// transform()
// ---------------------------------------------------------------------------

test('transform() mutates in-place', function () {
    $c = Collection::make([1, 2, 3]);
    $r = $c->transform(fn ($v) => $v * 2);
    expect($r)->toBe($c); // same instance
    expect($c->all())->toBe([2, 4, 6]);
});

// ---------------------------------------------------------------------------
// sortDesc() / sortKeys() / sortKeysDesc()
// ---------------------------------------------------------------------------

test('sortDesc() sorts descending by value', function () {
    $c = Collection::make([3, 1, 2]);
    expect($c->sortDesc()->values()->all())->toBe([3, 2, 1]);
});

test('sortKeys() sorts by key ascending', function () {
    $c = Collection::make(['c' => 3, 'a' => 1, 'b' => 2]);
    expect(array_keys($c->sortKeys()->all()))->toBe(['a', 'b', 'c']);
});

test('sortKeysDesc() sorts by key descending', function () {
    $c = Collection::make(['a' => 1, 'c' => 3, 'b' => 2]);
    expect(array_keys($c->sortKeysDesc()->all()))->toBe(['c', 'b', 'a']);
});

// ---------------------------------------------------------------------------
// firstOrFail()
// ---------------------------------------------------------------------------

test('firstOrFail() returns first matching item', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->firstOrFail(fn ($v) => $v > 1))->toBe(2);
});

test('firstOrFail() throws RuntimeException on no match', function () {
    $c = Collection::make([1, 2]);
    expect(fn () => $c->firstOrFail(fn ($v) => $v > 10))->toThrow(RuntimeException::class);
});

test('firstOrFail() throws on empty collection', function () {
    expect(fn () => Collection::make([])->firstOrFail())->toThrow(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// firstWhere()
// ---------------------------------------------------------------------------

test('firstWhere() finds by path equality', function () {
    $c = Collection::make([['status' => 'a'], ['status' => 'b']]);
    expect($c->firstWhere('status', 'b'))->toBe(['status' => 'b']);
});

test('firstWhere() uses operator', function () {
    $c = Collection::make([['score' => 5], ['score' => 10]]);
    expect($c->firstWhere('score', '>=', 8))->toBe(['score' => 10]);
});

// ---------------------------------------------------------------------------
// value()
// ---------------------------------------------------------------------------

test('value() returns path from first item', function () {
    $c = Collection::make([['a' => 1], ['a' => 2]]);
    expect($c->value('a'))->toBe(1);
});

test('value() returns default when path missing', function () {
    $c = Collection::make([['b' => 1]]);
    expect($c->value('a', 'def'))->toBe('def');
});

// ---------------------------------------------------------------------------
// after() / before()
// ---------------------------------------------------------------------------

test('after() returns item after matching value', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->after(2))->toBe(3);
});

test('after() returns null when not found', function () {
    expect(Collection::make([1, 2])->after(99))->toBeNull();
});

test('after() is loose by default, strict on request', function () {
    expect(Collection::make([1, 2])->after('1'))->toBe(2);
    expect(Collection::make([1, 2])->after('1', strict: true))->toBeNull();
    expect(Collection::make(['trim', 'x'])->after('trim'))->toBe('x');
});

test('before() returns item before matching value', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->before(2))->toBe(1);
});

test('before() returns null when not found', function () {
    expect(Collection::make([1, 2])->before(99))->toBeNull();
});

// ---------------------------------------------------------------------------
// toPrettyJson()
// ---------------------------------------------------------------------------

test('toPrettyJson() produces pretty-printed JSON', function () {
    $c = Collection::make(['a' => 1]);
    $json = $c->toPrettyJson();
    expect($json)->toContain("\n");
    expect(json_decode($json, true))->toBe(['a' => 1]);
});

// ---------------------------------------------------------------------------
// doesntContain() / some() / containsStrict() / every()
// ---------------------------------------------------------------------------

test('doesntContain() is inverse of contains()', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->doesntContain(4))->toBeTrue();
    expect($c->doesntContain(2))->toBeFalse();
});

test('some() is alias for contains()', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->some(fn ($v) => $v > 2))->toBeTrue();
});

test('containsStrict() uses strict comparison', function () {
    $c = Collection::make([['v' => 1], ['v' => '1']]);
    expect($c->containsStrict('v', 1))->toBeTrue();
    // strict: '1' !== 1, so only the first matches - but containsStrict finds at least one
    // The collection also has '1' but it doesn't match int 1 strictly -> only 1 match, still true
    $c2 = Collection::make([['v' => '1']]);
    expect($c2->containsStrict('v', 1))->toBeFalse();
});

test('every() returns true when all items pass callback', function () {
    $c = Collection::make([2, 4, 6]);
    expect($c->every(fn ($v) => $v % 2 === 0))->toBeTrue();
});

test('every() returns false when some items fail', function () {
    $c = Collection::make([2, 3, 6]);
    expect($c->every(fn ($v) => $v % 2 === 0))->toBeFalse();
});

test('every() with path/value', function () {
    $c = Collection::make([['on' => true], ['on' => true]]);
    expect($c->every('on', true))->toBeTrue();
});

// ---------------------------------------------------------------------------
// hasSole()
// ---------------------------------------------------------------------------

test('hasSole() returns true for exactly one match', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->hasSole(fn ($v) => $v === 2))->toBeTrue();
});

test('hasSole() returns false for zero or multiple matches', function () {
    $c = Collection::make([1, 2, 2]);
    expect($c->hasSole(fn ($v) => $v === 2))->toBeFalse();
    expect($c->hasSole(fn ($v) => $v === 9))->toBeFalse();
});

// ---------------------------------------------------------------------------
// average()
// ---------------------------------------------------------------------------

test('average() is alias for avg()', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->average())->toBe(2);
});

// ---------------------------------------------------------------------------
// mode()
// ---------------------------------------------------------------------------

test('mode() returns most frequent values', function () {
    $c = Collection::make([1, 2, 2, 3, 3, 3]);
    expect($c->mode())->toBe([3]);
});

test('mode() with path', function () {
    $c = Collection::make([['v' => 1], ['v' => 2], ['v' => 2]]);
    expect($c->mode('v'))->toBe([2]);
});

// ---------------------------------------------------------------------------
// percentage()
// ---------------------------------------------------------------------------

test('percentage() returns correct percentage', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->percentage(fn ($v) => $v > 2))->toBe(50.0);
});

// ---------------------------------------------------------------------------
// select()
// ---------------------------------------------------------------------------

test('select() picks multiple paths from each item', function () {
    $data = [
        ['name' => 'Alice', 'age' => 30, 'city' => 'NY'],
        ['name' => 'Bob',   'age' => 25, 'city' => 'LA'],
    ];
    $result = Collection::make($data)->select(['name', 'age'])->all();
    expect($result[0])->toBe(['name' => 'Alice', 'age' => 30]);
    expect($result[1])->toBe(['name' => 'Bob',   'age' => 25]);
});

// ---------------------------------------------------------------------------
// toArray()
// ---------------------------------------------------------------------------

test('toArray() is alias for all()', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->toArray())->toBe($c->all());
});

// ---------------------------------------------------------------------------
// flip()
// ---------------------------------------------------------------------------

test('flip() swaps keys and values', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    expect($c->flip()->all())->toBe([1 => 'a', 2 => 'b']);
});

// ---------------------------------------------------------------------------
// take() / skip()
// ---------------------------------------------------------------------------

test('take() takes from start', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->take(2)->all())->toBe([1, 2]);
});

test('take() negative takes from end', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->take(-2)->values()->all())->toBe([3, 4]);
});

test('skip() skips first N items', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->skip(2)->values()->all())->toBe([3, 4]);
});

// ---------------------------------------------------------------------------
// forPage()
// ---------------------------------------------------------------------------

test('forPage() returns correct page slice', function () {
    $c = Collection::make(range(1, 10));
    expect($c->forPage(2, 3)->values()->all())->toBe([4, 5, 6]);
});

// ---------------------------------------------------------------------------
// chunkWhile()
// ---------------------------------------------------------------------------

test('chunkWhile() groups consecutive equal items', function () {
    $c = Collection::make([1, 1, 2, 2, 3]);
    $groups = $c->chunkWhile(fn ($v, $k, $chunk) => $v === $chunk->last())
        ->map(fn ($chunk) => $chunk->values()->all())
        ->all();
    expect($groups)->toBe([[1, 1], [2, 2], [3]]);
});

// ---------------------------------------------------------------------------
// sliding()
// ---------------------------------------------------------------------------

test('sliding() returns overlapping windows', function () {
    $c = Collection::make([1, 2, 3, 4]);
    $windows = $c->sliding(2)->map(fn ($w) => $w->values()->all())->all();
    expect($windows)->toBe([[1, 2], [2, 3], [3, 4]]);
});

test('sliding() respects step', function () {
    $c = Collection::make([1, 2, 3, 4]);
    $windows = $c->sliding(2, 2)->map(fn ($w) => $w->values()->all())->all();
    expect($windows)->toBe([[1, 2], [3, 4]]);
});

// ---------------------------------------------------------------------------
// split()
// ---------------------------------------------------------------------------

test('split() splits into N roughly-equal groups', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $groups = $c->split(2)->map(fn ($g) => $g->values()->all())->all();
    expect(count($groups))->toBe(2);
    expect(array_merge(...$groups))->toBe([1, 2, 3, 4, 5]);
});

// ---------------------------------------------------------------------------
// implode()
// ---------------------------------------------------------------------------

test('implode() joins values with glue', function () {
    $c = Collection::make(['a', 'b', 'c']);
    expect($c->implode(', '))->toBe('a, b, c');
});

test('implode() with path plucks field then joins', function () {
    $c = Collection::make([
        ['name' => 'Alice'],
        ['name' => 'Bob'],
        ['name' => 'Carol'],
    ]);
    expect($c->implode('name', ', '))->toBe('Alice, Bob, Carol');
});

// ---------------------------------------------------------------------------
// sortByDesc()
// ---------------------------------------------------------------------------

test('sortByDesc() sorts descending by path', function () {
    $c = Collection::make([['n' => 2], ['n' => 1], ['n' => 3]]);
    $r = $c->sortByDesc('n');
    expect(array_column($r->values()->all(), 'n'))->toBe([3, 2, 1]);
});

// ---------------------------------------------------------------------------
// diffAssoc() / diffKeys()
// ---------------------------------------------------------------------------

test('diffAssoc() removes entries with same key AND value', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    $r = $c->diffAssoc(['a' => 1, 'b' => 99]);
    expect($r->all())->toBe(['b' => 2, 'c' => 3]);
});

test('diffKeys() removes entries whose keys appear in arg', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    $r = $c->diffKeys(['a' => 'x', 'c' => 'y']);
    expect($r->all())->toBe(['b' => 2]);
});

// ---------------------------------------------------------------------------
// intersectByKeys()
// ---------------------------------------------------------------------------

test('intersectByKeys() keeps only keys present in arg', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    $r = $c->intersectByKeys(['a' => 'x', 'c' => 'y']);
    expect($r->all())->toBe(['a' => 1, 'c' => 3]);
});

// ---------------------------------------------------------------------------
// multiply()
// ---------------------------------------------------------------------------

test('multiply() repeats items N times', function () {
    $c = Collection::make([1, 2]);
    expect($c->multiply(3)->all())->toBe([1, 2, 1, 2, 1, 2]);
});

test('multiply() with 0 returns empty collection', function () {
    expect(Collection::make([1, 2])->multiply(0)->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// crossJoin()
// ---------------------------------------------------------------------------

test('crossJoin() produces cartesian product', function () {
    $c = Collection::make([1, 2]);
    $r = $c->crossJoin(['a', 'b'])->all();
    expect($r)->toBe([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']]);
});

// ---------------------------------------------------------------------------
// combine()
// ---------------------------------------------------------------------------

test('combine() uses collection as keys', function () {
    $c = Collection::make(['a', 'b']);
    expect($c->combine([1, 2])->all())->toBe(['a' => 1, 'b' => 2]);
});

// ---------------------------------------------------------------------------
// shuffle()
// ---------------------------------------------------------------------------

test('shuffle() returns same items in (possibly) different order', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $r = $c->shuffle();
    expect($r->sort()->values()->all())->toBe([1, 2, 3, 4, 5]);
});

// ---------------------------------------------------------------------------
// random()
// ---------------------------------------------------------------------------

test('random() with n=1 returns single item', function () {
    $c = Collection::make([1, 2, 3]);
    $v = $c->random();
    expect(in_array($v, [1, 2, 3], true))->toBeTrue();
});

test('random() with n>1 returns Collection of correct size', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $r = $c->random(3);
    expect($r)->toBeInstanceOf(Collection::class);
    expect($r->count())->toBe(3);
});

test('random() throws when n > count', function () {
    $c = Collection::make([1, 2]);
    expect(fn () => $c->random(5))->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// whereStrict() / whereInStrict() / whereNotInStrict()
// ---------------------------------------------------------------------------

test('whereStrict() uses strict comparison', function () {
    $c = Collection::make([['v' => 1], ['v' => '1']]);
    expect($c->whereStrict('v', 1)->values()->count())->toBe(1);
    expect($c->whereStrict('v', 1)->values()->first())->toBe(['v' => 1]);
});

test('whereInStrict() uses strict comparison', function () {
    $c = Collection::make([['v' => 1], ['v' => '1'], ['v' => 2]]);
    expect($c->whereInStrict('v', [1])->values()->count())->toBe(1);
});

test('whereNotInStrict() uses strict comparison', function () {
    $c = Collection::make([['v' => 1], ['v' => '1'], ['v' => 2]]);
    $r = $c->whereNotInStrict('v', [1])->values();
    expect($r->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// whereBetween() / whereNotBetween()
// ---------------------------------------------------------------------------

test('whereBetween() filters by range inclusive', function () {
    $c = Collection::make([['age' => 10], ['age' => 20], ['age' => 30]]);
    $r = $c->whereBetween('age', [15, 25])->values();
    expect($r->count())->toBe(1);
    expect($r->first())->toBe(['age' => 20]);
});

test('whereNotBetween() excludes range', function () {
    $c = Collection::make([['age' => 10], ['age' => 20], ['age' => 30]]);
    $r = $c->whereNotBetween('age', [15, 25])->values();
    expect($r->pluck('age')->values()->all())->toBe([10, 30]);
});

// ---------------------------------------------------------------------------
// whereInstanceOf()
// ---------------------------------------------------------------------------

test('whereInstanceOf() filters by class', function () {
    $c = Collection::make([new stdClass, 'string', 42]);
    expect($c->whereInstanceOf(stdClass::class)->values()->count())->toBe(1);
});

test('whereInstanceOf() accepts array of classes (OR)', function () {
    $c = Collection::make([new stdClass, new ArrayObject, 'str']);
    $r = $c->whereInstanceOf([stdClass::class, ArrayObject::class])->values();
    expect($r->count())->toBe(2);
});

test('whereInstanceOf() with path', function () {
    $c = Collection::make([
        ['obj' => new stdClass],
        ['obj' => new ArrayObject],
        ['obj' => 'str'],
    ]);
    expect($c->whereInstanceOf(stdClass::class, 'obj')->values()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// splice()
// ---------------------------------------------------------------------------

test('splice() removes and returns items from offset', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $removed = $c->splice(2);
    expect($removed->all())->toBe([3, 4, 5]);
    expect($c->all())->toBe([1, 2]);
});

test('splice() with length', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $removed = $c->splice(1, 2);
    expect($removed->all())->toBe([2, 3]);
    expect($c->all())->toBe([1, 4, 5]);
});

test('splice() with replacement', function () {
    $c = Collection::make([1, 2, 3]);
    $removed = $c->splice(1, 1, [10, 11]);
    expect($removed->all())->toBe([2]);
    expect($c->all())->toBe([1, 10, 11, 3]);
});

// ---------------------------------------------------------------------------
// whenEmpty() / whenNotEmpty()
// ---------------------------------------------------------------------------

test('whenEmpty() executes callback when empty', function () {
    $c = Collection::make([]);
    $called = false;
    $c->whenEmpty(function ($col) use (&$called) {
        $called = true;

        return $col;
    });
    expect($called)->toBeTrue();
});

test('whenEmpty() does not execute when not empty', function () {
    $c = Collection::make([1]);
    $called = false;
    $c->whenEmpty(function ($col) use (&$called) {
        $called = true;

        return $col;
    });
    expect($called)->toBeFalse();
});

test('whenNotEmpty() executes when not empty', function () {
    $c = Collection::make([1, 2]);
    $called = false;
    $c->whenNotEmpty(function ($col) use (&$called) {
        $called = true;

        return $col;
    });
    expect($called)->toBeTrue();
});

// ---------------------------------------------------------------------------
// unlessEmpty() / unlessNotEmpty()
// ---------------------------------------------------------------------------

test('unlessEmpty() executes when NOT empty', function () {
    $c = Collection::make([1]);
    $called = false;
    $c->unlessEmpty(function ($col) use (&$called) {
        $called = true;

        return $col;
    });
    expect($called)->toBeTrue();
});

test('unlessNotEmpty() executes when empty', function () {
    $c = Collection::make([]);
    $called = false;
    $c->unlessNotEmpty(function ($col) use (&$called) {
        $called = true;

        return $col;
    });
    expect($called)->toBeTrue();
});

// ---------------------------------------------------------------------------
// pipeThrough()
// ---------------------------------------------------------------------------

test('pipeThrough() passes collection through each callable', function () {
    $c = Collection::make([3, 1, 2]);
    $result = $c->pipeThrough([
        fn ($col) => $col->sort(),
        fn ($col) => $col->values(),
        fn ($col) => $col->map(fn ($v) => $v * 10),
    ]);
    expect($result->all())->toBe([10, 20, 30]);
});

// ---------------------------------------------------------------------------
// ensure()
// ---------------------------------------------------------------------------

test('ensure() passes when all items match type', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->ensure('int'))->toBeInstanceOf(Collection::class);
});

test('ensure() passes for multiple types (OR)', function () {
    $c = Collection::make([1, 2.5, 3]);
    expect($c->ensure(['int', 'float']))->toBeInstanceOf(Collection::class);
});

test('ensure() throws when item does not match', function () {
    $c = Collection::make([1, 'oops', 3]);
    expect(fn () => $c->ensure('int'))->toThrow(UnexpectedValueException::class);
});

test('ensure() works with class names', function () {
    $c = Collection::make([new stdClass, new stdClass]);
    expect($c->ensure(stdClass::class))->toBeInstanceOf(Collection::class);
});

// ---------------------------------------------------------------------------
// collection() - static alias for make()
// ---------------------------------------------------------------------------

test('collection() creates a collection identical to make()', function () {
    $c = collection([1, 2, 3]);
    expect($c)->toBeInstanceOf(Collection::class);
    expect($c->all())->toBe([1, 2, 3]);
});

// ---------------------------------------------------------------------------
// forget() - alias for remove()
// ---------------------------------------------------------------------------

test('forget() removes a key', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    $c->forget('a');
    expect($c->all())->toBe(['b' => 2]);
});

test('forget() removes nested path', function () {
    $c = Collection::make(['user' => ['name' => 'Alice', 'age' => 30]]);
    $c->forget('user.name');
    expect($c->get('user'))->toBe(['age' => 30]);
});

// ---------------------------------------------------------------------------
// replace() / replaceRecursive()
// ---------------------------------------------------------------------------

test('replace() replaces values by key', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    $r = $c->replace(['b' => 99]);
    expect($r->all())->toBe(['a' => 1, 'b' => 99, 'c' => 3]);
});

test('replace() with another collection', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    $r = $c->replace(Collection::make(['a' => 10]));
    expect($r->get('a'))->toBe(10);
    expect($r->get('b'))->toBe(2);
});

test('replace() does not modify original', function () {
    $c = Collection::make(['x' => 1]);
    $c->replace(['x' => 99]);
    expect($c->get('x'))->toBe(1);
});

test('replaceRecursive() deep-replaces nested arrays', function () {
    $c = Collection::make(['a' => ['x' => 1, 'y' => 2]]);
    $r = $c->replaceRecursive(['a' => ['x' => 99]]);
    expect($r->get('a'))->toBe(['x' => 99, 'y' => 2]);
});

// ---------------------------------------------------------------------------
// dot() - alias for flatten()
// ---------------------------------------------------------------------------

test('dot() flattens to dot-notation', function () {
    $c = Collection::make(['user' => ['name' => 'Alice', 'age' => 30]]);
    $r = $c->dot();
    expect($r->all())->toBe(['user.name' => 'Alice', 'user.age' => 30]);
});

test('dot() with prefix', function () {
    $c = Collection::make(['name' => 'Bob']);
    $r = $c->dot('prefix');
    expect($r->all())->toBe(['prefix.name' => 'Bob']);
});

// ---------------------------------------------------------------------------
// doesntContainStrict()
// ---------------------------------------------------------------------------

test('doesntContainStrict() returns false when strict match exists', function () {
    $c = Collection::make([['v' => 1], ['v' => 2]]);
    expect($c->doesntContainStrict('v', 1))->toBeFalse();
});

test('doesntContainStrict() returns true when no strict match', function () {
    $c = Collection::make([['v' => '1'], ['v' => 2]]);
    expect($c->doesntContainStrict('v', 1))->toBeTrue(); // int 1 not present
});

// ---------------------------------------------------------------------------
// duplicatesStrict()
// ---------------------------------------------------------------------------

test('duplicatesStrict() treats 1 and "1" as different', function () {
    $c = Collection::make([1, '1', 1, 2]);
    $dupes = $c->duplicatesStrict();
    expect(array_values($dupes->all()))->toBe([1]);
});

test('duplicatesStrict() with path', function () {
    $c = Collection::make([
        ['s' => 'a'],
        ['s' => 'b'],
        ['s' => 'a'],
    ]);
    $dupes = $c->duplicatesStrict('s');
    expect($dupes->count())->toBe(1);
    expect(array_values($dupes->all())[0])->toBe('a');
});

// ---------------------------------------------------------------------------
// uniqueStrict()
// ---------------------------------------------------------------------------

test('uniqueStrict() treats 1 and "1" as different', function () {
    $c = Collection::make([1, '1', 1, 2]);
    expect($c->uniqueStrict()->all())->toBe([0 => 1, 1 => '1', 3 => 2]);
});

test('uniqueStrict() with path', function () {
    $c = Collection::make([['v' => 1], ['v' => '1'], ['v' => 1]]);
    $r = $c->uniqueStrict('v');
    expect($r->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// splitIn()
// ---------------------------------------------------------------------------

test('splitIn() returns exactly N groups', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $groups = $c->splitIn(3);
    expect($groups->count())->toBe(3);
    $items = [];
    foreach ($groups->all() as $g) {
        foreach ($g->all() as $v) {
            $items[] = $v;
        }
    }
    expect($items)->toBe([1, 2, 3, 4, 5]);
});

test('splitIn() fills groups completely, never empty ones', function () {
    $c = Collection::make([1, 2]);
    $groups = $c->splitIn(5);
    expect($groups->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// keyBy() - alias for mapBy()
// ---------------------------------------------------------------------------

test('keyBy() re-indexes by key', function () {
    $c = Collection::make([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]);
    $r = $c->keyBy('id');
    expect(array_keys($r->all()))->toBe([1, 2]);
    expect($r->get('1.name'))->toBe('Alice');
});

test('keyBy() accepts callback', function () {
    $c = Collection::make(['alice', 'bob']);
    $r = $c->keyBy(fn ($v) => strtoupper($v));
    expect(array_keys($r->all()))->toBe(['ALICE', 'BOB']);
});

// ---------------------------------------------------------------------------
// intersectAssoc()
// ---------------------------------------------------------------------------

test('intersectAssoc() keeps items with matching key AND value', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    $r = $c->intersectAssoc(['a' => 1, 'b' => 99, 'd' => 3]);
    expect($r->all())->toBe(['a' => 1]);
});

test('intersectAssoc() with collection argument', function () {
    $c = Collection::make(['x' => 10, 'y' => 20]);
    $r = $c->intersectAssoc(Collection::make(['x' => 10, 'y' => 99]));
    expect($r->all())->toBe(['x' => 10]);
});

// ---------------------------------------------------------------------------
// pipeInto()
// ---------------------------------------------------------------------------

test('pipeInto() passes collection to constructor', function () {
    $class = new class(Collection::make())
    {
        public Collection $col;

        public function __construct(Collection $c)
        {
            $this->col = $c;
        }
    };

    $c = Collection::make([1, 2, 3]);
    $result = $c->pipeInto($class::class);
    expect($result->col->all())->toBe([1, 2, 3]);
});

// ---------------------------------------------------------------------------
// dump() / dd()
// ---------------------------------------------------------------------------

test('dump() outputs and returns $this', function () {
    $c = Collection::make([1, 2]);
    ob_start();
    $r = $c->dump();
    ob_end_clean();
    expect($r)->toBe($c);
});

// ---------------------------------------------------------------------------
// eachSpread()
// ---------------------------------------------------------------------------

test('eachSpread() spreads sub-array as args', function () {
    $results = [];
    Collection::make([['Alice', 30], ['Bob', 25]])
        ->eachSpread(function ($name, $age) use (&$results) {
            $results[] = "$name:$age";
        });
    expect($results)->toBe(['Alice:30', 'Bob:25']);
});

test('eachSpread() stops early on false', function () {
    $count = 0;
    Collection::make([[1], [2], [3]])
        ->eachSpread(function ($v) use (&$count) {
            $count++;
            if ($v === 2) {
                return false;
            }
        });
    expect($count)->toBe(2);
});

test('eachSpread() returns $this', function () {
    $c = Collection::make([[1]]);
    expect($c->eachSpread(fn ($v) => null))->toBe($c);
});

// ---------------------------------------------------------------------------
// mapSpread()
// ---------------------------------------------------------------------------

test('mapSpread() spreads sub-array as args', function () {
    $c = Collection::make([[1, 2], [3, 4]]);
    expect($c->mapSpread(fn ($a, $b) => $a + $b)->all())->toBe([3, 7]);
});

test('mapSpread() works with single-element arrays', function () {
    $c = Collection::make([[10], [20]]);
    expect($c->mapSpread(fn ($v) => $v * 2)->all())->toBe([20, 40]);
});

// ---------------------------------------------------------------------------
// mapInto()
// ---------------------------------------------------------------------------

test('mapInto() instantiates class with each item', function () {
    // Use a simple value-object class via anonymous class factory
    $class = new class('placeholder')
    {
        public function __construct(public readonly string $val) {}
    };
    $c = Collection::make(['hello', 'world']);
    $result = $c->mapInto($class::class);
    expect($result->count())->toBe(2);
    expect($result->first()->val)->toBe('hello');
    expect($result->last()->val)->toBe('world');
});

// ---------------------------------------------------------------------------
// mapToGroups()
// ---------------------------------------------------------------------------

test('mapToGroups() groups by callback-returned key', function () {
    $c = Collection::make([
        ['dept' => 'eng',  'name' => 'Alice'],
        ['dept' => 'hr',   'name' => 'Bob'],
        ['dept' => 'eng',  'name' => 'Carol'],
    ]);

    $groups = $c->mapToGroups(fn ($item) => [$item['dept'] => $item['name']]);

    expect($groups->keys()->all())->toBe(['eng', 'hr']);
    expect($groups->get('eng')->all())->toBe(['Alice', 'Carol']);
    expect($groups->get('hr')->all())->toBe(['Bob']);
});

test('mapToGroups() returns Collections as group values', function () {
    $c = Collection::make([['t' => 'a', 'v' => 1], ['t' => 'a', 'v' => 2]]);
    $groups = $c->mapToGroups(fn ($item) => [$item['t'] => $item['v']]);
    expect($groups->get('a'))->toBeInstanceOf(Collection::class);
});

// ---------------------------------------------------------------------------
// macro() / hasMacro() / flushMacros()
// ---------------------------------------------------------------------------

test('macro() registers and calls an instance macro', function () {
    Collection::macro('double', fn () => $this->map(fn ($v) => $v * 2));

    $c = Collection::make([1, 2, 3]);
    expect($c->double()->all())->toBe([2, 4, 6]);

    Collection::flushMacros();
});

test('macro() closure has access to $this', function () {
    Collection::macro('sum2', fn () => array_sum($this->all()));

    expect(Collection::make([10, 20])->sum2())->toBe(30);

    Collection::flushMacros();
});

test('hasMacro() returns true after registration', function () {
    Collection::macro('testMacro', fn () => null);
    expect(Collection::hasMacro('testMacro'))->toBeTrue();
    expect(Collection::hasMacro('missing'))->toBeFalse();
    Collection::flushMacros();
});

test('flushMacros() removes all macros', function () {
    Collection::macro('m1', fn () => null);
    Collection::macro('m2', fn () => null);
    Collection::flushMacros();
    expect(Collection::hasMacro('m1'))->toBeFalse();
    expect(Collection::hasMacro('m2'))->toBeFalse();
});

test('calling unknown macro throws BadMethodCallException', function () {
    expect(fn () => Collection::make([])->nonExistentMacro())
        ->toThrow(BadMethodCallException::class);
});

test('mixin() registers all public methods as macros', function () {
    $mixin = new class
    {
        public function triple(): Closure
        {
            return function () {
                /** @var Collection $this */
                return $this->map(fn ($v) => $v * 3);
            };
        }
    };

    Collection::mixin($mixin);

    expect(Collection::hasMacro('triple'))->toBeTrue();
    expect(Collection::make([1, 2])->triple()->all())->toBe([3, 6]);

    Collection::flushMacros();
});

test('mixin() respects replace=false', function () {
    Collection::macro('greet', fn () => 'original');

    $mixin = new class
    {
        public function greet(): Closure
        {
            return fn () => 'overwritten';
        }
    };

    Collection::mixin($mixin, replace: false);
    expect(Collection::make([])->greet())->toBe('original');

    Collection::flushMacros();
});

// =============================================================================
// mapKeys()
// =============================================================================

test('mapKeys() transforms keys, preserves values', function () {
    $c = Collection::make(['Foo' => 1, 'Bar' => 2, 'Baz' => 3]);
    expect($c->mapKeys(fn ($k) => strtolower($k))->all())
        ->toBe(['foo' => 1, 'bar' => 2, 'baz' => 3]);
});

test('mapKeys() receives value as second argument', function () {
    $c = Collection::make([
        ['id' => 10, 'name' => 'Alice'],
        ['id' => 20, 'name' => 'Bob'],
    ]);
    expect($c->mapKeys(fn ($k, $v) => $v['id'])->all())
        ->toBe([10 => ['id' => 10, 'name' => 'Alice'], 20 => ['id' => 20, 'name' => 'Bob']]);
});

test('mapKeys() returns new Collection instance', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->mapKeys(fn ($k) => $k))->toBeInstanceOf(Collection::class);
});

test('mapKeys() does not mutate original', function () {
    $c = Collection::make(['A' => 1]);
    $c->mapKeys(fn ($k) => strtolower($k));
    expect($c->keys()->all())->toBe(['A']);
});

// =============================================================================
// sortBy() with array (multi-column)
// =============================================================================

test('sortBy() with array of paths sorts by multiple columns', function () {
    $c = Collection::make([
        ['name' => 'Bob',   'age' => 30],
        ['name' => 'Alice', 'age' => 25],
        ['name' => 'Alice', 'age' => 20],
    ]);
    $result = $c->sortBy(['name', 'age'])->values()->all();
    expect(array_column($result, 'name'))->toBe(['Alice', 'Alice', 'Bob']);
    expect(array_column($result, 'age'))->toBe([20, 25, 30]);
});

test('sortBy() with array supports per-column direction', function () {
    $c = Collection::make([
        ['name' => 'Bob',   'age' => 30],
        ['name' => 'Alice', 'age' => 25],
        ['name' => 'Alice', 'age' => 20],
    ]);
    // name ASC, age DESC
    $result = $c->sortBy(['name', 'age' => 'desc'])->values()->all();
    expect(array_column($result, 'name'))->toBe(['Alice', 'Alice', 'Bob']);
    expect(array_column($result, 'age'))->toBe([25, 20, 30]);
});

test('sortBy() with array sorts missing paths as null', function () {
    $c = Collection::make([
        ['name' => 'Bob'],
        ['other' => true],
        ['name' => 'Alice'],
    ]);
    $result = $c->sortBy(['name'])->values()->all();
    expect($result[1]['name'])->toBe('Alice');
    expect($result[0])->toBe(['other' => true]);
});

// =============================================================================
// insert()
// =============================================================================

test('insert() inserts at the given index', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->insert(2, 99)->all())->toBe([1, 2, 99, 3, 4]);
});

test('insert() at index 0 prepends', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->insert(0, 0)->all())->toBe([0, 1, 2, 3]);
});

test('insert() at count appends', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->insert(3, 4)->all())->toBe([1, 2, 3, 4]);
});

test('insert() beyond count appends', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->insert(999, 4)->all())->toBe([1, 2, 3, 4]);
});

test('insert() with negative index inserts from end', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->insert(-1, 99)->all())->toBe([1, 2, 99, 3]);
});

test('insert() returns new Collection instance', function () {
    $c = Collection::make([1, 2, 3]);
    $new = $c->insert(1, 9);
    expect($new)->toBeInstanceOf(Collection::class);
    expect($c->all())->toBe([1, 2, 3]); // not mutated
});

// =============================================================================
// isList() / isAssoc()
// =============================================================================

test('isList() returns true for sequential integer-keyed array', function () {
    expect(Collection::make([1, 2, 3])->isList())->toBeTrue();
    expect(Collection::make([])->isList())->toBeTrue();
});

test('isList() returns false for associative array', function () {
    expect(Collection::make(['a' => 1, 'b' => 2])->isList())->toBeFalse();
});

test('isList() returns false when integer keys are non-sequential', function () {
    expect(Collection::make([0 => 'a', 2 => 'b'])->isList())->toBeFalse();
});

test('isAssoc() is inverse of isList()', function () {
    expect(Collection::make(['a' => 1])->isAssoc())->toBeTrue();
    expect(Collection::make([1, 2, 3])->isAssoc())->toBeFalse();
});

// =============================================================================
// product()
// =============================================================================

test('product() returns product of all scalar items', function () {
    expect(Collection::make([1, 2, 3, 4])->product())->toBe(24);
});

test('product() with path multiplies plucked values', function () {
    $c = Collection::make([['qty' => 2], ['qty' => 3], ['qty' => 5]]);
    expect($c->product('qty'))->toBe(30);
});

test('product() with callable', function () {
    $c = Collection::make([
        ['price' => 10, 'qty' => 2],
        ['price' => 3,  'qty' => 4],
    ]);
    // 20 * 12 = 240
    expect($c->product(fn ($i) => $i['price'] * $i['qty']))->toBe(240);
});

test('product() returns 1 for empty collection (identity)', function () {
    expect(Collection::make([])->product())->toBe(1);
});

// =============================================================================
// scan()
// =============================================================================

test('scan() returns running totals', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->scan(fn ($carry, $v) => $carry + $v, 0)->all())->toBe([1, 3, 6, 10]);
});

test('scan() result has same count as input', function () {
    $c = Collection::make([10, 20, 30]);
    expect($c->scan(fn ($carry, $v) => $carry + $v, 0)->count())->toBe(3);
});

test('scan() works with non-numeric values', function () {
    $c = Collection::make(['a', 'b', 'c']);
    expect($c->scan(fn ($carry, $v) => $carry.$v, '')->all())->toBe(['a', 'ab', 'abc']);
});

test('scan() returns empty Collection for empty input', function () {
    expect(Collection::make([])->scan(fn ($c, $v) => $c + $v, 0)->all())->toBe([]);
});

test('scan() callback receives key as third argument', function () {
    $keys = [];
    Collection::make(['a' => 1, 'b' => 2])->scan(function ($carry, $v, $key) use (&$keys) {
        $keys[] = $key;

        return $carry + $v;
    }, 0);
    expect($keys)->toBe(['a', 'b']);
});

// =============================================================================
// mapPath()
// =============================================================================

test('mapPath() transforms value at a plain path', function () {
    $c = Collection::make([
        ['price' => 100],
        ['price' => 200],
    ]);
    $result = $c->mapPath('price', fn ($p) => $p * 2)->pluck('price')->all();
    expect(array_values($result))->toBe([200, 400]);
});

test('mapPath() transforms nested dot-notation path', function () {
    $c = Collection::make([
        ['meta' => ['slug' => 'Hello World']],
        ['meta' => ['slug' => 'Foo Bar']],
    ]);
    $result = $c->mapPath('meta.slug', fn ($s) => strtolower(str_replace(' ', '-', $s)));
    expect($result->value('meta.slug'))->toBe('hello-world');
});

test('mapPath() leaves items without the path unchanged', function () {
    $c = Collection::make([['price' => 10], ['other' => 5]]);
    $result = $c->mapPath('price', fn ($p) => $p * 10)->all();
    expect($result[0]['price'])->toBe(100);
    expect($result[1])->toBe(['other' => 5]);
});

test('mapPath() does not mutate original', function () {
    $c = Collection::make([['n' => 1]]);
    $c->mapPath('n', fn ($v) => $v * 99);
    expect($c->first()['n'])->toBe(1);
});

test('mapPath() returns new Collection instance', function () {
    $c = Collection::make([['v' => 1]]);
    expect($c->mapPath('v', fn ($x) => $x))->toBeInstanceOf(Collection::class);
});

// =============================================================================
// transpose()
// =============================================================================

test('transpose() swaps rows and columns', function () {
    $c = Collection::make([[1, 2, 3], [4, 5, 6]]);
    expect($c->transpose()->all())->toBe([[1, 4], [2, 5], [3, 6]]);
});

test('transpose() single row becomes single-item columns', function () {
    $c = Collection::make([[1, 2, 3]]);
    expect($c->transpose()->all())->toBe([[1], [2], [3]]);
});

test('transpose() pads short rows with null', function () {
    $c = Collection::make([[1, 2, 3], [4, 5]]);
    expect($c->transpose()->all())->toBe([[1, 4], [2, 5], [3, null]]);
});

test('transpose() returns empty for empty collection', function () {
    expect(Collection::make([])->transpose()->all())->toBe([]);
});

test('transpose() double-transpose is identity', function () {
    $matrix = [[1, 2, 3], [4, 5, 6]];
    expect(Collection::make($matrix)->transpose()->transpose()->all())->toBe($matrix);
});

// =============================================================================
// toTree()
// =============================================================================

test('toTree() builds nested tree from flat list', function () {
    $c = Collection::make([
        ['id' => 1, 'parent_id' => null, 'name' => 'Root'],
        ['id' => 2, 'parent_id' => 1,    'name' => 'Child A'],
        ['id' => 3, 'parent_id' => 1,    'name' => 'Child B'],
        ['id' => 4, 'parent_id' => 2,    'name' => 'Grandchild'],
    ]);
    $tree = $c->toTree('id', 'parent_id');
    expect($tree->count())->toBe(1);
    $root = $tree->first();
    expect($root['name'])->toBe('Root');
    expect(count($root['children']))->toBe(2);
    expect($root['children'][0]['name'])->toBe('Child A');
    expect(count($root['children'][0]['children']))->toBe(1);
    expect($root['children'][0]['children'][0]['name'])->toBe('Grandchild');
});

test('toTree() supports non-null root id', function () {
    $c = Collection::make([
        ['id' => 1, 'parent_id' => 0, 'name' => 'Root'],
        ['id' => 2, 'parent_id' => 1, 'name' => 'Child'],
    ]);
    $tree = $c->toTree('id', 'parent_id', 'children', 0);
    expect($tree->count())->toBe(1);
    expect($tree->first()['name'])->toBe('Root');
    expect(count($tree->first()['children']))->toBe(1);
});

test('toTree() returns multiple roots when they exist', function () {
    $c = Collection::make([
        ['id' => 1, 'parent_id' => null, 'name' => 'Root 1'],
        ['id' => 2, 'parent_id' => null, 'name' => 'Root 2'],
        ['id' => 3, 'parent_id' => 1,    'name' => 'Child of 1'],
    ]);
    $tree = $c->toTree('id', 'parent_id');
    expect($tree->count())->toBe(2);
});

test('toTree() supports custom children key', function () {
    $c = Collection::make([
        ['id' => 1, 'parent_id' => null],
        ['id' => 2, 'parent_id' => 1],
    ]);
    $tree = $c->toTree('id', 'parent_id', 'items');
    expect(isset($tree->first()['items']))->toBeTrue();
    expect(count($tree->first()['items']))->toBe(1);
});

// =============================================================================
// whereContains()
// =============================================================================

test('whereContains() filters by substring match', function () {
    $c = Collection::make([
        ['name' => 'Alice Smith'],
        ['name' => 'Bob Jones'],
        ['name' => 'Charlie Smithson'],
    ]);
    $result = $c->whereContains('name', 'Smith');
    expect($result->count())->toBe(2);
    expect($result->pluck('name')->values()->all())->toBe(['Alice Smith', 'Charlie Smithson']);
});

test('whereContains() is case-sensitive by default', function () {
    $c = Collection::make([['name' => 'Alice'], ['name' => 'alice']]);
    expect($c->whereContains('name', 'Ali')->count())->toBe(1);
});

test('whereContains() ignoreCase=true matches regardless of case', function () {
    $c = Collection::make([['name' => 'Alice'], ['name' => 'alice'], ['name' => 'BOB']]);
    expect($c->whereContains('name', 'ali', true)->count())->toBe(2);
});

test('whereContains() supports nested dot-notation path', function () {
    $c = Collection::make([
        ['user' => ['email' => 'alice@gmail.com']],
        ['user' => ['email' => 'bob@yahoo.com']],
    ]);
    expect($c->whereContains('user.email', '@gmail')->count())->toBe(1);
});

test('whereContains() skips non-string and missing paths', function () {
    $c = Collection::make([['n' => 42], ['n' => 'hello world'], ['other' => true]]);
    expect($c->whereContains('n', 'hello')->count())->toBe(1);
});

// =============================================================================
// paginate()
// =============================================================================

test('paginate() returns correct data slice', function () {
    $c = Collection::make(range(1, 50));
    $result = $c->paginate(10, 2);
    expect($result['data']->all())->toBe(range(11, 20));
});

test('paginate() metadata is correct', function () {
    $c = Collection::make(range(1, 50));
    $result = $c->paginate(10, 2);
    expect($result['total'])->toBe(50);
    expect($result['per_page'])->toBe(10);
    expect($result['current_page'])->toBe(2);
    expect($result['last_page'])->toBe(5);
    expect($result['from'])->toBe(11);
    expect($result['to'])->toBe(20);
});

test('paginate() page 1 has correct from/to', function () {
    $c = Collection::make(range(1, 7));
    $result = $c->paginate(3, 1);
    expect($result['from'])->toBe(1);
    expect($result['to'])->toBe(3);
    expect($result['last_page'])->toBe(3);
});

test('paginate() last page may be partial', function () {
    $c = Collection::make(range(1, 7));
    $result = $c->paginate(3, 3);
    expect($result['data']->all())->toBe([7]);
    expect($result['from'])->toBe(7);
    expect($result['to'])->toBe(7);
});

test('paginate() empty collection returns nulls for from/to', function () {
    $result = Collection::make([])->paginate(10, 1);
    expect($result['total'])->toBe(0);
    expect($result['from'])->toBeNull();
    expect($result['to'])->toBeNull();
    expect($result['last_page'])->toBe(1);
});

test('paginate() data is a Collection instance', function () {
    $result = Collection::make([1, 2, 3])->paginate(2, 1);
    expect($result['data'])->toBeInstanceOf(Collection::class);
});

test('paginate() page clamped to last_page when out of bounds', function () {
    $c = Collection::make(range(1, 5));
    $result = $c->paginate(5, 99);
    expect($result['current_page'])->toBe(1);
});

// =============================================================================
// hasAll()
// =============================================================================

test('hasAll() returns true when all paths exist', function () {
    $c = Collection::make(['id' => 1, 'name' => 'Alice', 'email' => 'a@b.com']);
    expect($c->hasAll('id', 'name', 'email'))->toBeTrue();
});

test('hasAll() returns false when any path is missing', function () {
    $c = Collection::make(['id' => 1, 'name' => 'Alice']);
    expect($c->hasAll('id', 'name', 'email'))->toBeFalse();
});

test('hasAll() returns true for single existing path', function () {
    $c = Collection::make(['x' => 5]);
    expect($c->hasAll('x'))->toBeTrue();
});

test('hasAll() supports nested dot-notation paths', function () {
    $c = Collection::make(['user' => ['id' => 1, 'email' => 'x@y.com']]);
    expect($c->hasAll('user.id', 'user.email'))->toBeTrue();
    expect($c->hasAll('user.id', 'user.phone'))->toBeFalse();
});

test('hasAll() returns true with zero arguments', function () {
    $c = Collection::make(['a' => 1]);
    expect($c->hasAll())->toBeTrue();
});

// =============================================================================
// getOrPut()
// =============================================================================

test('getOrPut() returns existing value without modifying', function () {
    $c = Collection::make(['score' => 42]);
    expect($c->getOrPut('score', 0))->toBe(42);
    expect($c->get('score'))->toBe(42);
});

test('getOrPut() sets and returns default when path missing', function () {
    $c = Collection::make([]);
    $v = $c->getOrPut('retries', 0);
    expect($v)->toBe(0);
    expect($c->get('retries'))->toBe(0);
});

test('getOrPut() accepts callable default (lazy)', function () {
    $c = Collection::make([]);
    $called = 0;
    $c->getOrPut('token', function () use (&$called) {
        $called++;

        return 'abc';
    });
    expect($c->get('token'))->toBe('abc');
    expect($called)->toBe(1);
});

test('getOrPut() callable not invoked when key exists', function () {
    $c = Collection::make(['token' => 'existing']);
    $called = 0;
    $result = $c->getOrPut('token', function () use (&$called) {
        $called++;

        return 'new';
    });
    expect($result)->toBe('existing');
    expect($called)->toBe(0);
});

test('getOrPut() supports dot-notation paths', function () {
    $c = Collection::make(['meta' => ['hits' => 5]]);
    expect($c->getOrPut('meta.hits', 0))->toBe(5);
    $c->getOrPut('meta.misses', 0);
    expect($c->get('meta.misses'))->toBe(0);
});

// =============================================================================
// whereStartsWith()
// =============================================================================

test('whereStartsWith() filters by prefix', function () {
    $c = Collection::make([
        ['name' => 'Alice'],
        ['name' => 'Alicia'],
        ['name' => 'Bob'],
    ]);
    expect($c->whereStartsWith('name', 'Al')->count())->toBe(2);
});

test('whereStartsWith() is case-sensitive by default', function () {
    $c = Collection::make([['name' => 'alice'], ['name' => 'Alice']]);
    expect($c->whereStartsWith('name', 'Al')->count())->toBe(1);
});

test('whereStartsWith() ignoreCase=true', function () {
    $c = Collection::make([['name' => 'alice'], ['name' => 'Alice'], ['name' => 'Bob']]);
    expect($c->whereStartsWith('name', 'al', true)->count())->toBe(2);
});

test('whereStartsWith() skips non-string and missing paths', function () {
    $c = Collection::make([['n' => 42], ['n' => 'hello'], ['other' => true]]);
    expect($c->whereStartsWith('n', 'he')->count())->toBe(1);
});

// =============================================================================
// whereEndsWith()
// =============================================================================

test('whereEndsWith() filters by suffix', function () {
    $c = Collection::make([
        ['email' => 'alice@gmail.com'],
        ['email' => 'bob@yahoo.com'],
        ['email' => 'carol@gmail.com'],
    ]);
    expect($c->whereEndsWith('email', '@gmail.com')->count())->toBe(2);
});

test('whereEndsWith() is case-sensitive by default', function () {
    $c = Collection::make([['file' => 'readme.MD'], ['file' => 'readme.md']]);
    expect($c->whereEndsWith('file', '.md')->count())->toBe(1);
});

test('whereEndsWith() ignoreCase=true', function () {
    $c = Collection::make([['file' => 'readme.MD'], ['file' => 'readme.md']]);
    expect($c->whereEndsWith('file', '.md', true)->count())->toBe(2);
});

test('whereEndsWith() skips non-string and missing paths', function () {
    $c = Collection::make([['n' => 42], ['n' => 'foobar'], ['x' => 1]]);
    expect($c->whereEndsWith('n', 'bar')->count())->toBe(1);
});

// =============================================================================
// evolve()
// =============================================================================

test('evolve() applies transformers to specified paths', function () {
    $c = Collection::make([
        ['price' => 100, 'name' => '  Alice  '],
        ['price' => 200, 'name' => '  Bob  '],
    ]);
    $result = $c->evolve([
        'price' => fn ($p) => $p * 2,
        'name' => 'trim',
    ]);
    expect($result->first()['price'])->toBe(200);
    expect($result->first()['name'])->toBe('Alice');
});

test('evolve() leaves paths not in transformers untouched', function () {
    $c = Collection::make([['a' => 1, 'b' => 2]]);
    $result = $c->evolve(['a' => fn ($v) => $v + 10]);
    expect($result->first()['b'])->toBe(2);
});

test('evolve() supports dot-notation paths', function () {
    $c = Collection::make([['meta' => ['slug' => 'HELLO-WORLD']]]);
    $result = $c->evolve(['meta.slug' => 'strtolower']);
    expect($result->first()['meta']['slug'])->toBe('hello-world');
});

test('evolve() does not modify original collection', function () {
    $c = Collection::make([['v' => 1]]);
    $c->evolve(['v' => fn ($x) => $x * 99]);
    expect($c->first()['v'])->toBe(1);
});

// =============================================================================
// symmetricDiff()
// =============================================================================

test('symmetricDiff() returns items in one but not both', function () {
    $a = Collection::make([1, 2, 3, 4]);
    $b = Collection::make([3, 4, 5, 6]);
    expect($a->symmetricDiff($b)->sort()->values()->all())->toBe([1, 2, 5, 6]);
});

test('symmetricDiff() returns empty when collections are equal', function () {
    $a = Collection::make([1, 2, 3]);
    expect($a->symmetricDiff([1, 2, 3])->isEmpty())->toBeTrue();
});

test('symmetricDiff() accepts plain array', function () {
    $a = Collection::make([1, 2, 3]);
    expect($a->symmetricDiff([2, 3, 4])->sort()->values()->all())->toBe([1, 4]);
});

test('symmetricDiff() with disjoint collections returns all items', function () {
    $a = Collection::make([1, 2]);
    $b = Collection::make([3, 4]);
    expect($a->symmetricDiff($b)->count())->toBe(4);
});

// =============================================================================
// diffUsing()
// =============================================================================

test('diffUsing() filters with custom comparator', function () {
    $a = Collection::make([['id' => 1], ['id' => 2], ['id' => 3]]);
    $b = [['id' => 2], ['id' => 3]];
    $result = $a->diffUsing($b, fn ($x, $y) => $x['id'] <=> $y['id']);
    expect($result->count())->toBe(1);
    expect($result->first()['id'])->toBe(1);
});

test('diffUsing() returns all items when nothing matches', function () {
    $a = Collection::make([1, 2, 3]);
    $result = $a->diffUsing([4, 5, 6], fn ($a, $b) => $a <=> $b);
    expect($result->count())->toBe(3);
});

test('diffUsing() accepts Collection as argument', function () {
    $a = Collection::make([1, 2, 3]);
    $b = Collection::make([2, 3]);
    expect($a->diffUsing($b, fn ($a, $b) => $a <=> $b)->all())->toBe([1]);
});

// =============================================================================
// intersectUsing()
// =============================================================================

test('intersectUsing() keeps items matching comparator', function () {
    $a = Collection::make([['id' => 1], ['id' => 2], ['id' => 3]]);
    $b = [['id' => 2], ['id' => 3]];
    $result = $a->intersectUsing($b, fn ($x, $y) => $x['id'] <=> $y['id']);
    expect($result->count())->toBe(2);
    expect($result->pluck('id')->all())->toBe([2, 3]);
});

test('intersectUsing() returns empty when nothing matches', function () {
    $a = Collection::make([1, 2, 3]);
    $result = $a->intersectUsing([4, 5, 6], fn ($a, $b) => $a <=> $b);
    expect($result->isEmpty())->toBeTrue();
});

test('intersectUsing() accepts Collection as argument', function () {
    $a = Collection::make([1, 2, 3]);
    $b = Collection::make([2, 3, 4]);
    expect($a->intersectUsing($b, fn ($a, $b) => $a <=> $b)->all())->toBe([1 => 2, 2 => 3]);
});

// =============================================================================
// minBy() / maxBy()
// =============================================================================

test('minBy() returns item with minimum value at path', function () {
    $c = Collection::make([
        ['name' => 'Alice', 'score' => 80],
        ['name' => 'Bob',   'score' => 55],
        ['name' => 'Carol', 'score' => 92],
    ]);
    expect($c->minBy('score')['name'])->toBe('Bob');
});

test('maxBy() returns item with maximum value at path', function () {
    $c = Collection::make([
        ['name' => 'Alice', 'score' => 80],
        ['name' => 'Bob',   'score' => 55],
        ['name' => 'Carol', 'score' => 92],
    ]);
    expect($c->maxBy('score')['name'])->toBe('Carol');
});

test('minBy() accepts callable', function () {
    $c = Collection::make([
        ['w' => 3, 'h' => 4],
        ['w' => 2, 'h' => 6],
        ['w' => 5, 'h' => 2],
    ]);
    expect($c->minBy(fn ($i) => $i['w'] * $i['h']))->toBe(['w' => 5, 'h' => 2]);
});

test('maxBy() accepts callable', function () {
    $c = Collection::make([
        ['w' => 3, 'h' => 4],  // area 12
        ['w' => 2, 'h' => 5],  // area 10
        ['w' => 5, 'h' => 2],  // area 10
    ]);
    expect($c->maxBy(fn ($i) => $i['w'] * $i['h']))->toBe(['w' => 3, 'h' => 4]);
});

test('minBy() returns null for empty collection', function () {
    expect(Collection::make([])->minBy('x'))->toBeNull();
});

test('maxBy() returns null for empty collection', function () {
    expect(Collection::make([])->maxBy('x'))->toBeNull();
});

test('minBy() supports dot-notation path', function () {
    $c = Collection::make([
        ['meta' => ['price' => 10]],
        ['meta' => ['price' => 5]],
        ['meta' => ['price' => 20]],
    ]);
    expect($c->minBy('meta.price')['meta']['price'])->toBe(5);
});

// =============================================================================
// firstKey() / lastKey()
// =============================================================================

test('firstKey() returns key of first item', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    expect($c->firstKey())->toBe('a');
});

test('lastKey() returns key of last item', function () {
    $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
    expect($c->lastKey())->toBe('c');
});

test('firstKey() with callback returns first matching key', function () {
    $c = Collection::make(['a' => 1, 'b' => 4, 'c' => 3]);
    expect($c->firstKey(fn ($v) => $v > 2))->toBe('b');
});

test('lastKey() with callback returns last matching key', function () {
    $c = Collection::make(['a' => 1, 'b' => 4, 'c' => 3]);
    expect($c->lastKey(fn ($v) => $v > 2))->toBe('c');
});

test('firstKey() returns null for empty collection', function () {
    expect(Collection::make([])->firstKey())->toBeNull();
});

test('lastKey() returns null when no item matches callback', function () {
    expect(Collection::make([1, 2, 3])->lastKey(fn ($v) => $v > 99))->toBeNull();
});

test('firstKey() works on list (integer keys)', function () {
    $c = Collection::make([10, 20, 30]);
    expect($c->firstKey())->toBe(0);
    expect($c->lastKey())->toBe(2);
});

// =============================================================================
// filterMap()
// =============================================================================

test('filterMap() keeps non-null non-false results', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $result = $c->filterMap(fn ($n) => $n % 2 === 0 ? $n * 10 : null);
    expect($result->all())->toBe([1 => 20, 3 => 40]);
});

test('filterMap() removes false results', function () {
    $c = Collection::make(['alice', 'bob', 'carol']);
    $result = $c->filterMap(fn ($n) => strlen($n) > 3 ? strtoupper($n) : false);
    expect($result->all())->toBe([0 => 'ALICE', 2 => 'CAROL']);
});

test('filterMap() keeps 0 and empty string', function () {
    // null and false are discarded; 0, '' and non-falsy values are kept
    $c = Collection::make([0, '', null, false, 1]);
    $result = $c->filterMap(fn ($v) => $v);
    expect($result->all())->toBe([0 => 0, 1 => '', 4 => 1]);
});

test('filterMap() keeps true and arrays', function () {
    $c = Collection::make([['ok' => true], null, ['ok' => false]]);
    $result = $c->filterMap(fn ($v) => $v);
    expect($result->count())->toBe(2);
});

test('filterMap() returns empty for all-null results', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->filterMap(fn () => null)->isEmpty())->toBeTrue();
});

// =============================================================================
// whereMatches()
// =============================================================================

test('whereMatches() filters by regex', function () {
    $c = Collection::make([
        ['code' => 'AB123'],
        ['code' => 'XY999'],
        ['code' => 'ab123'],
    ]);
    // 'AB123' and 'XY999' match; 'ab123' does not (lowercase)
    expect($c->whereMatches('code', '/^[A-Z]{2}\d{3}$/')->count())->toBe(2);
});

test('whereMatches() supports case-insensitive regex flag', function () {
    $c = Collection::make([
        ['email' => 'user@Gmail.COM'],
        ['email' => 'other@yahoo.com'],
    ]);
    expect($c->whereMatches('email', '/@gmail\.com$/i')->count())->toBe(1);
});

test('whereMatches() skips non-string values', function () {
    $c = Collection::make([['n' => 42], ['n' => 'hello123'], ['n' => null]]);
    expect($c->whereMatches('n', '/\d+/')->count())->toBe(1);
});

test('whereMatches() supports dot-notation path', function () {
    $c = Collection::make([
        ['user' => ['phone' => '+7-999-123-4567']],
        ['user' => ['phone' => '8 800 555 35 35']],
    ]);
    expect($c->whereMatches('user.phone', '/^\+7/')->count())->toBe(1);
});

// =============================================================================
// sortKeysUsing()
// =============================================================================

test('sortKeysUsing() sorts keys with custom comparator', function () {
    $c = Collection::make(['banana' => 1, 'apple' => 2, 'cherry' => 3]);
    $result = $c->sortKeysUsing('strcmp');
    expect(array_keys($result->all()))->toBe(['apple', 'banana', 'cherry']);
});

test('sortKeysUsing() supports natural case sort', function () {
    $c = Collection::make(['img12' => 1, 'img2' => 2, 'img1' => 3]);
    $result = $c->sortKeysUsing('strnatcmp');
    expect(array_keys($result->all()))->toBe(['img1', 'img2', 'img12']);
});

test('sortKeysUsing() returns new collection (non-mutating)', function () {
    $c = Collection::make(['b' => 2, 'a' => 1]);
    $sorted = $c->sortKeysUsing('strcmp');
    expect(array_keys($c->all()))->toBe(['b', 'a']);
    expect(array_keys($sorted->all()))->toBe(['a', 'b']);
});

// =============================================================================
// standardDeviation()
// =============================================================================

test('standardDeviation() returns correct population std-dev', function () {
    $c = Collection::make([2, 4, 4, 4, 5, 5, 7, 9]);
    expect(round($c->standardDeviation(), 4))->toBe(2.0);
});

test('standardDeviation() returns correct sample std-dev', function () {
    $c = Collection::make([2, 4, 4, 4, 5, 5, 7, 9]);
    expect(round($c->standardDeviation(sample: true), 2))->toBe(2.14);
});

test('standardDeviation() returns null for single item', function () {
    expect(Collection::make([5])->standardDeviation())->toBeNull();
});

test('standardDeviation() returns null for empty collection', function () {
    expect(Collection::make([])->standardDeviation())->toBeNull();
});

test('standardDeviation() accepts dot-notation path', function () {
    $c = Collection::make([
        ['score' => 2],
        ['score' => 4],
        ['score' => 4],
        ['score' => 4],
        ['score' => 5],
        ['score' => 5],
        ['score' => 7],
        ['score' => 9],
    ]);
    expect(round($c->standardDeviation('score'), 4))->toBe(2.0);
});

// =============================================================================
// unzip()
// =============================================================================

test('unzip() splits tuples into separate collections', function () {
    $c = Collection::make([[1, 'a'], [2, 'b'], [3, 'c']]);
    [$nums, $letters] = $c->unzip();
    expect($nums->all())->toBe([1, 2, 3]);
    expect($letters->all())->toBe(['a', 'b', 'c']);
});

test('unzip() returns empty array for empty collection', function () {
    expect(Collection::make([])->unzip())->toBe([]);
});

test('unzip() fills missing positions with null', function () {
    $c = Collection::make([[1, 'a', true], [2, 'b']]);
    [$a, $b, $c2] = $c->unzip();
    expect($c2->all())->toBe([true, null]);
});

test('unzip() returns Collection instances', function () {
    $c = Collection::make([[1, 2], [3, 4]]);
    $parts = $c->unzip();
    expect($parts[0])->toBeInstanceOf(Collection::class);
});

test('unzip() is inverse of zip', function () {
    $a = Collection::make([1, 2, 3]);
    $b = [10, 20, 30];
    [$xs, $ys] = $a->zip($b)->unzip();
    expect($xs->all())->toBe([1, 2, 3]);
    expect($ys->all())->toBe([10, 20, 30]);
});

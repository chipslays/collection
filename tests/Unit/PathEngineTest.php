<?php

declare(strict_types=1);

use Collection\Collection;

// =============================================================================
// Escaping & exact keys
// =============================================================================

test('get() prefers an exact top-level key containing the delimiter', function () {
    $c = Collection::make(['example.com' => 1, 'example' => ['com' => 2]]);
    expect($c->get('example.com'))->toBe(1);
});

test('a backslash escapes the delimiter', function () {
    $c = Collection::make(['hosts' => ['example.com' => ['ip' => '1.1.1.1']]]);
    expect($c->get('hosts.example\.com.ip'))->toBe('1.1.1.1');
    expect($c->has('hosts.example\.com'))->toBeTrue();
});

test('a backslash escapes the wildcard', function () {
    $c = Collection::make(['a' => ['*' => 'star', 'b' => 'bee']]);
    expect($c->get('a.\*'))->toBe('star');
    expect($c->get('a.*'))->toBe(['*' => 'star', 'b' => 'bee']);
});

test('a backslash before other characters is kept literally', function () {
    $c = Collection::make(['App\Models\User' => 'class']);
    expect($c->get('App\Models\User'))->toBe('class');
});

test('set() and forget() work with escaped keys', function () {
    $c = Collection::make();
    $c->set('domains.example\.com', true);
    expect($c->all())->toBe(['domains' => ['example.com' => true]]);

    $c->forget('domains.example\.com');
    expect($c->all())->toBe(['domains' => []]);
});

test('integer paths are accepted', function () {
    $c = Collection::make(['a', 'b']);
    expect($c->get(1))->toBe('b');
    expect($c->has(0))->toBeTrue();
    $c->set(2, 'c');
    expect($c->all())->toBe(['a', 'b', 'c']);
});

test('a Closure default is resolved lazily', function () {
    $c = Collection::make();
    expect($c->get('missing', fn () => 'computed'))->toBe('computed');
    expect($c->get('missing', 'date'))->toBe('date');
});

// =============================================================================
// Objects, ArrayAccess and nested collections
// =============================================================================

test('get() traverses public object properties', function () {
    $c = Collection::make(['user' => (object) ['profile' => (object) ['name' => 'Alice']]]);
    expect($c->get('user.profile.name'))->toBe('Alice');
});

test('get() returns a null property instead of the default', function () {
    $c = Collection::make(['user' => (object) ['name' => null]]);
    expect($c->get('user.name', 'default'))->toBeNull();
});

test('get() traverses ArrayAccess objects', function () {
    $c = Collection::make(['bag' => new ArrayObject(['key' => 'value'])]);
    expect($c->get('bag.key'))->toBe('value');
});

test('get() traverses nested collections', function () {
    $c = Collection::make(['users' => Collection::make([['name' => 'A'], ['name' => 'B']])]);
    expect($c->get('users.1.name'))->toBe('B');
    expect($c->get('users.*.name'))->toBe(['A', 'B']);
});

test('wildcards iterate object properties', function () {
    $c = Collection::make(['map' => (object) ['a' => ['v' => 1], 'b' => ['v' => 2]]]);
    expect($c->get('map.*.v'))->toBe(['a' => 1, 'b' => 2]);
});

test('set() writes into objects and nested collections', function () {
    $user = (object) ['name' => 'Alice'];
    $inner = Collection::make(['x' => 1]);
    $c = Collection::make(['user' => $user, 'inner' => $inner]);

    $c->set('user.name', 'Bob')->set('inner.y', 2);

    expect($user->name)->toBe('Bob');
    expect($inner->all())->toBe(['x' => 1, 'y' => 2]);
});

test('forget() removes object properties', function () {
    $user = (object) ['name' => 'Alice', 'token' => 'secret'];
    Collection::make(['user' => $user])->forget('user.token');
    expect(property_exists($user, 'token'))->toBeFalse();
});

// =============================================================================
// Named wildcard
// =============================================================================

test('named wildcard keys results by a field', function () {
    $c = Collection::make(['users' => [['id' => 7, 'name' => 'A'], ['id' => 9, 'name' => 'B']]]);
    expect($c->get('users.*[id].name'))->toBe([7 => 'A', 9 => 'B']);
});

test('named wildcard key may be a nested path', function () {
    $c = Collection::make(['users' => [['meta' => ['id' => 'u1'], 'name' => 'A']]]);
    expect($c->get('users.*[meta.id].name'))->toBe(['u1' => 'A']);
});

test('named wildcard works with a custom wildcard token', function () {
    $c = new Collection(['users' => [['id' => 7, 'name' => 'A']]], '#');
    expect($c->get('users.#[id].name'))->toBe([7 => 'A']);
});

test('named wildcard as the last segment', function () {
    $c = Collection::make(['users' => [['id' => 7, 'name' => 'A']]]);
    expect($c->get('users.*[id]'))->toBe([7 => ['id' => 7, 'name' => 'A']]);
});

test('set(), has() and forget() understand named wildcards', function () {
    $c = Collection::make(['users' => [['id' => 7], ['id' => 9]]]);

    $c->set('users.*[id].active', true);
    expect($c->get('users.*.active'))->toBe([true, true]);
    expect($c->has('users.*[id].active'))->toBeTrue();

    $c->forget('users.*[id].active');
    expect($c->all())->toBe(['users' => [['id' => 7], ['id' => 9]]]);
});

// =============================================================================
// set() / has() semantics
// =============================================================================

test('set() with overwrite: false fills only missing paths', function () {
    $c = Collection::make(['settings' => ['theme' => 'light']]);
    $c->set('settings.theme', 'dark', overwrite: false)->set('settings.lang', 'ru', overwrite: false);
    expect($c->all())->toBe(['settings' => ['theme' => 'light', 'lang' => 'ru']]);
});

test('has() accepts several paths, all of which must exist', function () {
    $c = Collection::make(['a' => 1, 'b' => null]);
    expect($c->has('a', 'b'))->toBeTrue();
    expect($c->has(['a', 'b']))->toBeTrue();
    expect($c->has('a', 'c'))->toBeFalse();
});

test('isset() requires a non-null value, has() only the key', function () {
    $c = Collection::make(['user' => ['deleted_at' => null]]);
    expect($c->has('user.deleted_at'))->toBeTrue();
    expect(isset($c['user.deleted_at']))->toBeFalse();
});

// =============================================================================
// Path-aware transformations
// =============================================================================

test('mapPath() applies the callback to every wildcard match', function () {
    $c = Collection::make([['tags' => [['n' => 'x'], ['n' => 'y']]]]);
    expect($c->mapPath('tags.*.n', strtoupper(...))->get('0.tags.*.n'))->toBe(['X', 'Y']);
});

test('mapPath() clones objects instead of mutating the source', function () {
    $item = (object) ['price' => 10];
    $c = Collection::make([$item]);

    $result = $c->mapPath('price', fn ($p) => $p * 2);

    expect($result->first()->price)->toBe(20);
    expect($item->price)->toBe(10);
});

test('mapPath() leaves missing paths untouched', function () {
    $c = Collection::make([['a' => 1], ['b' => 2]]);
    expect($c->mapPath('a', fn ($v) => $v + 1)->all())->toBe([['a' => 2], ['b' => 2]]);
});

test('only() keeps nested paths and wildcards', function () {
    $c = Collection::make([
        'user' => ['name' => 'A', 'password' => 'x'],
        'users' => [['email' => 'a@x', 'pw' => 1], ['email' => 'b@x', 'pw' => 2]],
        'meta' => 1,
    ]);

    expect($c->only('user.name', 'users.*.email')->all())->toBe([
        'user' => ['name' => 'A'],
        'users' => [['email' => 'a@x'], ['email' => 'b@x']],
    ]);
});

test('except() removes nested paths without touching the original', function () {
    $c = Collection::make(['user' => ['name' => 'A', 'password' => 'x'], 'token' => 't']);

    expect($c->except('user.password', 'token')->all())->toBe(['user' => ['name' => 'A']]);
    expect($c->get('user.password'))->toBe('x');
});

test('except() clones objects on the path', function () {
    $user = (object) ['name' => 'A', 'password' => 'x'];
    $c = Collection::make(['user' => $user]);

    $result = $c->except('user.password');

    expect(property_exists($result->get('user'), 'password'))->toBeFalse();
    expect($user->password)->toBe('x');
});

test('select() picks paths from every item and keeps keys', function () {
    $c = Collection::make(['a' => ['id' => 1, 'user' => ['email' => 'e', 'pw' => 'p'], 'x' => 0]]);
    expect($c->select('id', 'user.email')->all())->toBe(['a' => ['id' => 1, 'user' => ['email' => 'e']]]);
});

test('dot() keeps empty arrays and undot() restores them', function () {
    $c = Collection::make(['a' => [], 'b' => ['c' => 1]]);
    expect($c->dot()->all())->toBe(['a' => [], 'b.c' => 1]);
    expect($c->dot()->undot()->all())->toBe(['a' => [], 'b' => ['c' => 1]]);
});

test('undot() does not treat the wildcard specially', function () {
    expect(Collection::make(['a.*' => 1])->undot()->all())->toBe(['a' => ['*' => 1]]);
});

// =============================================================================
// Constructor validation
// =============================================================================

test('constructor rejects an empty delimiter or wildcard', function () {
    expect(fn () => new Collection([], '*', ''))->toThrow(InvalidArgumentException::class);
    expect(fn () => new Collection([], '', '.'))->toThrow(InvalidArgumentException::class);
});

test('constructor rejects overlapping wildcard and delimiter', function () {
    expect(fn () => new Collection([], '.', '.'))->toThrow(InvalidArgumentException::class);
});

test('constructor rejects a backslash token', function () {
    expect(fn () => new Collection([], '*', '\\'))->toThrow(InvalidArgumentException::class);
});

test('multi-character delimiters work', function () {
    $c = new Collection(['a' => ['b' => ['c' => 1]]], '*', '->');
    expect($c->get('a->b->c'))->toBe(1);
    expect($c->get('a->*->c'))->toBe(['b' => 1]);
});

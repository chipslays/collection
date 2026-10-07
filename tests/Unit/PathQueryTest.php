<?php

declare(strict_types=1);

use Collection\Collection;

function queryData(): Collection
{
    return Collection::make([
        'users' => [
            ['id' => 1, 'name' => 'Alice', 'age' => 31, 'role' => 'admin', 'email' => 'a@x', 'profile' => ['email' => 'alice@home']],
            ['id' => 2, 'name' => 'Bob', 'age' => 17, 'role' => 'user', 'email' => 'b@x'],
            ['id' => 3, 'name' => 'Carol', 'age' => 25, 'role' => 'user', 'email' => 'c@x', 'banned' => true],
        ],
        'owner' => ['email' => 'owner@x'],
    ]);
}

// =============================================================================
// Filters
// =============================================================================

test('filter selects children by a comparison', function () {
    expect(queryData()->get('users[?age>=18].name'))->toBe([0 => 'Alice', 2 => 'Carol']);
});

test('filter supports truthy, negation, && and ||', function () {
    $data = queryData();
    expect($data->get('users[?banned].name'))->toBe([2 => 'Carol']);
    expect($data->get('users[?!banned].name'))->toBe(['Alice', 'Bob']);
    expect($data->get('users[?role="user" && !banned].name'))->toBe([1 => 'Bob']);
    expect($data->get('users[?role=admin || age<18].name'))->toBe(['Alice', 'Bob']);
});

test('filter values: numbers, booleans, null, quoted strings', function () {
    $c = Collection::make([
        ['n' => 1, 'b' => true, 'x' => null, 's' => 'a b'],
        ['n' => 2, 'b' => false, 'x' => 1, 's' => 'a]b'],
    ]);
    expect($c->get('[?n===2].n'))->toBe([1 => 2]);
    expect($c->get('[?b=false].n'))->toBe([1 => 2]);
    expect($c->get('[?x=null].n'))->toBe([0 => 1]);
    expect($c->get("[?s='a b'].n"))->toBe([0 => 1]);
    expect($c->get('[?s="a]b"].n'))->toBe([1 => 2]);
});

test('filter path inside the condition may be nested', function () {
    expect(queryData()->get('users[?profile.email].name'))->toBe(['Alice']);
});

test('filter combines with a named wildcard', function () {
    expect(queryData()->get('users.*[id][?age>20].name'))->toBe([1 => 'Alice', 3 => 'Carol']);
});

test('filter without matches returns the default', function () {
    expect(queryData()->get('users[?age>100].name', 'none'))->toBe('none');
});

test('filter works in set, forget, has and mapPath', function () {
    $data = queryData();

    expect($data->copy()->set('users[?age<18].minor', true)->get('users.*.minor'))->toBe([1 => true]);
    expect($data->copy()->forget('users[?banned]')->get('users.*.name'))->toBe(['Alice', 'Bob']);
    expect($data->has('users[?role=admin]'))->toBeTrue();
    expect($data->has('users[?role=root]'))->toBeFalse();
    expect(Collection::make([$data->all()])->mapPath('users[?role=user].name', strtoupper(...))->get('0.users.*.name'))
        ->toBe(['Alice', 'BOB', 'CAROL']);
});

test('filter is usable in item methods like pluck() and where()', function () {
    $orders = Collection::make([
        ['id' => 1, 'items' => [['sku' => 'A', 'qty' => 0], ['sku' => 'B', 'qty' => 2]]],
        ['id' => 2, 'items' => [['sku' => 'C', 'qty' => 0]]],
    ]);

    expect($orders->pluck('items[?qty>0].sku')->all())->toBe([[1 => 'B'], null]);
    expect($orders->where('items[?qty>0]')->keys()->all())->toBe([0]);
});

test('a literal bracket in a key still works and can be escaped', function () {
    expect(Collection::make(['a' => ['b[1]' => 2]])->get('a.b[1]'))->toBe(2);
    expect(Collection::make(['a' => ['[?x]' => 3]])->get('a.\[?x]'))->toBe(3);
});

test('an empty condition throws', function () {
    expect(fn () => queryData()->get('users[?].name'))->toThrow(InvalidArgumentException::class);
});

// =============================================================================
// Recursive descent
// =============================================================================

test('** finds a key at any depth, keyed by full path', function () {
    expect(queryData()->get('**.email'))->toBe([
        'users.0.email' => 'a@x',
        'users.0.profile.email' => 'alice@home',
        'users.1.email' => 'b@x',
        'users.2.email' => 'c@x',
        'owner.email' => 'owner@x',
    ]);
});

test('** result keys are escaped paths that read back with get()', function () {
    $c = Collection::make(['hosts' => ['example.com' => ['ip' => '1.1.1.1']]]);
    $hits = $c->get('**.ip');

    expect(array_keys($hits))->toBe(['hosts.example\.com.ip']);
    expect($c->get(array_key_first($hits)))->toBe('1.1.1.1');
});

test('** with a filter', function () {
    expect(queryData()->get('**[?role=admin].name'))->toBe(['users.0.name' => 'Alice']);
});

test('** works in has, set, forget, only and mapPath', function () {
    $data = queryData();

    expect($data->has('**.profile.email'))->toBeTrue();
    expect($data->has('**.nope'))->toBeFalse();
    expect(array_unique($data->copy()->set('**.email', '***')->get('**.email')))->toBe(['users.0.email' => '***']);
    expect($data->copy()->forget('**.email')->has('**.email'))->toBeFalse();
    expect($data->only('**.profile')->all())->toBe(['users' => [['profile' => ['email' => 'alice@home']]]]);
    expect($data->mapPath('**.email', strtoupper(...))->get('owner.email'))->toBe('OWNER@X');
});

test('** only overwrites existing paths', function () {
    $c = Collection::make(['a' => ['x' => 1], 'b' => []]);
    expect($c->set('**.x', 2)->all())->toBe(['a' => ['x' => 2], 'b' => []]);
});

test('** goes through objects and stops on cycles', function () {
    $node = new stdClass;
    $node->name = 'root';
    $node->self = $node;

    expect(Collection::make(['n' => $node])->get('**.name'))->toBe(['n.name' => 'root']);
});

test('** with a custom wildcard', function () {
    $c = new Collection(['a' => ['b' => ['id' => 1]]], '#');
    expect($c->get('##.id'))->toBe(['a.b.id' => 1]);
});

// =============================================================================
// Fast paths keep exact semantics
// =============================================================================

test('fast paths fall back correctly for objects and collections', function () {
    $c = Collection::make([
        ['user' => ['name' => 'array']],
        ['user' => (object) ['name' => 'object']],
        ['user' => Collection::make(['name' => 'collection'])],
        (object) ['user' => ['name' => 'object item']],
        ['user' => null],
        'scalar',
    ]);

    expect($c->pluck('user.name')->all())->toBe(['array', 'object', 'collection', 'object item', null, null]);
    expect($c->get('*.user.name'))->toBe(['array', 'object', 'collection', 'object item']);
    expect($c->where('user.name', 'object')->keys()->all())->toBe([1]);
    expect($c->groupBy('user.name')->keys()->all())->toBe(['array', 'object', 'collection', 'object item', '']);
});

test('fast paths do not index strings by numeric keys', function () {
    $c = Collection::make([['code' => 'abc'], ['code' => ['x', 'y']]]);
    expect($c->pluck('code.0')->all())->toBe([null, 'x']);
    expect($c->get('*.code.0'))->toBe([1 => 'x']);
});

test('fast paths respect escaped segments inside nested collections', function () {
    $c = Collection::make([['hosts' => Collection::make(['a.b' => 1, 'a' => ['b' => 2]])]]);
    expect($c->pluck('hosts.a\.b')->all())->toBe([1]);
    expect($c->pluck('hosts.a.b')->all())->toBe([2]);
});

test('fast paths keep null values distinct from missing in get()', function () {
    $c = Collection::make([['a' => null], ['b' => 1]]);
    expect($c->get('*.a'))->toBe([0 => null]);
});

test('where() compares objects with Laravel rules on the fast path', function () {
    $c = Collection::make([['v' => new ArrayObject], ['v' => 'x']]);
    expect($c->where('v', '!=', 'x')->keys()->all())->toBe([0]);
    expect($c->where('v', 'x')->keys()->all())->toBe([1]);
});

test('unique() hash path matches in_array semantics', function () {
    expect(Collection::make([1, '1', 1.0, '01', 'a', 'A'])->unique(fn ($v) => $v)->all())->toBe([0 => 1, 4 => 'a', 5 => 'A']);
    expect(Collection::make([1, '1', 1.0, 'a', 'a', null, null])->unique(strict: true)->all())->toBe([0 => 1, 1 => '1', 2 => 1.0, 3 => 'a', 5 => null]);
});

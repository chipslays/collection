<?php

declare(strict_types=1);

use Collection\Collection;
use Collection\Contracts\Caster;

enum CastStatus: string
{
    case Active = 'active';
    case Banned = 'banned';
}

enum CastSize
{
    case S;
    case M;
}

final class CastMoney
{
    public function __construct(public int $cents, public string $currency = 'EUR') {}
}

final class CastMoneyCaster implements Caster
{
    public function __construct(private string $currency) {}

    public function cast(mixed $value, mixed $item, string|int $key): mixed
    {
        return new CastMoney((int) round($value * 100), $this->currency);
    }
}

afterEach(fn () => (fn () => static::$casters = [])->call(Collection::make()));

// =============================================================================
// rename()
// =============================================================================

test('rename() renames a nested key in place, keeping its position', function () {
    $c = Collection::make(['user' => ['mail' => 'a@x', 'name' => 'A']]);
    expect($c->rename('user.mail', 'user.email')->get('user'))->toBe(['email' => 'a@x', 'name' => 'A']);
});

test('rename() moves a value to another branch', function () {
    $c = Collection::make(['user' => ['age' => 3, 'name' => 'A']]);
    expect($c->rename('user.age', 'meta.age')->all())->toBe(['user' => ['name' => 'A'], 'meta' => ['age' => 3]]);
});

test('rename() maps wildcards onto the target', function () {
    $c = Collection::make(['users' => [['mail' => 'a'], ['mail' => 'b']]]);
    expect($c->rename('users.*.mail', 'users.*.email')->all())->toBe(['users' => [['email' => 'a'], ['email' => 'b']]]);
    expect($c->rename('users.*.mail', 'emails.*')->all())->toBe(['users' => [[], []], 'emails' => ['a', 'b']]);
});

test('rename() with ** and filters', function () {
    $c = Collection::make(['a' => ['e_mail' => 1, 'b' => ['e_mail' => 2]]]);
    expect($c->rename('**.e_mail', '**.email')->all())->toBe(['a' => ['email' => 1, 'b' => ['email' => 2]]]);

    $users = Collection::make(['users' => [['n' => 'a', 'old' => true], ['n' => 'b']]]);
    expect($users->rename('users[?old].n', 'users.*.legacy_name')->get('users'))->toBe([['legacy_name' => 'a', 'old' => true], ['n' => 'b']]);
});

test('rename() accepts a map and returns a new collection', function () {
    $c = Collection::make(['id' => 1, 'mail' => 'x']);
    expect($c->rename(['id' => 'uuid', 'mail' => 'email'])->all())->toBe(['uuid' => 1, 'email' => 'x']);
    expect($c->all())->toBe(['id' => 1, 'mail' => 'x']);
});

test('rename() ignores missing sources', function () {
    expect(Collection::make(['a' => 1])->rename('b', 'c')->all())->toBe(['a' => 1]);
});

test('rename() throws when wildcards do not line up', function () {
    $c = Collection::make(['users' => [['mail' => 'a']]]);
    expect(fn () => $c->rename('users.*.mail', 'emails'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $c->rename('users.0.mail', 'x.*'))->toThrow(InvalidArgumentException::class);
});

test('rename() clones objects instead of mutating them', function () {
    $user = (object) ['mail' => 'a'];
    $c = Collection::make(['user' => $user]);

    expect($c->rename('user.mail', 'user.email')->get('user.email'))->toBe('a');
    expect(property_exists($user, 'mail'))->toBeTrue();
});

// =============================================================================
// remap()
// =============================================================================

test('remap() builds a new structure from paths and callbacks', function () {
    $rows = Collection::make([
        'u7' => [
            'user_id' => 7,
            'profile' => ['full_name' => 'Alice'],
            'addresses' => [['city' => 'Berlin']],
            'roles' => [['slug' => 'admin'], ['slug' => 'dev']],
        ],
    ]);

    expect($rows->remap([
        'id' => 'user_id',
        'name' => 'profile.full_name',
        'address.city' => 'addresses.0.city',
        'tags' => 'roles.*.slug',
        'label' => fn ($u, $key) => "{$key}:{$u['user_id']}",
        'missing' => 'nope',
    ])->all())->toBe([
        'u7' => [
            'id' => 7,
            'name' => 'Alice',
            'address' => ['city' => 'Berlin'],
            'tags' => ['admin', 'dev'],
            'label' => 'u7:7',
            'missing' => null,
        ],
    ]);
});

test('remap() treats target paths literally', function () {
    expect(Collection::make([['a' => 1]])->remap(['x.*' => 'a'])->first())->toBe(['x' => ['*' => 1]]);
});

// =============================================================================
// cast()
// =============================================================================

test('cast() converts scalars', function () {
    $c = Collection::make([['i' => '3', 'f' => '1.5', 's' => 12, 'b' => 'false', 'y' => 'yes', 'z' => 0]]);

    expect($c->cast(['i' => 'int', 'f' => 'float', 's' => 'string', 'b' => 'bool', 'y' => 'bool', 'z' => 'boolean'])->first())
        ->toBe(['i' => 3, 'f' => 1.5, 's' => '12', 'b' => false, 'y' => true, 'z' => false]);
});

test('cast() parameters: float precision and decimal', function () {
    $c = Collection::make([['p' => '12.3456']]);
    expect($c->cast('p', 'float:2')->first()['p'])->toBe(12.35);
    expect($c->cast('p', 'decimal:1')->first()['p'])->toBe('12.3');
});

test('cast() array, json, object and collection', function () {
    $c = Collection::make([['a' => '["x"]', 'j' => '{"k":1}', 'o' => ['k' => 1], 'c' => [1, 2]]]);
    $r = $c->cast(['a' => 'array', 'j' => 'json', 'o' => 'object', 'c' => 'collection'])->first();

    expect($r['a'])->toBe(['x']);
    expect($r['j'])->toBe(['k' => 1]);
    expect($r['o'])->toBeInstanceOf(stdClass::class)->and($r['o']->k)->toBe(1);
    expect($r['c'])->toBeInstanceOf(Collection::class)->and($r['c']->all())->toBe([1, 2]);
});

test('cast() dates', function () {
    $c = Collection::make([['at' => '2026-10-07 10:30', 'd' => '07.10.2026', 'ts' => 0]]);
    $r = $c->cast(['at' => 'datetime', 'd' => 'date:d.m.Y', 'ts' => 'datetime'])->first();

    expect($r['at'])->toBeInstanceOf(DateTimeImmutable::class)->and($r['at']->format('H:i'))->toBe('10:30');
    expect($r['d']->format('Y-m-d H:i'))->toBe('2026-10-07 00:00');
    expect($r['ts']->getTimestamp())->toBe(0);
    expect($c->cast('d', 'datetime:d.m.Y')->first()['d']->format('H:i:s'))->toBe('00:00:00');
    expect($c->cast('at', 'timestamp')->first()['at'])->toBeInt();
    expect(get_class($c->cast('at', DateTime::class)->first()['at']))->toBe(DateTime::class);
    expect(fn () => $c->cast('d', 'date:Y-m-d'))->toThrow(InvalidArgumentException::class);
});

test('cast() enums and classes', function () {
    $c = Collection::make([['s' => 'active', 'z' => 'M', 'bad' => 'zzz', 'm' => 5]]);
    $r = $c->cast(['s' => CastStatus::class, 'z' => CastSize::class, 'bad' => CastStatus::class.':try', 'm' => CastMoney::class])->first();

    expect($r['s'])->toBe(CastStatus::Active);
    expect($r['z'])->toBe(CastSize::M);
    expect($r['bad'])->toBeNull();
    expect($r['m'])->toBeInstanceOf(CastMoney::class)->and($r['m']->cents)->toBe(5);
    expect(fn () => $c->cast('bad', CastStatus::class))->toThrow(ValueError::class);
});

test('cast() with callables, Caster objects, functions and chains', function () {
    $c = Collection::make(['k' => ['e' => '  A@X.COM ', 'p' => 9.99, 'n' => 2, 'w' => '  hi ']]);
    $r = $c->cast([
        'e' => ['trim', 'strtolower'],
        'p' => new CastMoneyCaster('USD'),
        'n' => fn ($v, $item, $key) => "{$key}:".($v * $item['p']),
        'w' => trim(...),
    ])->first();

    expect($r['e'])->toBe('a@x.com');
    expect($r['p'])->toBeInstanceOf(CastMoney::class)->and($r['p']->cents)->toBe(999)->and($r['p']->currency)->toBe('USD');
    expect($r['n'])->toBe('k:19.98');
    expect($r['w'])->toBe('hi');
});

test('cast() with a registered caster and arguments', function () {
    Collection::castUsing('cents', fn ($v, $item, $key, $multiplier = '100') => (int) round($v * (int) $multiplier));
    Collection::castUsing('usd', new CastMoneyCaster('USD'));

    $c = Collection::make([['p' => 1.5]]);
    expect($c->cast('p', 'cents')->first()['p'])->toBe(150);
    expect($c->cast('p', 'cents:1000')->first()['p'])->toBe(1500);
    expect($c->cast('p', 'usd')->first()['p']->currency)->toBe('USD');
});

test('cast() keeps null for named casters and skips missing paths', function () {
    $c = Collection::make([['a' => null], ['b' => '1']]);
    expect($c->cast(['a' => 'int', 'b' => 'int'])->all())->toBe([['a' => null], ['b' => 1]]);
});

test('cast() works with wildcards, filters and **', function () {
    $c = Collection::make([['items' => [['q' => '2', 'on' => true], ['q' => '5']], 'meta' => ['q' => '1']]]);

    expect($c->cast('items.*.q', 'int')->get('0.items.*.q'))->toBe([2, 5]);
    expect($c->cast('items[?on].q', 'int')->get('0.items.*.q'))->toBe([2, '5']);
    expect($c->cast('**.q', 'int')->get('0.meta.q'))->toBe(1);
});

test('cast() clones objects and does not mutate the source', function () {
    $item = (object) ['n' => '1'];
    $r = Collection::make([$item])->cast('n', 'int');

    expect($r->first()->n)->toBe(1);
    expect($item->n)->toBe('1');
});

test('cast() rejects unknown casters', function () {
    expect(fn () => Collection::make([['a' => 1]])->cast('a', 'nope'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Collection::make([['a' => 1]])->cast('a', 42))->toThrow(InvalidArgumentException::class);
});

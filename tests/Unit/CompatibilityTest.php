<?php

declare(strict_types=1);

use Collection\Collection;
use Collection\Exceptions\ItemNotFoundException;
use Collection\Exceptions\MultipleItemsFoundException;
use Random\Engine\Mt19937;
use Random\Randomizer;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class Money
{
    public function __construct(public int $amount, public mixed $key = null) {}
}

// =============================================================================
// Creation
// =============================================================================

test('make() decodes JSON arrays and wraps other strings', function () {
    expect(Collection::make('{"a":1}')->all())->toBe(['a' => 1]);
    expect(Collection::make('hello')->all())->toBe(['hello']);
    expect(Collection::make('5')->all())->toBe(['5']);
});

test('fromJson() rejects scalar JSON and passes depth/flags', function () {
    expect(fn () => Collection::fromJson('5'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Collection::fromJson('[[1]]', depth: 1))->toThrow(JsonException::class);
    expect(Collection::fromJson('[12345678901234567890]', flags: JSON_BIGINT_AS_STRING)->first())
        ->toBe('12345678901234567890');
});

test('collection() helper accepts JSON and iterables', function () {
    expect(collection('[1,2]')->all())->toBe([1, 2]);
    expect(collection(new ArrayIterator(['a' => 1]))->all())->toBe(['a' => 1]);
});

test('constructor accepts collections and traversables', function () {
    expect((new Collection(Collection::make([1])))->all())->toBe([1]);
    expect((new Collection(null))->all())->toBe([]);
});

test('empty() and times() without callback', function () {
    expect(Collection::empty()->all())->toBe([]);
    expect(Collection::times(3)->all())->toBe([1, 2, 3]);
});

test('toBase() returns a plain Collection', function () {
    $sub = new class([1]) extends Collection {};
    expect(get_class($sub->toBase()))->toBe(Collection::class);
});

// =============================================================================
// Callbacks
// =============================================================================

test('map() passes the key and preserves keys', function () {
    expect(Collection::make(['a' => 1])->map(fn ($v, $k) => "$k$v")->all())->toBe(['a' => 'a1']);
});

test('built-in functions receive only the value', function () {
    $c = Collection::make(['x' => ' a ', 'y' => ' b ']);
    expect($c->map('trim')->all())->toBe(['x' => 'a', 'y' => 'b']);
    expect($c->map(trim(...))->all())->toBe(['x' => 'a', 'y' => 'b']);
    expect(Collection::make(['10', '0x1A'])->map(intval(...))->all())->toBe([10, 0]);
});

test('reduce() passes the key', function () {
    expect(Collection::make(['a' => 1, 'b' => 2])->reduce(fn ($carry, $v, $k) => $carry.$k.$v, ''))->toBe('a1b2');
});

test('reduceSpread() reduces to several values', function () {
    [$sum, $count] = Collection::make([1, 2, 3])->reduceSpread(fn ($sum, $count, $v) => [$sum + $v, $count + 1], 0, 0);
    expect([$sum, $count])->toBe([6, 3]);
    expect(fn () => Collection::make([1])->reduceSpread(fn () => 1))->toThrow(UnexpectedValueException::class);
});

test('mapSpread() and eachSpread() pass the key last', function () {
    expect(Collection::make([[1, 2]])->mapSpread(fn ($a, $b, $k) => [$a + $b, $k])->all())->toBe([[3, 0]]);
});

test('mapInto() supports backed enums and passes the key', function () {
    expect(Collection::make(['active'])->mapInto(Status::class)->first())->toBe(Status::Active);
    expect(Collection::make(['k' => 5])->mapInto(Money::class)->first()->key)->toBe('k');
});

// =============================================================================
// Laravel methods added
// =============================================================================

test('add() and unshift()', function () {
    $c = Collection::make([2]);
    $c->add(3)->unshift(0, 1);
    expect($c->all())->toBe([0, 1, 2, 3]);
});

test('push() accepts several values', function () {
    expect(Collection::make()->push(1, 2, 3)->all())->toBe([1, 2, 3]);
});

test('pop() and shift() with a count', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->pop(2)->all())->toBe([4, 3]);
    expect($c->shift(2)->all())->toBe([1, 2]);
    expect($c->isEmpty())->toBeTrue();
});

test('collapseWithKeys() keeps keys', function () {
    expect(Collection::make([['a' => 1], ['b' => 2]])->collapseWithKeys()->all())->toBe(['a' => 1, 'b' => 2]);
});

test('mapToDictionary() groups values into plain arrays', function () {
    $c = Collection::make([['d' => 'x', 'n' => 1], ['d' => 'x', 'n' => 2]]);
    expect($c->mapToDictionary(fn ($i) => [$i['d'] => $i['n']])->all())->toBe(['x' => [1, 2]]);
});

test('diffAssocUsing(), diffKeysUsing() and intersectAssocUsing()', function () {
    $c = Collection::make(['a' => 1, 'B' => 2]);
    expect($c->diffKeysUsing(['b' => 9], 'strcasecmp')->all())->toBe(['a' => 1]);
    expect($c->diffAssocUsing(['b' => 2], 'strcasecmp')->all())->toBe(['a' => 1]);
    expect($c->intersectAssocUsing(['b' => 2], 'strcasecmp')->all())->toBe(['B' => 2]);
});

test('hasSole(), hasMany() and their deprecated aliases', function () {
    $c = Collection::make([1, 2, 2]);
    expect($c->hasSole(fn ($v) => $v === 1))->toBeTrue();
    expect($c->hasMany(fn ($v) => $v === 2))->toBeTrue();
    expect($c->containsOneItem())->toBeFalse();
    expect($c->containsManyItems())->toBeTrue();
});

test('sole() and firstOrFail() throw the dedicated exceptions', function () {
    $c = Collection::make([['id' => 1], ['id' => 1]]);
    expect(fn () => $c->sole('id', 2))->toThrow(ItemNotFoundException::class);
    expect(fn () => $c->sole('id', 1))->toThrow(MultipleItemsFoundException::class);
    expect(fn () => $c->firstOrFail('id', 5))->toThrow(ItemNotFoundException::class);
    expect(fn () => $c->firstOrFail('id', 5))->toThrow(RuntimeException::class);
});

test('partition() returns a collection that can be destructured', function () {
    [$even, $odd] = Collection::make([1, 2, 3, 4])->partition(fn ($n) => $n % 2 === 0);
    expect($even->all())->toBe([1 => 2, 3 => 4]);
    expect($odd->all())->toBe([0 => 1, 2 => 3]);
});

test('value() reads from the first item having the path', function () {
    $c = Collection::make([['a' => 1], ['b' => 2], ['b' => 3]]);
    expect($c->value('b'))->toBe(2);
    expect($c->value('z', 'none'))->toBe('none');
});

// =============================================================================
// where()
// =============================================================================

test('where() supports every operator', function () {
    $c = Collection::make([['n' => 1], ['n' => 2], ['n' => 3]]);
    expect($c->where('n', '>', 1)->count())->toBe(2);
    expect($c->where('n', '<=>', 2)->count())->toBe(2);
    expect($c->where('n', '!==', 2)->count())->toBe(2);
});

test('where() throws on an unknown operator', function () {
    expect(fn () => Collection::make([['a' => 1]])->where('a', 'like', 1))
        ->toThrow(InvalidArgumentException::class);
});

test('where() accepts a callable', function () {
    expect(Collection::make([1, 2, 3])->where(fn ($v) => $v > 1)->values()->all())->toBe([2, 3]);
});

test('whereMatches() validates the pattern and passes flags', function () {
    $c = Collection::make([['s' => 'abc'], ['s' => 'xyz']]);
    expect($c->whereMatches('s', '/^a/')->count())->toBe(1);
    expect($c->whereMatches('s', '/c$/', offset: 2)->count())->toBe(1);
    expect(fn () => $c->whereMatches('s', '/('))->toThrow(InvalidArgumentException::class);
});

test('whereNull() treats a missing path as null', function () {
    expect(Collection::make([['a' => null], ['b' => 1]])->whereNull('a')->count())->toBe(2);
});

// =============================================================================
// Grouping & keys
// =============================================================================

test('groupBy() and keyBy() normalise enum keys', function () {
    $c = Collection::make([['s' => Status::Active], ['s' => Status::Inactive]]);
    expect($c->groupBy('s')->keys()->all())->toBe(['active', 'inactive']);
    expect($c->keyBy('s')->keys()->all())->toBe(['active', 'inactive']);
});

test('groupBy() can preserve keys', function () {
    $c = Collection::make(['x' => ['g' => 1], 'y' => ['g' => 1]]);
    expect($c->groupBy('g', preserveKeys: true)->first()->keys()->all())->toBe(['x', 'y']);
});

test('toArray() converts nested collections recursively', function () {
    $c = Collection::make(['a' => Collection::make(['b' => Collection::make([1])]), 'c' => [Collection::make([2])]]);
    expect($c->toArray())->toBe(['a' => ['b' => [1]], 'c' => [[2]]]);
    expect($c->all()['a'])->toBeInstanceOf(Collection::class);
});

test('copy() clones nested collections', function () {
    $inner = Collection::make([1]);
    $copy = Collection::make(['x' => $inner])->copy();
    $copy->get('x')->push(2);
    expect($inner->all())->toBe([1]);
});

// =============================================================================
// Higher-order messages
// =============================================================================

test('higher-order messages read paths', function () {
    $c = Collection::make([['stats' => ['score' => 2]], ['stats' => ['score' => 3]]]);
    expect($c->sum->{'stats.score'})->toBe(5);
    expect($c->map->stats->all())->toBe([['score' => 2], ['score' => 3]]);
});

test('higher-order messages call methods', function () {
    $c = Collection::make([Collection::make([1, 2]), Collection::make([3])]);
    expect($c->map->count()->all())->toBe([2, 1]);
});

test('unknown properties throw', function () {
    expect(fn () => Collection::make()->nope)->toThrow(Exception::class);
});

// =============================================================================
// Slicing & chunking
// =============================================================================

test('sliding() validates its arguments', function () {
    expect(fn () => Collection::make([1])->sliding(2, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => Collection::make([1])->sliding(0))->toThrow(InvalidArgumentException::class);
    expect(Collection::make([1, 2, 3])->sliding()->toArray())->toBe([[1, 2], [1 => 2, 2 => 3]]);
});

test('nth() validates the step', function () {
    expect(fn () => Collection::make([1])->nth(0))->toThrow(InvalidArgumentException::class);
});

test('split() distributes the remainder like Laravel', function () {
    expect(Collection::make(range(1, 10))->split(4)->map->count()->all())->toBe([3, 3, 2, 2]);
    expect(Collection::make(range(1, 5))->split(4)->count())->toBe(4);
    expect(fn () => Collection::make([1])->split(0))->toThrow(InvalidArgumentException::class);
});

test('random() returns an item for null, a collection for a number', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->random())->toBeInt();
    expect($c->random(1))->toBeInstanceOf(Collection::class);
    expect($c->random(0)->all())->toBe([]);
    expect($c->random(fn () => 2)->count())->toBe(2);
    expect(fn () => $c->random(4))->toThrow(InvalidArgumentException::class);
});

test('shuffle() and random() accept a seeded Randomizer', function () {
    $c = Collection::make(range(1, 20));
    $a = $c->shuffle(new Randomizer(new Mt19937(42)))->all();
    $b = $c->shuffle(new Randomizer(new Mt19937(42)))->all();
    expect($a)->toBe($b);

    expect($c->random(3, preserveKeys: true, randomizer: new Randomizer(new Mt19937(1)))->all())
        ->toBe($c->random(3, preserveKeys: true, randomizer: new Randomizer(new Mt19937(1)))->all());
});

// =============================================================================
// Aggregates & formatting
// =============================================================================

test('min()/max()/median() ignore nulls', function () {
    $c = Collection::make([['p' => null], ['p' => 5], ['p' => 3]]);
    expect($c->min('p'))->toBe(3);
    expect($c->max('p'))->toBe(5);
    expect($c->median('p'))->toBe(4);
});

test('mode() works with any values and returns null when empty', function () {
    expect(Collection::make([[1], [1], [2]])->mode())->toBe([[1]]);
    expect(Collection::make()->mode())->toBeNull();
});

test('percentage() accepts a path and a rounding mode', function () {
    $c = Collection::make([['a' => true], ['a' => false], ['a' => false]]);
    expect($c->percentage('a', 0))->toBe(33.0);
    expect($c->percentage('a', 1, PHP_ROUND_HALF_DOWN))->toBe(33.3);
    expect(Collection::make()->percentage('a'))->toBeNull();
});

test('toJson() passes depth', function () {
    expect(fn () => Collection::make([[1]])->toJson(depth: 1))->toThrow(JsonException::class);
});

test('escapeWhenCastingToString() escapes HTML', function () {
    expect((string) Collection::make(['<b>'])->escapeWhenCastingToString())->toBe('[&quot;&lt;b&gt;&quot;]');
});

test('join() and implode() follow Laravel', function () {
    expect(Collection::make(['a', 'b', 'c'])->join(', ', ' and '))->toBe('a, b and c');
    expect(Collection::make([['n' => 'a'], ['n' => 'b']])->implode('n'))->toBe('ab');
    expect(Collection::make([['n' => 'a'], ['n' => 'b']])->implode(fn ($i) => strtoupper($i['n']), '-'))->toBe('A-B');
});

test('ensure() supports more types', function () {
    expect(Collection::make([new stdClass])->ensure('object')->count())->toBe(1);
    expect(Collection::make([[1]])->ensure(['iterable'])->count())->toBe(1);
    expect(fn () => Collection::make([1])->ensure('string'))->toThrow(UnexpectedValueException::class);
});

// =============================================================================
// Trees
// =============================================================================

test('toTree() compares ids loosely and cuts cycles', function () {
    $c = Collection::make([
        ['id' => 1, 'parent_id' => '0'],
        ['id' => 2, 'parent_id' => 1],
        ['id' => 3, 'parent_id' => 4],
        ['id' => 4, 'parent_id' => 3],
    ]);

    $tree = $c->toTree(rootId: 0);
    expect($tree->count())->toBe(1);
    expect($tree->get('0.children.0.id'))->toBe(2);
});

test('toTree() can promote orphans to roots', function () {
    $c = Collection::make([['id' => 1, 'parent_id' => 99]]);
    expect($c->toTree()->count())->toBe(0);
    expect($c->toTree(orphansAsRoots: true)->count())->toBe(1);
});

// =============================================================================
// Macros
// =============================================================================

test('subclasses inherit macros and may override them', function () {
    Collection::macro('greet', fn () => 'parent');
    $sub = new class extends Collection {};

    expect($sub->greet())->toBe('parent');

    $sub::macro('greet', fn () => 'child');
    expect($sub->greet())->toBe('child');
    expect(Collection::make()->greet())->toBe('parent');

    Collection::flushMacros();
    $sub::flushMacros();
});

test('static closures and first-class callables work as macros', function () {
    Collection::macro('staticOne', static fn () => 'static');
    Collection::macro('upper', strtoupper(...));

    expect(Collection::make()->staticOne())->toBe('static');
    expect(Collection::make()->upper('a'))->toBe('A');

    Collection::flushMacros();
});

<?php

declare(strict_types=1);

namespace Collection\Concerns;

use BackedEnum;
use Closure;
use Collection\Contracts\Caster;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Stringable;
use UnitEnum;

/**
 * Reshaping data: rename/move paths, remap items to a new structure, cast values.
 */
trait ReshapesItems
{
    /**
     * Custom casters registered with castUsing().
     *
     * @var array<string, callable|Caster>
     */
    protected static array $casters = [];

    // -----------------------------------------------------------------
    // rename()
    // -----------------------------------------------------------------

    /**
     * Rename or move paths. Returns a new collection.
     *
     * Wildcards, filters and recursive descent in the source are mapped onto
     * the wildcards of the target in order. Renaming a key inside the same
     * parent keeps its position.
     *
     * @throws InvalidArgumentException when source and target wildcards do not line up
     *
     * @example rename('user.mail', 'user.email')
     * @example rename('users.*.mail', 'users.*.email')
     * @example rename(['meta.tmp' => 'cache.tmp', 'id' => 'uuid'])
     * @example rename('**.e_mail', '**.email')
     */
    public function rename(string|int|array $from, string|int|null $to = null): static
    {
        $map = is_array($from) ? $from : [$from => $to];
        $items = $this->items;

        foreach ($map as $source => $target) {
            if ($target === null) {
                throw new InvalidArgumentException("No target path given for [{$source}].");
            }

            $sourceSegments = array_key_exists($source, $items) ? [(string) $source] : $this->toSegments($source);
            $targetSegments = $this->toSegments($target);

            foreach ($this->dataMatches($items, $sourceSegments) as [$keys, $value, $captures]) {
                if ($keys === []) {
                    continue;
                }

                $destination = $this->fillCaptures($targetSegments, $captures, (string) $source, (string) $target);

                $this->movePath($items, static::literal($keys), $destination, $value);
            }
        }

        return $this->newInstance($items);
    }

    /**
     * Replace the wildcards of a target path with the keys a source match captured.
     *
     * @return list<string>
     */
    private function fillCaptures(array $targetSegments, array $captures, string $source, string $target): array
    {
        $destination = [];
        $index = 0;

        foreach ($targetSegments as $segment) {
            if (is_string($segment)) {
                $destination[] = $segment;

                continue;
            }

            if (!array_key_exists($index, $captures)) {
                throw new InvalidArgumentException(
                    "Target [{$target}] has more wildcards than source [{$source}].",
                );
            }

            foreach ($captures[$index++] as $key) {
                $destination[] = (string) $key;
            }
        }

        if ($index !== count($captures)) {
            throw new InvalidArgumentException(
                "Target [{$target}] must contain a wildcard for every wildcard of source [{$source}].",
            );
        }

        return $destination;
    }

    /**
     * Move one concrete path; a rename within the same array keeps the key's position.
     */
    private function movePath(array &$items, array $source, array $destination, mixed $value): void
    {
        if ($source === $destination) {
            return;
        }

        $parent = array_slice($source, 0, -1);

        if ($parent === array_slice($destination, 0, -1)) {
            $siblings = $parent === [] ? $items : $this->dataGet($items, $parent);

            if (is_array($siblings)) {
                $from = end($source);
                $to = end($destination);
                $reordered = [];

                foreach ($siblings as $key => $sibling) {
                    if ((string) $key === $from) {
                        $reordered[$to] = $sibling;
                    } elseif ((string) $key !== $to) {
                        $reordered[$key] = $sibling;
                    }
                }

                if ($parent === []) {
                    $items = $reordered;
                } else {
                    $this->dataSet($items, $parent, $reordered, true, true);
                }

                return;
            }
        }

        $this->dataForget($items, $source, true);
        $this->dataSet($items, $destination, $value, true, true);
    }

    // -----------------------------------------------------------------
    // remap()
    // -----------------------------------------------------------------

    /**
     * Build a new structure for every item from [target path => source].
     *
     * A source is a path (wildcards, filters and ** allowed), a list of literal
     * segments, or a callback fn($item, $key). Missing sources give null.
     * Target paths are literal: dots create nesting, the wildcard has no meaning.
     *
     * @example remap([
     *     'id'          => 'user_id',
     *     'name'        => 'profile.full_name',
     *     'address.city'=> 'addresses.0.city',
     *     'tags'        => 'roles.*.slug',
     *     'label'       => fn($u) => "#{$u['user_id']} {$u['profile']['full_name']}",
     * ])
     */
    public function remap(array $map): static
    {
        $fields = [];

        foreach ($map as $target => $source) {
            $fields[] = [$this->toSegments((string) $target, wildcards: false), $this->valueRetriever($source)];
        }

        $result = [];

        foreach ($this->items as $key => $item) {
            $row = [];

            foreach ($fields as [$target, $retrieve]) {
                $this->dataSet($row, $target, $retrieve($item, $key));
            }

            $result[$key] = $row;
        }

        return $this->newInstance($result);
    }

    // -----------------------------------------------------------------
    // cast()
    // -----------------------------------------------------------------

    /**
     * Cast values at paths inside every item. Returns a new collection.
     *
     * A caster is one of:
     *   'int', 'float', 'float:2', 'decimal:2', 'string', 'bool', 'array', 'json',
     *   'object', 'collection', 'datetime', 'datetime:Y-m-d', 'date', 'timestamp'
     *   Status::class           backed enum (from); Status::class . ':try' uses tryFrom; pure enum by case name
     *   Money::class            any other class: new Money($value) unless already an instance
     *   'trim', 'strtolower'    any global function (receives only the value)
     *   'money:USD'             a caster registered with castUsing()
     *   fn($value, $item, $key) a callable
     *   new MoneyCast()         a Caster object
     *   ['trim', 'int']         a list: applied left to right
     *
     * Named casters keep null as null; callables and Caster objects receive it.
     * Missing paths are left untouched. Objects on the path are cloned.
     *
     * @example cast(['price' => 'float', 'items.*.qty' => 'int', 'status' => Status::class])
     * @example cast('created_at', 'datetime:d.m.Y')
     * @example cast(['email' => ['trim', 'strtolower']])
     */
    public function cast(string|int|array $casts, mixed $caster = null): static
    {
        $casts = is_array($casts) ? $casts : [$casts => $caster];
        $compiled = [];

        foreach ($casts as $path => $definition) {
            $compiled[] = [$this->toSegments((string) $path), $this->resolveCaster($definition)];
        }

        $result = [];

        foreach ($this->items as $key => $item) {
            $original = $item;

            foreach ($compiled as [$segments, $cast]) {
                $this->dataTransform($item, $segments, static fn ($value) => $cast($value, $original, $key), true);
            }

            $result[$key] = $item;
        }

        return $this->newInstance($result);
    }

    /**
     * Register a named caster usable in cast(): Collection::castUsing('money', fn($value, $item, $key, ...$args) => ...).
     *
     * Arguments after ':' are split by ',' and passed after $key: 'money:USD,2' -> ...$args = ['USD', '2'].
     *
     * @example castUsing('upper', fn($value) => strtoupper($value))
     * @example castUsing('money', fn($value, $item, $key, $currency = 'USD') => ...)  // used as 'money:EUR'
     */
    public static function castUsing(string $name, callable|Caster $caster): void
    {
        static::$casters[$name] = $caster;
    }

    /**
     * Turn a caster definition into fn($value, $item, $key).
     *
     * @throws InvalidArgumentException for an unknown caster
     */
    protected function resolveCaster(mixed $caster): Closure
    {
        if ($caster instanceof Caster) {
            return static fn ($value, $item, $key) => $caster->cast($value, $item, $key);
        }

        if (is_array($caster) && !is_callable($caster)) {
            $chain = array_map(fn ($step) => $this->resolveCaster($step), array_values($caster));

            return static function ($value, $item, $key) use ($chain) {
                foreach ($chain as $step) {
                    $value = $step($value, $item, $key);
                }

                return $value;
            };
        }

        if ($this->useAsCallable($caster)) {
            $callback = static::adaptCallback($caster);

            return static fn ($value, $item, $key) => $callback($value, $item, $key);
        }

        if (!is_string($caster) || $caster === '') {
            throw new InvalidArgumentException('A caster must be a string, a callable, a Caster or a list of them, got '.get_debug_type($caster).'.');
        }

        // Class and function names never contain ':', so the first one starts the argument.
        [$name, $argument] = array_pad(explode(':', $caster, 2), 2, null);

        if (isset(static::$casters[$name])) {
            $custom = static::$casters[$name];
            $args = $argument === null ? [] : array_map('trim', explode(',', $argument));

            return $custom instanceof Caster
                ? static fn ($value, $item, $key) => $custom->cast($value, $item, $key)
                : static fn ($value, $item, $key) => $custom($value, $item, $key, ...$args);
        }

        // Built-in names are lowercase; 'DateTime' is the class, 'datetime' the built-in.
        $builtin = $name === strtolower($name) ? $this->builtinCaster($name, $argument) : null;

        if ($builtin === null && (class_exists($name) || enum_exists($name) || interface_exists($name))) {
            $builtin = self::classCaster($name, $argument);
        }

        if ($builtin === null && function_exists($name)) {
            $builtin = static::adaptCallback($name);
        }

        if ($builtin === null) {
            throw new InvalidArgumentException("Unknown caster [{$caster}].");
        }

        return static fn ($value) => $value === null ? null : $builtin($value);
    }

    /**
     * @return (Closure(mixed): mixed)|null
     */
    private function builtinCaster(string $name, ?string $argument): ?Closure
    {
        return match ($name) {
            'int', 'integer' => static fn ($v) => is_object($v) && $v instanceof DateTimeInterface ? $v->getTimestamp() : (int) (is_string($v) ? trim($v) : $v),
            'float', 'double', 'real' => $argument === null
                ? static fn ($v) => (float) $v
                : static fn ($v) => round((float) $v, (int) $argument),
            'decimal' => static fn ($v) => number_format((float) $v, (int) ($argument ?? 2), '.', ''),
            'string' => static fn ($v) => match (true) {
                is_array($v) => json_encode($v, JSON_THROW_ON_ERROR),
                $v instanceof DateTimeInterface => $v->format($argument ?? DateTimeInterface::ATOM),
                $v instanceof BackedEnum => (string) $v->value,
                $v instanceof UnitEnum => $v->name,
                is_object($v) && !$v instanceof Stringable => json_encode($v, JSON_THROW_ON_ERROR),
                default => (string) $v,
            },
            'bool', 'boolean' => static fn ($v) => is_string($v)
                ? filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $v
                : (bool) $v,
            'array' => fn ($v) => is_string($v) && json_validate($v) && is_array($decoded = json_decode($v, true))
                ? $decoded
                : $this->getArrayableItems($v),
            'json' => static fn ($v) => is_string($v) ? json_decode($v, true, 512, JSON_THROW_ON_ERROR) : $v,
            'object' => fn ($v) => match (true) {
                is_object($v) => $v,
                is_string($v) => json_decode($v, false, 512, JSON_THROW_ON_ERROR),
                default => json_decode(json_encode($this->getArrayableItems($v), JSON_THROW_ON_ERROR), false),
            },
            'collection' => fn ($v) => $this->newInstance(
                is_string($v) && json_validate($v) ? (array) json_decode($v, true) : $this->getArrayableItems($v),
            ),
            'datetime', 'immutable_datetime' => static fn ($v) => self::toDateTime($v, $argument),
            'date' => static fn ($v) => self::toDateTime($v, $argument)->setTime(0, 0),
            'timestamp' => static fn ($v) => self::toDateTime($v, $argument)->getTimestamp(),
            default => null,
        };
    }

    /**
     * Caster for a class name: enums, date classes and value objects.
     */
    private static function classCaster(string $class, ?string $argument): Closure
    {
        if (is_subclass_of($class, BackedEnum::class)) {
            return $argument === 'try'
                ? static fn ($v) => $v instanceof $class ? $v : $class::tryFrom($v)
                : static fn ($v) => $v instanceof $class ? $v : $class::from($v);
        }

        if (enum_exists($class)) {
            return static fn ($v) => $v instanceof $class ? $v : constant($class.'::'.$v);
        }

        if (is_a($class, DateTimeInterface::class, true)) {
            return static function ($v) use ($class, $argument) {
                if ($v instanceof $class) {
                    return $v;
                }

                $date = self::toDateTime($v, $argument);

                return method_exists($class, 'createFromInterface')
                    ? $class::createFromInterface($date)
                    : new $class($date->format('Y-m-d H:i:s.u'), $date->getTimezone());
            };
        }

        return static fn ($v) => $v instanceof $class ? $v : new $class($v);
    }

    /**
     * @throws InvalidArgumentException when the value cannot be parsed
     */
    private static function toDateTime(mixed $value, ?string $format): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && $format === null)) {
            return new DateTimeImmutable('@'.(int) $value);
        }

        $string = (string) $value;

        if ($format !== null) {
            // Without '!' or '|' PHP fills the missing fields with the current time.
            $date = DateTimeImmutable::createFromFormat(
                str_contains($format, '!') || str_contains($format, '|') ? $format : '!'.$format,
                $string,
            );

            if ($date === false) {
                throw new InvalidArgumentException("Cannot parse [{$string}] with format [{$format}].");
            }

            return $date;
        }

        try {
            return new DateTimeImmutable($string);
        } catch (Exception $e) {
            throw new InvalidArgumentException("Cannot parse [{$string}] as a date.", 0, $e);
        }
    }
}

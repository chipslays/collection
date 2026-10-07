<?php

declare(strict_types=1);

namespace Collection\Concerns;

use RoundingMode;

/**
 * Numeric aggregates and counting.
 *
 * Every method accepts what to aggregate as:
 *   null      -> the items themselves
 *   'path'    -> the value at a dot-notation path in each item
 *   callable  -> callback($item, $key)
 *
 * avg/min/max/median/mode/standardDeviation ignore null values (a missing
 * path reads as null), the same way Laravel does.
 */
trait AggregatesValues
{
    /**
     * Sum of the values; with a path or callback, sum of the values it returns.
     *
     * @example sum()
     * @example sum('price')
     * @example sum(fn($item) => $item['price'] * $item['qty'])
     */
    public function sum(mixed $callback = null): int|float
    {
        $sum = 0;

        foreach ($this->retrieveAll($callback) as $value) {
            $sum += $value;
        }

        return $sum;
    }

    /**
     * Product of the values; with a path or callback, product of the values it returns.
     *
     * @example product('qty')
     */
    public function product(mixed $callback = null): int|float
    {
        $product = 1;

        foreach ($this->retrieveAll($callback) as $value) {
            $product *= $value;
        }

        return $product;
    }

    /**
     * Average of non-null values, or null when there are none.
     *
     * @example avg('score')
     */
    public function avg(mixed $callback = null): int|float|null
    {
        $values = $this->nonNullValues($callback);

        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * Alias for avg().
     *
     * @example average()
     * @example average('score')
     */
    public function average(mixed $callback = null): int|float|null
    {
        return $this->avg($callback);
    }

    /**
     * Smallest non-null value, or null when there are none.
     *
     * @example make([3, 1, 2])->min()  // 1
     * @example min('price')
     */
    public function min(mixed $callback = null): mixed
    {
        $values = $this->nonNullValues($callback);

        return $values === [] ? null : min($values);
    }

    /**
     * Largest non-null value, or null when there are none.
     *
     * @example make([3, 1, 2])->max()  // 3
     * @example max('price')
     */
    public function max(mixed $callback = null): mixed
    {
        $values = $this->nonNullValues($callback);

        return $values === [] ? null : max($values);
    }

    /**
     * The whole item holding the smallest value (first one on ties).
     *
     * @example minBy('price')
     */
    public function minBy(mixed $callback): mixed
    {
        return $this->extremeBy($callback, -1);
    }

    /**
     * The whole item holding the largest value (first one on ties).
     *
     * @example maxBy('score')
     */
    public function maxBy(mixed $callback): mixed
    {
        return $this->extremeBy($callback, 1);
    }

    /**
     * Median of non-null values; the mean of the two middle values for an even count.
     *
     * @example median('score')
     * @example median(flags: SORT_NUMERIC)
     */
    public function median(mixed $key = null, int $flags = SORT_REGULAR): int|float|null
    {
        $values = $this->nonNullValues($key);
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        sort($values, $flags);
        $middle = intdiv($count, 2);

        return $count % 2
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Most frequent value(s), sorted ascending; null for an empty collection.
     *
     * Works with any values, not only scalars.
     *
     * @example mode()         // [2]
     * @example mode('score')  // [70, 90] on a tie
     */
    public function mode(mixed $key = null): ?array
    {
        if ($this->items === []) {
            return null;
        }

        $counts = [];
        $values = [];

        foreach ($this->nonNullValues($key) as $value) {
            $hash = is_scalar($value) ? gettype($value).':'.$value : serialize($value);
            $counts[$hash] = ($counts[$hash] ?? 0) + 1;
            $values[$hash] ??= $value;
        }

        if ($counts === []) {
            return null;
        }

        $highest = max($counts);
        $result = [];

        foreach ($counts as $hash => $count) {
            if ($count === $highest) {
                $result[] = $values[$hash];
            }
        }

        sort($result);

        return $result;
    }

    /**
     * Standard deviation of non-null values; null when fewer than two.
     *
     * Population deviation by default; $sample = true divides by n - 1.
     *
     * @example standardDeviation('score')
     * @example standardDeviation('price', sample: true)
     */
    public function standardDeviation(mixed $key = null, bool $sample = false): ?float
    {
        $values = $this->nonNullValues($key);
        $count = count($values);

        if ($count < 2) {
            return null;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(static fn ($v) => ($v - $mean) ** 2, $values))
            / ($sample ? $count - 1 : $count);

        return sqrt($variance);
    }

    /**
     * Percentage (0-100) of items passing the test; null for an empty collection.
     *
     * The test accepts where()-style arguments: a callable or a path (truthy check).
     *
     * @example percentage(fn($u) => $u['active'])
     * @example percentage('active', precision: 1)
     * @example percentage('active', 0, PHP_ROUND_HALF_EVEN)
     */
    public function percentage(
        mixed $callback,
        int $precision = 2,
        int|RoundingMode $mode = PHP_ROUND_HALF_UP,
    ): ?float {
        if ($this->items === []) {
            return null;
        }

        return round($this->filter($callback)->count() / count($this->items) * 100, $precision, $mode);
    }

    /**
     * Count items per value, per path value, or per callback result.
     *
     * Keys are normalised: null -> '', true/false -> 1/0, enums -> their value.
     *
     * @example countBy()                                      // ['a' => 2, 'b' => 1]
     * @example countBy('role')
     * @example countBy(fn($u) => $u['active'] ? 'on' : 'off')
     */
    public function countBy(mixed $countBy = null): static
    {
        $counts = [];

        foreach ($this->retrieveAll($countBy) as $group) {
            if (!is_int($group) && !is_string($group)) {
                $group = static::normalizeKey($group);
            }

            $counts[$group] = ($counts[$group] ?? 0) + 1;
        }

        return $this->newInstance($counts);
    }

    /**
     * Retrieved values with nulls removed, as a list.
     */
    private function nonNullValues(mixed $callback): array
    {
        $values = [];

        foreach ($this->retrieveAll($callback) as $value) {
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function extremeBy(mixed $callback, int $direction): mixed
    {
        $found = false;
        $best = null;
        $bestItem = null;

        foreach ($this->retrieveAll($callback) as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (!$found || ($value <=> $best) === $direction) {
                $found = true;
                $best = $value;
                $bestItem = $this->items[$key];
            }
        }

        return $bestItem;
    }
}

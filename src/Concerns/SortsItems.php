<?php

declare(strict_types=1);

namespace Collection\Concerns;

/**
 * Sorting. Every method returns a new collection and preserves keys
 * (call values() to re-index). $options accepts the native SORT_* flags.
 */
trait SortsItems
{
    /**
     * Sort values with a comparator, or with native flags.
     *
     * @example sort()                                 // asort(SORT_REGULAR)
     * @example sort(SORT_NATURAL | SORT_FLAG_CASE)    // natural, case-insensitive
     * @example sort(fn($a, $b) => $a['age'] <=> $b['age'])
     */
    public function sort(callable|int|null $callback = null): static
    {
        $items = $this->items;

        is_callable($callback)
            ? uasort($items, $callback)
            : asort($items, $callback ?? SORT_REGULAR);

        return $this->newInstance($items);
    }

    /**
     * Sort values in descending order with native flags or a comparator.
     *
     * @example sortDesc()
     * @example sortDesc(SORT_STRING)
     * @example sortDesc(fn($a, $b) => strlen($a) <=> strlen($b))  // comparator is reversed for you
     */
    public function sortDesc(callable|int $options = SORT_REGULAR): static
    {
        $items = $this->items;

        is_callable($options)
            ? uasort($items, static fn ($a, $b) => $options($b, $a))
            : arsort($items, $options);

        return $this->newInstance($items);
    }

    /**
     * Sort by a path, a callback, or several criteria.
     *
     * Single criterion - a path or a callback ($item, $key) returning the sort value:
     *   sortBy('name')
     *   sortBy('user.age', SORT_NUMERIC, descending: true)
     *   sortBy(fn($item) => strlen($item['name']))
     *
     * Several criteria - an array; each entry is one of:
     *   'path'                     ascending
     *   ['path', 'desc']           explicit direction ('asc' / 'desc')
     *   'path' => 'desc'           path as key, direction as value
     *   fn($a, $b) => int          comparator
     *
     *   sortBy(['price' => 'desc', 'name'])
     *   sortBy([['price', 'desc'], ['name', 'asc']])
     *
     * Missing paths sort as null.
     *
     * @example sortBy('name')
     * @example sortBy('user.age', SORT_NUMERIC, descending: true)
     * @example sortBy(fn($item) => strlen($item['name']))
     * @example sortBy(['price' => 'desc', 'name'])
     */
    public function sortBy(mixed $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        if (is_array($callback) && !is_callable($callback)) {
            return $this->sortByMany($callback, $options, $descending);
        }

        $results = $this->retrieveAll($callback);

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return $this->newInstance($results);
    }

    /**
     * Sort by a path, a callback, or several criteria in descending order.
     *
     * @example sortByDesc('price')
     * @example sortByDesc(['price', 'name'])  // every criterion descending
     */
    public function sortByDesc(mixed $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort keys with native flags.
     *
     * @example sortKeys()
     * @example sortKeys(SORT_NATURAL, descending: true)
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        $items = $this->items;

        $descending ? krsort($items, $options) : ksort($items, $options);

        return $this->newInstance($items);
    }

    /**
     * Sort keys in descending order.
     *
     * @example make(['a' => 1, 'c' => 3, 'b' => 2])->sortKeysDesc()  // ['c' => 3, 'b' => 2, 'a' => 1]
     */
    public function sortKeysDesc(int $options = SORT_REGULAR): static
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Sort keys with a comparator.
     *
     * @example sortKeysUsing('strnatcasecmp')
     * @example sortKeysUsing(fn($a, $b) => strlen($a) <=> strlen($b))
     */
    public function sortKeysUsing(callable $callback): static
    {
        $items = $this->items;

        uksort($items, $callback);

        return $this->newInstance($items);
    }

    /**
     * Sort keys with a comparator or native flags (shorthand for sortKeys()/sortKeysUsing()).
     *
     * @example ksort()
     * @example ksort(SORT_STRING)
     * @example ksort(fn($a, $b) => $b <=> $a)
     */
    public function ksort(callable|int|null $callback = null): static
    {
        return is_callable($callback)
            ? $this->sortKeysUsing($callback)
            : $this->sortKeys($callback ?? SORT_REGULAR);
    }

    /**
     * Sort using several comparisons; see sortBy().
     */
    protected function sortByMany(array $comparisons, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $criteria = [];

        foreach ($comparisons as $key => $comparison) {
            if (is_string($key)) {
                $comparison = [$key, $comparison];
            } elseif ($this->useAsCallable($comparison) || !is_array($comparison)) {
                $comparison = [$comparison];
            }

            $prop = $comparison[0];
            $direction = $comparison[1] ?? true;
            $ascending = $direction === true || (is_string($direction) && strtolower($direction) === 'asc');

            $criteria[] = [
                'comparator' => $this->useAsCallable($prop) ? $prop : null,
                'retrieve' => $this->useAsCallable($prop) ? null : $this->valueRetriever($prop),
                'ascending' => $descending ? false : $ascending,
            ];
        }

        $items = $this->items;

        uasort($items, static function ($a, $b) use ($criteria, $options) {
            foreach ($criteria as $criterion) {
                if ($criterion['comparator'] !== null) {
                    $result = ($criterion['comparator'])($a, $b) <=> 0;
                } else {
                    $values = [($criterion['retrieve'])($a), ($criterion['retrieve'])($b)];

                    if (!$criterion['ascending']) {
                        $values = array_reverse($values);
                    }

                    $result = static::compareWithFlags($values[0], $values[1], $options);
                }

                if ($result !== 0) {
                    return $result;
                }
            }

            return 0;
        });

        return $this->newInstance($items);
    }

    /**
     * Compare two values the way sort() would with the given SORT_* flags.
     */
    protected static function compareWithFlags(mixed $a, mixed $b, int $options): int
    {
        if (($options & SORT_FLAG_CASE) === SORT_FLAG_CASE) {
            return ($options & SORT_NATURAL) === SORT_NATURAL
                ? strnatcasecmp((string) $a, (string) $b)
                : strcasecmp((string) $a, (string) $b);
        }

        return match ($options) {
            SORT_NUMERIC => (float) $a <=> (float) $b,
            SORT_STRING => strcmp((string) $a, (string) $b),
            SORT_NATURAL => strnatcmp((string) $a, (string) $b),
            SORT_LOCALE_STRING => strcoll((string) $a, (string) $b),
            default => $a <=> $b,
        };
    }
}

<?php

declare(strict_types=1);

namespace Collection\Contracts;

/**
 * A reusable value caster for Collection::cast().
 *
 * class MoneyCast implements Caster
 * {
 *     public function __construct(private string $currency = 'EUR') {}
 *
 *     public function cast(mixed $value, mixed $item, string|int $key): mixed
 *     {
 *         return new Money((int) round($value * 100), $this->currency);
 *     }
 * }
 *
 * $orders->cast(['total' => new MoneyCast('USD')]);
 */
interface Caster
{
    /**
     * @param  mixed  $value  The value at the cast path
     * @param  mixed  $item  The whole item the value belongs to (before casting)
     * @param  string|int  $key  The item's key in the collection
     */
    public function cast(mixed $value, mixed $item, string|int $key): mixed;
}

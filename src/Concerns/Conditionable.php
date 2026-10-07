<?php

declare(strict_types=1);

namespace Collection\Concerns;

use Closure;

/**
 * Conditional chaining: when(), unless() and their empty/not-empty shorthands.
 *
 * Callbacks receive ($collection, $conditionValue). When a callback returns
 * a collection that result continues the chain; any other return value
 * (including null) keeps the current collection.
 */
trait Conditionable
{
    /**
     * Run $callback when $value is truthy, otherwise $default (when given).
     *
     * $value may be a Closure - it receives the collection and its result is the condition.
     *
     * @example when($isAdmin, fn($c) => $c->merge($adminItems))
     * @example when(fn($c) => $c->isNotEmpty(), fn($c) => $c->sortBy('name'))
     * @example when($request['sort'] ?? null, fn($c, $field) => $c->sortBy($field))
     */
    public function when(mixed $value = null, ?callable $callback = null, ?callable $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if ($value) {
            return $callback === null ? $this : $this->conditionResult($callback($this, $value));
        }

        return $default === null ? $this : $this->conditionResult($default($this, $value));
    }

    /**
     * Run $callback when $value is falsy, otherwise $default (when given).
     *
     * @example unless($c->isEmpty(), fn($c) => $c->sortBy('name'))
     */
    public function unless(mixed $value = null, ?callable $callback = null, ?callable $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if (!$value) {
            return $callback === null ? $this : $this->conditionResult($callback($this, $value));
        }

        return $default === null ? $this : $this->conditionResult($default($this, $value));
    }

    /**
     * Run $callback when the collection is empty.
     *
     * @example whenEmpty(fn($c) => $c->push('default'))
     */
    public function whenEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Run $callback when the collection is not empty.
     *
     * @example whenNotEmpty(fn($c) => $c->push('last'), fn($c) => $c->push('first'))
     */
    public function whenNotEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Run $callback unless the collection is empty (same as whenNotEmpty()).
     *
     * @example unlessEmpty(fn($c) => $c->push('last'))
     */
    public function unlessEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->whenNotEmpty($callback, $default);
    }

    /**
     * Run $callback unless the collection is not empty (same as whenEmpty()).
     *
     * @example unlessNotEmpty(fn($c) => $c->push('default'))
     */
    public function unlessNotEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->whenEmpty($callback, $default);
    }

    private function conditionResult(mixed $result): static
    {
        return $result instanceof static ? $result : $this;
    }
}

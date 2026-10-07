<?php

declare(strict_types=1);

namespace Collection;

use Closure;

/**
 * Proxy behind higher-order messages such as $users->map->name or $users->each->save().
 *
 * Property access reads a dot-notation path from every item:
 *   $users->sum->{'stats.score'}
 *
 * Method calls invoke the method on every item:
 *   $users->each->notify($message)
 */
class HigherOrderCollectionProxy
{
    /**
     * Create a proxy that calls $method on the collection with a callback built from the message.
     *
     * @param  Closure(mixed, string): mixed  $resolver  Reads a path from an item.
     */
    public function __construct(
        protected Collection $collection,
        protected string $method,
        protected Closure $resolver,
    ) {}

    /**
     * Apply the method with a callback reading the property (or path) from every item.
     *
     * @example $users->map->name
     */
    public function __get(string $key): mixed
    {
        $resolver = $this->resolver;

        return $this->collection->{$this->method}(static fn ($value) => $resolver($value, $key));
    }

    /**
     * Apply the method with a callback calling the given method on every item.
     *
     * @example $users->each->save()
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->collection->{$this->method}(static fn ($value) => $value->{$method}(...$parameters));
    }
}

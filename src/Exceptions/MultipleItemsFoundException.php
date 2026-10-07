<?php

declare(strict_types=1);

namespace Collection\Exceptions;

use OverflowException;

/**
 * Thrown when an operation requires exactly one item but several matched.
 *
 * Extends OverflowException (and therefore RuntimeException).
 */
class MultipleItemsFoundException extends OverflowException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("{$count} items were found.");
    }

    public function getCount(): int
    {
        return $this->count;
    }
}

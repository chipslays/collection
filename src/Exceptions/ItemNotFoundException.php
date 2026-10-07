<?php

declare(strict_types=1);

namespace Collection\Exceptions;

use UnderflowException;

/**
 * Thrown when an operation requires an item but none matched.
 *
 * Extends UnderflowException (and therefore RuntimeException), so existing
 * catch blocks for either keep working.
 */
class ItemNotFoundException extends UnderflowException
{
    public function __construct(string $message = 'The collection contains no matching items.')
    {
        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace Collection\Concerns;

use BadMethodCallException;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * Adds run-time macro (extension) support to any class.
 *
 * Usage:
 *   Collection::macro('toUpper', fn() => $this->map(strtoupper(...)));
 *   Collection::mixin(new MyCollectionMixin());
 *   Collection::hasMacro('toUpper');   // true
 *   Collection::flushMacros();
 *
 * Subclasses inherit the macros of their parents and may override them;
 * macros registered on a subclass never leak to the parent.
 */
trait Macroable
{
    /**
     * Macro registry keyed by the class that registered the macro.
     *
     * @var array<class-string, array<string, callable>>
     */
    private static array $macros = [];

    /**
     * Register a custom macro (extension method) on the class.
     *
     * @param-closure-this static $macro
     *
     * @example Collection::macro('toUpper', fn() => $this->map(strtoupper(...)));
     */
    public static function macro(string $name, callable|object $macro): void
    {
        self::$macros[static::class][$name] = $macro;
    }

    /**
     * Mix all public/protected methods of $mixin into the class as macros.
     *
     * Each qualifying method on $mixin must return a Closure - that Closure
     * becomes the macro body. Magic methods (__construct etc.) are skipped.
     *
     * @param  bool  $replace  Whether to overwrite already-registered macros.
     *
     * @throws ReflectionException
     * @throws UnexpectedValueException if a mixin method doesn't return a Closure
     *
     * @example Collection::mixin(new MyCollectionMixin());
     */
    public static function mixin(object $mixin, bool $replace = true): void
    {
        $methods = (new ReflectionClass($mixin))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            // Skip magic methods and methods inherited from parent classes
            if (
                str_starts_with($method->getName(), '__')
                || $method->getDeclaringClass()->getName() !== $mixin::class
            ) {
                continue;
            }

            if (!$replace && static::hasMacro($method->getName())) {
                continue;
            }

            $macro = $method->invoke($mixin);

            if (!$macro instanceof Closure) {
                throw new UnexpectedValueException(sprintf(
                    'Mixin method %s::%s must return a Closure, got %s.',
                    $mixin::class,
                    $method->getName(),
                    get_debug_type($macro),
                ));
            }

            static::macro($method->getName(), $macro);
        }
    }

    /**
     * Check whether a macro is available on this class (own or inherited).
     *
     * @example Collection::hasMacro('toUpper')  // true after Collection::macro('toUpper', ...)
     */
    public static function hasMacro(string $name): bool
    {
        return self::findMacro($name) !== null;
    }

    /**
     * Remove all macros registered directly on the current class.
     *
     * @example Collection::flushMacros()
     */
    public static function flushMacros(): void
    {
        self::$macros[static::class] = [];
    }

    /**
     * Handle calls to registered macros on an instance.
     *
     * Closures are bound to $this so they can use collection internals.
     * Static closures and closures created from methods are called with
     * the class scope only.
     *
     * @throws BadMethodCallException when the macro does not exist
     *
     * @example Collection::macro('toUpper', fn() => $this->map(strtoupper(...)));
     * @example make(['a'])->toUpper()  // ['A']
     */
    public function __call(string $method, array $parameters): mixed
    {
        $macro = self::findMacro($method) ?? throw self::missingMacro($method);

        if ($macro instanceof Closure) {
            $macro = self::bindMacro($macro, $this);
        }

        return $macro(...$parameters);
    }

    /**
     * Handle static calls to registered macros.
     *
     * @throws BadMethodCallException when the macro does not exist
     *
     * @example Collection::macro('ones', fn() => new Collection([1, 1]));
     * @example Collection::ones()
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        $macro = self::findMacro($method) ?? throw self::missingMacro($method);

        if ($macro instanceof Closure) {
            $macro = self::bindMacro($macro, null);
        }

        return $macro(...$parameters);
    }

    /**
     * Look the macro up on the class, then on its parents.
     */
    private static function findMacro(string $name): mixed
    {
        for ($class = static::class; $class !== false; $class = get_parent_class($class)) {
            if (isset(self::$macros[$class][$name])) {
                return self::$macros[$class][$name];
            }
        }

        return null;
    }

    private static function bindMacro(Closure $macro, ?object $instance): Closure
    {
        $reflection = new ReflectionFunction($macro);

        // Closures created from functions or methods (Closure::fromCallable,
        // first-class callables) cannot be re-bound; only anonymous ones can.
        if (!str_starts_with($reflection->getName(), '{closure')) {
            return $macro;
        }

        if ($instance !== null && !$reflection->isStatic()) {
            return Closure::bind($macro, $instance, static::class) ?? $macro;
        }

        return Closure::bind($macro, null, static::class) ?? $macro;
    }

    private static function missingMacro(string $method): BadMethodCallException
    {
        return new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
    }
}

<?php

declare(strict_types=1);

namespace Collection\Concerns;

use ArrayAccess;
use BackedEnum;
use Closure;
use Error;
use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use stdClass;
use Stringable;
use Traversable;
use UnitEnum;

/**
 * Dot-notation path engine shared by every collection method.
 *
 * Path syntax (with the default '*' wildcard and '.' delimiter):
 *   'user.name'               plain keys
 *   'users.*.name'            wildcard - every child, original keys preserved
 *   'users.*[id].name'        named wildcard - result keyed by each child's 'id'
 *   'users[?active].name'     filter - only children passing the condition
 *   'users[?age>=18 && role="admin"]'
 *   '**.email'                recursive descent - any depth, keyed by full path
 *   'hosts.example\.com'      backslash escapes the delimiter, wildcard or '['
 *   ['hosts', 'example.com']  an array of segments is taken literally
 *
 * Traversal works through arrays, ArrayAccess objects, nested collections
 * and public object properties. Wildcards iterate arrays, collections,
 * Traversable objects and public object properties.
 *
 * Parsed segments: a string is a plain key; an array is a selector:
 *   ['t' => 'w', 'k' => ?keySegments, 'f' => ?filters]  wildcard
 *   ['t' => 'd', 'k' => null,         'f' => ?filters]  recursive descent
 */
trait ResolvesPaths
{
    /**
     * Parsed paths, keyed by mode + wildcard + delimiter + path.
     *
     * @var array<string, list<string|array<string, mixed>>>
     */
    private static array $pathCache = [];

    /**
     * Unique marker that tells "missing" apart from a stored null.
     */
    protected static function missing(): object
    {
        static $missing = null;

        return $missing ??= new stdClass;
    }

    // -----------------------------------------------------------------
    // Parsing
    // -----------------------------------------------------------------

    /**
     * Turn a path (string, int or list of literal segments) into parsed segments.
     *
     * @param  string|int|array<array-key, string|int>  $path
     */
    protected function toSegments(string|int|array $path, bool $wildcards = true): array
    {
        if (is_array($path)) {
            return array_map(
                fn ($segment) => $wildcards && $segment === $this->wildcard
                    ? ['t' => 'w', 'k' => null, 'f' => null]
                    : (string) $segment,
                array_values($path),
            );
        }

        $path = (string) $path;
        $cacheKey = ($wildcards ? '1' : '0').$this->wildcard."\0".$this->delimiter."\0".$path;

        if (isset(self::$pathCache[$cacheKey])) {
            return self::$pathCache[$cacheKey];
        }

        if (count(self::$pathCache) >= 2048) {
            self::$pathCache = [];
        }

        return self::$pathCache[$cacheKey] = $this->tokenizePath($path, $wildcards);
    }

    /**
     * Whether every segment is a plain key (no wildcard, filter or descent).
     */
    protected static function isPlainPath(array $segments): bool
    {
        foreach ($segments as $segment) {
            if (!is_string($segment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Split a path string into segments, honouring escapes, named wildcards,
     * filters and recursive descent.
     */
    private function tokenizePath(string $path, bool $wildcards): array
    {
        $delimiter = $this->delimiter;
        $wildcard = $this->wildcard;
        $dLength = strlen($delimiter);
        $length = strlen($path);

        $segments = [];
        $buffer = '';
        $literal = false;
        $named = null;
        $filters = [];
        $i = 0;

        while ($i < $length) {
            $char = $path[$i];

            if ($char === '\\' && $i + 1 < $length) {
                foreach ([$delimiter, $wildcard, '\\', '['] as $escapable) {
                    if (substr_compare($path, $escapable, $i + 1, strlen($escapable)) === 0) {
                        $buffer .= $escapable;
                        $literal = true;
                        $i += 1 + strlen($escapable);

                        continue 2;
                    }
                }
            }

            if (substr_compare($path, $delimiter, $i, $dLength) === 0) {
                array_push($segments, ...$this->makeSegments($buffer, $literal, $named, $filters, $wildcards));
                $buffer = '';
                $literal = false;
                $named = null;
                $filters = [];
                $i += $dLength;

                continue;
            }

            if ($wildcards && $char === '[') {
                $close = self::findClosingBracket($path, $i + 1);

                if ($close !== null) {
                    $content = substr($path, $i + 1, $close - $i - 1);

                    if (str_starts_with($content, '?')) {
                        $filters[] = $this->parseFilter(substr($content, 1));
                        $i = $close + 1;

                        continue;
                    }

                    if ($buffer === $wildcard && !$literal && $named === null && $filters === []) {
                        $named = $this->tokenizePath($content, true);
                        $i = $close + 1;

                        continue;
                    }
                }
            }

            $buffer .= $char;
            $i++;
        }

        array_push($segments, ...$this->makeSegments($buffer, $literal, $named, $filters, $wildcards));

        return $segments;
    }

    /**
     * Build the segment(s) for one delimiter-separated part of a path.
     */
    private function makeSegments(string $buffer, bool $literal, ?array $named, array $filters, bool $wildcards): array
    {
        $filter = $filters === [] ? null : $filters;

        if ($wildcards && !$literal) {
            if ($buffer === $this->wildcard) {
                return [['t' => 'w', 'k' => $named, 'f' => $filter]];
            }

            if ($buffer === $this->wildcard.$this->wildcard) {
                return [['t' => 'd', 'k' => null, 'f' => $filter]];
            }
        }

        if ($filter === null) {
            return [$buffer];
        }

        // 'users[?active]' = the 'users' key, then its children passing the filter.
        $selector = ['t' => 'w', 'k' => null, 'f' => $filter];

        return $buffer === '' && !$literal ? [$selector] : [$buffer, $selector];
    }

    private static function findClosingBracket(string $path, int $start): ?int
    {
        $depth = 1;
        $quote = null;

        for ($j = $start, $length = strlen($path); $j < $length; $j++) {
            $char = $path[$j];

            if ($quote !== null) {
                if ($char === '\\') {
                    $j++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '\\') {
                $j++;
            } elseif ($char === '[') {
                $depth++;
            } elseif ($char === ']' && --$depth === 0) {
                return $j;
            }
        }

        return null;
    }

    /**
     * Parse a filter expression: conditions joined by && and ||.
     *
     * Each condition is 'path', '!path' or 'path <op> value'; values are
     * numbers, true/false/null, quoted strings or bare words.
     *
     * @return list<list<array{p: array, op: ?string, v: mixed, not: bool}>> OR-groups of AND-conditions
     */
    private function parseFilter(string $expression): array
    {
        $groups = [];

        foreach (self::splitOutsideQuotes($expression, '||') as $group) {
            $conditions = [];

            foreach (self::splitOutsideQuotes($group, '&&') as $condition) {
                $conditions[] = $this->parseCondition(trim($condition));
            }

            $groups[] = $conditions;
        }

        return $groups;
    }

    private function parseCondition(string $condition): array
    {
        if ($condition === '') {
            throw new InvalidArgumentException('Empty condition in a path filter.');
        }

        if (preg_match('/^(.+?)\s*(===|!==|==|!=|<>|>=|<=|=|>|<)\s*(.*)$/s', $condition, $m)) {
            return [
                'p' => $this->toSegments(trim($m[1])),
                'op' => $m[2],
                'v' => self::parseLiteral(trim($m[3])),
                'not' => false,
            ];
        }

        $not = str_starts_with($condition, '!');

        return [
            'p' => $this->toSegments(trim($not ? substr($condition, 1) : $condition)),
            'op' => null,
            'v' => null,
            'not' => $not,
        ];
    }

    private static function parseLiteral(string $value): mixed
    {
        $length = strlen($value);

        if ($length >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$length - 1] === $value[0]) {
            return str_replace(['\\'.$value[0], '\\\\'], [$value[0], '\\'], substr($value, 1, -1));
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($value) ? $value + 0 : $value,
        };
    }

    /**
     * @return list<string>
     */
    private static function splitOutsideQuotes(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $quote = null;
        $length = strlen($value);
        $sepLen = strlen($separator);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quote !== null) {
                $buffer .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $value[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif (substr_compare($value, $separator, $i, $sepLen) === 0) {
                $parts[] = $buffer;
                $buffer = '';
                $i += $sepLen - 1;

                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * Render concrete keys as an (escaped) path string.
     */
    protected function renderPath(array $keys): string
    {
        $escape = ['\\' => '\\\\', $this->delimiter => '\\'.$this->delimiter, $this->wildcard => '\\'.$this->wildcard];

        return implode($this->delimiter, array_map(static fn ($key) => strtr((string) $key, $escape), $keys));
    }

    // -----------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------

    /**
     * Read the value at $segments inside $target.
     *
     * Returns static::missing() when the path cannot be resolved. A wildcard
     * returns an array keyed like its source (or by the named-wildcard key);
     * recursive descent returns an array keyed by the full path of each match.
     */
    protected function dataGet(mixed $target, array $segments, int $offset = 0): mixed
    {
        $missing = static::missing();

        for ($i = $offset, $count = count($segments); $i < $count; $i++) {
            $segment = $segments[$i];

            if (is_string($segment)) {
                if (is_array($target)) {
                    if (isset($target[$segment]) || array_key_exists($segment, $target)) {
                        $target = $target[$segment];

                        continue;
                    }

                    return $missing;
                }

                $target = $this->readChild($target, $segment, $missing);

                if ($target === $missing) {
                    return $missing;
                }

                continue;
            }

            if ($segment['t'] === 'd') {
                $result = [];

                foreach ($this->dataMatches($target, $segments, $i) as [$keys, $value]) {
                    $result[$this->renderPath($keys)] = $value;
                }

                return $result === [] ? $missing : $result;
            }

            $children = $this->childrenOf($target);

            if ($children === null) {
                return $missing;
            }

            if ($segment['f'] === null && $segment['k'] === null && static::isPlainPath(array_slice($segments, $i + 1))) {
                return $this->plainWildcard($children, $segments, $i + 1, $count);
            }

            $result = [];

            foreach ($children as $index => $child) {
                if ($segment['f'] !== null && !$this->passesFilters($segment['f'], $child)) {
                    continue;
                }

                $value = $this->dataGet($child, $segments, $i + 1);

                if ($value === $missing) {
                    continue;
                }

                if ($segment['k'] !== null) {
                    $named = $this->dataGet($child, $segment['k']);

                    if ($named !== $missing && (is_scalar($named) || $named instanceof UnitEnum || $named instanceof Stringable)) {
                        $index = static::normalizeKey($named);
                    }
                }

                $result[$index] = $value;
            }

            return $result === [] && $i + 1 < $count ? $missing : $result;
        }

        return $target;
    }

    /**
     * Fast route for a plain wildcard followed only by plain keys ('users.*.name').
     */
    private function plainWildcard(array $children, array $segments, int $from, int $count): mixed
    {
        $missing = static::missing();
        $result = [];
        $rest = array_slice($segments, $from);

        if ($rest !== [] && count($rest) <= 2 && $this->isShallowSafe($rest)) {
            try {
                $a = $rest[0];

                if (count($rest) === 1) {
                    foreach ($children as $index => $child) {
                        if (is_array($child) && isset($child[$a])) {
                            $result[$index] = $child[$a];
                        } elseif (($value = $this->dataGet($child, $segments, $from)) !== $missing) {
                            $result[$index] = $value;
                        }
                    }
                } else {
                    $b = $rest[1];

                    foreach ($children as $index => $child) {
                        if (is_array($child) && isset($child[$a][$b])) {
                            $result[$index] = $child[$a][$b];
                        } elseif (($value = $this->dataGet($child, $segments, $from)) !== $missing) {
                            $result[$index] = $value;
                        }
                    }
                }

                return $result === [] ? $missing : $result;
            } catch (Error) {
                $result = [];
            }
        }

        foreach ($children as $index => $value) {
            for ($j = $from; $j < $count; $j++) {
                $key = $segments[$j];

                if (is_array($value)) {
                    if (isset($value[$key]) || array_key_exists($key, $value)) {
                        $value = $value[$key];

                        continue;
                    }

                    $value = $missing;

                    break;
                }

                $value = $this->dataGet($value, $segments, $j);

                break;
            }

            if ($value !== $missing) {
                $result[$index] = $value;
            }
        }

        return $result === [] && $from < $count ? $missing : $result;
    }

    /**
     * The value retrieved from every item, keyed like the items.
     *
     * Same semantics as valueRetriever() (missing paths read as null), but a
     * plain path is resolved in a single loop without per-item callbacks.
     *
     * @return array<array-key, mixed>
     */
    protected function retrieveAll(mixed $by): array
    {
        $result = [];

        if ((is_string($by) || is_int($by)) && static::isPlainPath($segments = $this->toSegments($by))) {
            $fast = $this->retrieveShallow($segments);

            if ($fast !== null) {
                return $fast;
            }

            $missing = static::missing();

            foreach ($this->items as $itemKey => $item) {
                $value = $item;

                foreach ($segments as $key) {
                    if (is_array($value) && isset($value[$key])) {
                        $value = $value[$key];

                        continue;
                    }

                    $value = $this->dataGet($item, $segments);
                    $value = $value === $missing ? null : $value;

                    break;
                }

                $result[$itemKey] = $value;
            }

            return $result;
        }

        $retrieve = $this->valueRetriever($by);

        foreach ($this->items as $itemKey => $item) {
            $result[$itemKey] = $retrieve($item, $itemKey);
        }

        return $result;
    }

    /**
     * retrieveAll() for plain paths of depth 1-3 using native `??` access -
     * as fast as a hand-written loop.
     *
     * Returns null (caller falls back to the full engine) for deeper paths,
     * numeric inner keys (a string could be indexed by offset), or when an
     * inner value is an object that cannot be read like an array.
     */
    private function retrieveShallow(array $segments): ?array
    {
        $depth = count($segments);

        if ($depth > 3) {
            return null;
        }

        if (!$this->isShallowSafe($segments)) {
            return null;
        }

        $result = [];
        $slow = function (mixed $item) use ($segments): mixed {
            $found = $this->dataGet($item, $segments);

            return $found === static::missing() ? null : $found;
        };

        try {
            switch ($depth) {
                case 1:
                    [$a] = $segments;

                    foreach ($this->items as $key => $item) {
                        $result[$key] = is_array($item) ? ($item[$a] ?? null) : $slow($item);
                    }

                    break;

                case 2:
                    [$a, $b] = $segments;

                    foreach ($this->items as $key => $item) {
                        $result[$key] = is_array($item) ? ($item[$a][$b] ?? null) : $slow($item);
                    }

                    break;

                default:
                    [$a, $b, $c] = $segments;

                    foreach ($this->items as $key => $item) {
                        $result[$key] = is_array($item) ? ($item[$a][$b][$c] ?? null) : $slow($item);
                    }
            }
        } catch (Error) {
            return null;
        }

        return $result;
    }

    /**
     * Whether plain $segments can be read with native nested `[..]` access:
     * no numeric inner key (a string would be indexed by offset) and no
     * escaped delimiter/wildcard (a nested collection's offsetGet() would
     * re-parse it as a path).
     */
    private function isShallowSafe(array $segments): bool
    {
        foreach ($segments as $i => $segment) {
            if (
                ($i > 0 && is_numeric($segment))
                || str_contains($segment, $this->delimiter)
                || str_contains($segment, $this->wildcard)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read a path from an item, treating a missing path as null (data_get semantics).
     */
    protected function valueAt(mixed $target, string|int|array|null $path): mixed
    {
        if ($path === null) {
            return $target;
        }

        $value = $this->dataGet($target, $this->toSegments($path));

        return $value === static::missing() ? null : $value;
    }

    /**
     * Every concrete match of $segments inside $target.
     *
     * @return list<array{0: list<array-key>, 1: mixed, 2: list<list<array-key>>}>
     *                                                                             [keys relative to $target, value, keys captured by each wildcard/descent]
     */
    protected function dataMatches(mixed $target, array $segments, int $offset = 0, array $prefix = [], array $captures = []): array
    {
        if ($offset === count($segments)) {
            return [[$prefix, $target, $captures]];
        }

        $segment = $segments[$offset];

        if (is_string($segment)) {
            $child = $this->readChild($target, $segment, static::missing());

            return $child === static::missing()
                ? []
                : $this->dataMatches($child, $segments, $offset + 1, [...$prefix, $segment], $captures);
        }

        $matches = [];

        if ($segment['t'] === 'd') {
            foreach ($this->descendants($target) as [$keys, $node]) {
                if ($segment['f'] === null || $this->passesFilters($segment['f'], $node)) {
                    array_push($matches, ...$this->dataMatches(
                        $node,
                        $segments,
                        $offset + 1,
                        [...$prefix, ...$keys],
                        [...$captures, $keys],
                    ));
                }
            }

            return $matches;
        }

        foreach ($this->childrenOf($target) ?? [] as $key => $child) {
            if ($segment['f'] === null || $this->passesFilters($segment['f'], $child)) {
                array_push($matches, ...$this->dataMatches(
                    $child,
                    $segments,
                    $offset + 1,
                    [...$prefix, $key],
                    [...$captures, [$key]],
                ));
            }
        }

        return $matches;
    }

    /**
     * $target and every node below it, with keys relative to $target.
     *
     * @return list<array{0: list<array-key>, 1: mixed}>
     */
    private function descendants(mixed $target, array $keys = [], array &$seen = []): array
    {
        $nodes = [[$keys, $target]];

        if (is_object($target)) {
            $id = spl_object_id($target);

            if (isset($seen[$id])) {
                return [];
            }

            $seen[$id] = true;
        }

        if (is_array($target) || is_object($target)) {
            foreach ($this->childrenOf($target) ?? [] as $key => $child) {
                array_push($nodes, ...$this->descendants($child, [...$keys, $key], $seen));
            }
        }

        return $nodes;
    }

    /**
     * Determine whether $segments resolve inside $target (null values count as present).
     *
     * A wildcard matches when at least one child resolves.
     */
    protected function dataHas(mixed $target, array $segments, int $offset = 0): bool
    {
        $missing = static::missing();

        for ($i = $offset, $count = count($segments); $i < $count; $i++) {
            $segment = $segments[$i];

            if (is_string($segment)) {
                $target = $this->readChild($target, $segment, $missing);

                if ($target === $missing) {
                    return false;
                }

                continue;
            }

            if ($segment['t'] === 'd') {
                return $this->dataMatches($target, $segments, $i) !== [];
            }

            foreach ($this->childrenOf($target) ?? [] as $child) {
                if (
                    ($segment['f'] === null || $this->passesFilters($segment['f'], $child))
                    && $this->dataHas($child, $segments, $i + 1)
                ) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    // -----------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------

    /**
     * Write $value at $segments inside $target, creating intermediate arrays.
     *
     * - $overwrite false only fills keys that do not exist yet
     * - $cloneObjects true clones objects along the write path, so a copied
     *   array can be modified without touching objects it shares with the original
     * - recursive descent only writes to paths that already exist
     */
    protected function dataSet(
        mixed &$target,
        array $segments,
        mixed $value,
        bool $overwrite = true,
        bool $cloneObjects = false,
        int $offset = 0,
    ): void {
        if (is_object($target) && $cloneObjects) {
            $target = clone $target;
        }

        if ($target instanceof self) {
            $this->dataSet($target->items, $segments, $value, $overwrite, $cloneObjects, $offset);

            return;
        }

        $segment = $segments[$offset];
        $last = $offset === count($segments) - 1;

        if (is_array($segment)) {
            if ($segment['t'] === 'd') {
                if ($overwrite) {
                    foreach ($this->dataMatches($target, $segments, $offset) as [$keys]) {
                        $this->dataSet($target, static::literal($keys), $value, true, $cloneObjects);
                    }
                }

                return;
            }

            if (!is_array($target) && !is_object($target)) {
                $target = [];
            }

            foreach ($this->selectedKeys($target, $segment) as $key) {
                if ($last) {
                    if ($overwrite) {
                        $this->writeChild($target, $key, $value);
                    }

                    continue;
                }

                if (is_array($target)) {
                    $this->dataSet($target[$key], $segments, $value, $overwrite, $cloneObjects, $offset + 1);

                    continue;
                }

                $child = $this->readChild($target, (string) $key, null);
                $this->dataSet($child, $segments, $value, $overwrite, $cloneObjects, $offset + 1);
                $this->writeChild($target, $key, $child);
            }

            return;
        }

        if (!is_array($target) && !is_object($target)) {
            $target = [];
        }

        if (is_array($target)) {
            $exists = array_key_exists($segment, $target);

            if ($last) {
                if ($overwrite || !$exists) {
                    $target[$segment] = $value;
                }

                return;
            }

            if (!$exists) {
                $target[$segment] = [];
            }

            $this->dataSet($target[$segment], $segments, $value, $overwrite, $cloneObjects, $offset + 1);

            return;
        }

        $missing = static::missing();
        $child = $this->readChild($target, $segment, $missing);

        if ($last) {
            if ($overwrite || $child === $missing) {
                $this->writeChild($target, $segment, $value);
            }

            return;
        }

        if ($child === $missing) {
            $child = [];
        }

        $this->dataSet($child, $segments, $value, $overwrite, $cloneObjects, $offset + 1);
        $this->writeChild($target, $segment, $child);
    }

    /**
     * Remove the value at $segments inside $target.
     *
     * A trailing wildcard empties the matched level (or removes the children
     * passing its filter).
     */
    protected function dataForget(mixed &$target, array $segments, bool $cloneObjects = false, int $offset = 0): void
    {
        if (!is_array($target) && !is_object($target)) {
            return;
        }

        if (is_object($target) && $cloneObjects) {
            $target = clone $target;
        }

        if ($target instanceof self) {
            $this->dataForget($target->items, $segments, $cloneObjects, $offset);

            return;
        }

        $segment = $segments[$offset];
        $last = $offset === count($segments) - 1;

        if (is_array($segment)) {
            if ($segment['t'] === 'd') {
                foreach (array_reverse($this->dataMatches($target, $segments, $offset)) as [$keys]) {
                    if ($keys !== []) {
                        $this->dataForget($target, static::literal($keys), $cloneObjects);
                    }
                }

                return;
            }

            foreach ($this->selectedKeys($target, $segment) as $key) {
                if ($last) {
                    $this->unsetChild($target, $key);

                    continue;
                }

                if (is_array($target)) {
                    $this->dataForget($target[$key], $segments, $cloneObjects, $offset + 1);

                    continue;
                }

                $child = $this->readChild($target, (string) $key, null);
                $this->dataForget($child, $segments, $cloneObjects, $offset + 1);
                $this->writeChild($target, $key, $child);
            }

            return;
        }

        $missing = static::missing();
        $child = $this->readChild($target, $segment, $missing);

        if ($child === $missing) {
            return;
        }

        if ($last) {
            $this->unsetChild($target, $segment);

            return;
        }

        if (is_array($target)) {
            $this->dataForget($target[$segment], $segments, $cloneObjects, $offset + 1);

            return;
        }

        if (is_array($child) || is_object($child)) {
            $this->dataForget($child, $segments, $cloneObjects, $offset + 1);
            $this->writeChild($target, $segment, $child);
        }
    }

    /**
     * Replace every existing value at $segments with $callback($value).
     *
     * Missing paths are left untouched; wildcards apply the callback per match.
     */
    protected function dataTransform(
        mixed &$target,
        array $segments,
        Closure $callback,
        bool $cloneObjects = false,
        int $offset = 0,
    ): void {
        if ($offset === count($segments)) {
            $target = $callback($target);

            return;
        }

        if (!is_array($target) && !is_object($target)) {
            return;
        }

        if (is_object($target) && $cloneObjects) {
            $target = clone $target;
        }

        if ($target instanceof self) {
            $this->dataTransform($target->items, $segments, $callback, $cloneObjects, $offset);

            return;
        }

        $segment = $segments[$offset];

        if (is_array($segment)) {
            if ($segment['t'] === 'd') {
                foreach ($this->dataMatches($target, $segments, $offset) as [$keys]) {
                    $this->dataTransform($target, static::literal($keys), $callback, $cloneObjects);
                }

                return;
            }

            foreach ($this->selectedKeys($target, $segment) as $key) {
                if (is_array($target)) {
                    $this->dataTransform($target[$key], $segments, $callback, $cloneObjects, $offset + 1);

                    continue;
                }

                $child = $this->readChild($target, (string) $key, null);
                $this->dataTransform($child, $segments, $callback, $cloneObjects, $offset + 1);
                $this->writeChild($target, $key, $child);
            }

            return;
        }

        if (is_array($target)) {
            if (array_key_exists($segment, $target)) {
                $this->dataTransform($target[$segment], $segments, $callback, $cloneObjects, $offset + 1);
            }

            return;
        }

        $missing = static::missing();
        $child = $this->readChild($target, $segment, $missing);

        if ($child === $missing) {
            return;
        }

        $this->dataTransform($child, $segments, $callback, $cloneObjects, $offset + 1);
        $this->writeChild($target, $segment, $child);
    }

    /**
     * Extract the sub-structure at $segments as nested arrays.
     *
     * pick(['user' => ['name' => 'A', 'age' => 3]], 'user.name') -> ['user' => ['name' => 'A']]
     */
    protected function dataPick(mixed $target, array $segments, int $offset = 0): mixed
    {
        $missing = static::missing();
        $count = count($segments);

        if ($offset === $count) {
            return $target;
        }

        $segment = $segments[$offset];

        if (is_array($segment)) {
            if ($segment['t'] === 'd') {
                $result = [];

                foreach ($this->dataMatches($target, $segments, $offset) as [$keys, $value]) {
                    if ($keys === []) {
                        return $value;
                    }

                    $picked = [];
                    $this->dataSet($picked, static::literal($keys), $value);
                    $result = array_replace_recursive($result, $picked);
                }

                return $result === [] ? $missing : $result;
            }

            $children = $this->childrenOf($target);

            if ($children === null) {
                return $missing;
            }

            $result = [];

            foreach ($children as $key => $child) {
                if ($segment['f'] !== null && !$this->passesFilters($segment['f'], $child)) {
                    continue;
                }

                $picked = $this->dataPick($child, $segments, $offset + 1);

                if ($picked !== $missing) {
                    $result[$key] = $picked;
                }
            }

            return $result === [] && $offset + 1 < $count ? $missing : $result;
        }

        $child = $this->readChild($target, $segment, $missing);

        if ($child === $missing) {
            return $missing;
        }

        $picked = $this->dataPick($child, $segments, $offset + 1);

        return $picked === $missing ? $missing : [$segment => $picked];
    }

    // -----------------------------------------------------------------
    // Low-level access
    // -----------------------------------------------------------------

    /**
     * Read a direct child of an array, collection, ArrayAccess or plain object.
     */
    private function readChild(mixed $target, string $key, mixed $missing): mixed
    {
        if (is_array($target)) {
            return array_key_exists($key, $target) ? $target[$key] : $missing;
        }

        if ($target instanceof self) {
            return array_key_exists($key, $target->items) ? $target->items[$key] : $missing;
        }

        if ($target instanceof ArrayAccess) {
            return $target->offsetExists($key) ? $target->offsetGet($key) : $missing;
        }

        if (is_object($target) && $key !== '') {
            if (isset($target->{$key})) {
                return $target->{$key};
            }

            return array_key_exists($key, get_object_vars($target)) ? $target->{$key} : $missing;
        }

        return $missing;
    }

    private function writeChild(mixed &$target, string|int $key, mixed $value): void
    {
        if (is_array($target)) {
            $target[$key] = $value;
        } elseif ($target instanceof self) {
            $target->items[$key] = $value;
        } elseif ($target instanceof ArrayAccess) {
            $target->offsetSet($key, $value);
        } elseif (is_object($target)) {
            $target->{$key} = $value;
        }
    }

    private function unsetChild(mixed &$target, string|int $key): void
    {
        if (is_array($target)) {
            unset($target[$key]);
        } elseif ($target instanceof self) {
            unset($target->items[$key]);
        } elseif ($target instanceof ArrayAccess) {
            $target->offsetUnset($key);
        } elseif (is_object($target)) {
            unset($target->{$key});
        }
    }

    /**
     * Children iterated by a wildcard, or null when $target cannot be iterated.
     */
    private function childrenOf(mixed $target): ?array
    {
        return match (true) {
            is_array($target) => $target,
            $target instanceof self => $target->items,
            $target instanceof Traversable => iterator_to_array($target),
            is_object($target) => get_object_vars($target),
            default => null,
        };
    }

    /**
     * Keys of the children a wildcard selector applies to.
     *
     * @return list<array-key>
     */
    private function selectedKeys(mixed $target, array $selector): array
    {
        $children = $this->childrenOf($target) ?? [];

        if ($selector['f'] === null) {
            return array_keys($children);
        }

        $keys = [];

        foreach ($children as $key => $child) {
            if ($this->passesFilters($selector['f'], $child)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Concrete keys as literal segments.
     *
     * @param  list<array-key>  $keys
     * @return list<string>
     */
    protected static function literal(array $keys): array
    {
        return array_map('strval', $keys);
    }

    /**
     * Whether $node passes every filter of a selector (filters are ANDed,
     * each filter is OR-groups of AND-conditions).
     */
    private function passesFilters(array $filters, mixed $node): bool
    {
        foreach ($filters as $groups) {
            $passed = false;

            foreach ($groups as $conditions) {
                foreach ($conditions as $condition) {
                    if (!$this->passesCondition($condition, $node)) {
                        continue 2;
                    }
                }

                $passed = true;

                break;
            }

            if (!$passed) {
                return false;
            }
        }

        return true;
    }

    private function passesCondition(array $condition, mixed $node): bool
    {
        $value = $this->dataGet($node, $condition['p']);

        if ($value === static::missing()) {
            $value = null;
        }

        if ($condition['op'] === null) {
            return $condition['not'] ? !$value : (bool) $value;
        }

        return static::compareValues($value, $condition['op'], $condition['v']);
    }

    // -----------------------------------------------------------------
    // Callbacks & comparison
    // -----------------------------------------------------------------

    /**
     * Determine whether a value should be called rather than compared or used as a path.
     *
     * Strings are never treated as callables, so 'count' or 'date' stay paths/values.
     */
    protected function useAsCallable(mixed $value): bool
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * Limit the arguments passed to built-in PHP functions.
     *
     * Collections call callbacks with ($value, $key); built-ins such as
     * strtoupper(...) or intval(...) would fail or misbehave with the extra key.
     * They receive only their required arguments (at least one). User-defined
     * callables are returned untouched.
     */
    protected static function adaptCallback(callable $callback): callable
    {
        if (!$callback instanceof Closure && !is_string($callback) && !is_array($callback)) {
            return $callback;
        }

        try {
            $reflection = new ReflectionFunction(Closure::fromCallable($callback));
        } catch (ReflectionException) {
            return $callback;
        }

        if (!$reflection->isInternal() || $reflection->isVariadic()) {
            return $callback;
        }

        $arity = max(1, $reflection->getNumberOfRequiredParameters());

        return static fn (mixed ...$args) => $callback(...array_slice($args, 0, $arity));
    }

    /**
     * Build a callback that retrieves a value from an item.
     *
     * - callable  -> used as-is (built-ins get only the value)
     * - null      -> returns the item itself
     * - path      -> returns the value at the path, null when missing
     *
     * Plain paths get a fast route for arrays; everything else falls back
     * to the full path engine.
     */
    protected function valueRetriever(mixed $value): callable
    {
        if ($this->useAsCallable($value)) {
            return static::adaptCallback($value);
        }

        if ($value === null) {
            return static fn ($item) => $item;
        }

        $segments = $this->toSegments($value);
        $missing = static::missing();

        $slow = function ($item) use ($segments, $missing) {
            $found = $this->dataGet($item, $segments);

            return $found === $missing ? null : $found;
        };

        if (!static::isPlainPath($segments)) {
            return $slow;
        }

        if (count($segments) === 1) {
            $key = $segments[0];

            return static fn ($item) => is_array($item) && isset($item[$key]) ? $item[$key] : $slow($item);
        }

        return static function ($item) use ($segments, $slow) {
            $target = $item;

            foreach ($segments as $key) {
                if (!is_array($target) || !isset($target[$key])) {
                    return $slow($item);
                }

                $target = $target[$key];
            }

            return $target;
        };
    }

    /**
     * Build a predicate: callables are used as-is, any other value is compared strictly.
     */
    protected function equalityPredicate(mixed $value): callable
    {
        return $this->useAsCallable($value)
            ? static::adaptCallback($value)
            : static fn ($item) => $item === $value;
    }

    /**
     * Build a predicate from where()-style arguments.
     *
     *   (callable)                -> the callable
     *   (path)                    -> value at path is truthy
     *   (path, value)             -> value at path == value
     *   (path, operator, value)   -> value at path <operator> value
     *
     * Missing paths read as null, exactly like Laravel's data_get().
     *
     * @throws InvalidArgumentException on an unsupported operator
     */
    protected function operatorForWhere(mixed $key, mixed $operator = null, mixed $value = null): callable
    {
        if ($this->useAsCallable($key)) {
            return static::adaptCallback($key);
        }

        $argc = func_num_args();

        if ($argc === 1) {
            $value = true;
            $operator = '=';
        } elseif ($argc === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = static::assertOperator($operator);
        $retrieve = $this->valueRetriever($key);

        return static fn ($item) => static::compareValues($retrieve($item), $operator, $value);
    }

    /**
     * Supported comparison operators.
     */
    private const array OPERATORS = [
        '=' => true, '==' => true, '===' => true, '!=' => true, '<>' => true, '!==' => true,
        '<' => true, '>' => true, '<=' => true, '>=' => true, '<=>' => true,
    ];

    /**
     * @throws InvalidArgumentException on an unsupported operator
     */
    protected static function assertOperator(mixed $operator): string
    {
        if (!is_string($operator) || !isset(self::OPERATORS[$operator])) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported operator [%s]. Supported: = == === != <> !== < > <= >= <=>.',
                is_scalar($operator) ? (string) $operator : get_debug_type($operator),
            ));
        }

        return $operator;
    }

    /**
     * Compare two values with an operator, Laravel-style: an object only
     * equals another object or a string (through __toString()).
     */
    protected static function compareValues(mixed $retrieved, string $operator, mixed $value): bool
    {
        if (is_object($retrieved) !== is_object($value)) {
            $stringy = (is_string($retrieved) || $retrieved instanceof Stringable)
                && (is_string($value) || $value instanceof Stringable);

            if (!$stringy) {
                return $operator === '!=' || $operator === '<>' || $operator === '!==';
            }
        }

        return match ($operator) {
            '=', '==' => $retrieved == $value,
            '!=', '<>' => $retrieved != $value,
            '<' => $retrieved < $value,
            '>' => $retrieved > $value,
            '<=' => $retrieved <= $value,
            '>=' => $retrieved >= $value,
            '===' => $retrieved === $value,
            '!==' => $retrieved !== $value,
            '<=>' => (bool) ($retrieved <=> $value),
            default => throw new InvalidArgumentException("Unsupported operator [{$operator}]."),
        };
    }

    /**
     * Convert a value into a valid array key.
     *
     * null -> '', bool -> 0/1, enums -> value/name, Stringable -> string,
     * fractional floats -> string, arrays/objects -> JSON.
     */
    protected static function normalizeKey(mixed $key): int|string
    {
        return match (true) {
            is_int($key), is_string($key) => $key,
            $key === null => '',
            is_bool($key) => (int) $key,
            is_float($key) => is_finite($key) && floor($key) === $key && abs($key) < PHP_INT_MAX
                ? (int) $key
                : (string) $key,
            $key instanceof BackedEnum => $key->value,
            $key instanceof UnitEnum => $key->name,
            $key instanceof Stringable => (string) $key,
            default => json_encode(
                $key,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
            ) ?: get_debug_type($key),
        };
    }
}

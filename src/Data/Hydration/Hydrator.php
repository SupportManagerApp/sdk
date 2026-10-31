<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration;

use ReflectionClass;

/**
 * Builds a DTO from a decoded JSON payload by reading its promoted constructor
 * parameters, so a DTO's `fromArray()` is one line.
 *
 * Each parameter reads the snake_case form of its name (`teamId` ← `team_id`)
 * unless a {@see Attributes\From}, {@see Attributes\Can} or
 * {@see Attributes\Meta} attribute says otherwise, and is cast by its declared
 * type:
 *
 * - A value that is missing or null gives the {@see Attributes\Defaults}
 *   value, else the constructor default, else null for a nullable type, else
 *   the type's zero: `''`, `0`, `0.0`, `false` or `[]`. (So a missing `id`
 *   declared `string` is `''`.)
 * - `string`, `int`, `float` and `bool` use PHP's casts, so `"12"` → `12`,
 *   `"1.5"` → `1.5`, `7` → `"7"` and `1` → `true`.
 * - `array` passes an array through (anything else counts as missing);
 *   {@see Attributes\ListOf} hydrates its elements into a list.
 * - A backed enum uses `tryFrom()` when nullable (unknown → null) and `from()`
 *   otherwise (unknown → `ValueError`).
 * - Any other class is a nested DTO, built from an array through its own
 *   `fromArray()` when it has one; when nullable, a missing or non-array value
 *   gives null, otherwise the DTO is built from `[]`.
 *
 * {@see Attributes\CastWith} hands a value to a custom {@see Caster}, and
 * `$overrides` supplies ready-made constructor arguments for anything else.
 * The reflected plan for each class is cached for the life of the process.
 */
final class Hydrator
{
    /** @var array<class-string, array<string, Parameter>> */
    private static array $plans = [];

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  array<array-key, mixed>  $data  A flat payload, e.g. {@see \SupportManager\Sdk\Http\ResourceObject::flat()}.
     * @param  array<string, mixed>  $overrides  Constructor arguments, by parameter name, to use as-is instead of hydrating.
     * @return T
     */
    public static function hydrate(string $class, array $data, array $overrides = []): object
    {
        $parameters = self::$plans[$class] ??= self::plan($class);

        foreach (array_keys($overrides) as $name) {
            if (! isset($parameters[$name])) {
                throw new HydrationException("{$class} has no constructor parameter \${$name} to override.");
            }
        }

        $arguments = [];

        foreach ($parameters as $name => $parameter) {
            $arguments[$name] = array_key_exists($name, $overrides) ? $overrides[$name] : $parameter->value($data);
        }

        return new $class(...$arguments);
    }

    /**
     * @param  class-string  $class
     * @return array<string, Parameter>
     */
    private static function plan(string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        $plan = [];

        foreach ($constructor?->getParameters() ?? [] as $reflection) {
            $parameter = Parameter::fromReflection($reflection);
            $plan[$parameter->name] = $parameter;
        }

        return $plan;
    }
}

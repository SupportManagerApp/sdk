<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration;

use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionParameter;
use SupportManager\Sdk\Data\Hydration\Attributes\CastWith;
use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Attributes\Source;

/**
 * The reflected, cached plan for hydrating one constructor parameter: where
 * its value lives, what to cast it to, and what to use when it is missing.
 *
 * @internal
 */
final readonly class Parameter
{
    /**
     * @param  non-empty-list<list<string>>  $paths  Key paths (split on `.`) tried in order.
     */
    private function __construct(
        public string $name,
        private array $paths,
        private Type $type,
        private bool $nullable,
        private ?Type $element,
        private ?Caster $caster,
        private bool $hasDefaults,
        private mixed $defaults,
        private bool $hasConstructorDefault,
        private mixed $constructorDefault,
    ) {}

    public static function fromReflection(ReflectionParameter $parameter): self
    {
        $context = $parameter->getDeclaringClass()?->getName().'::$'.$parameter->getName();
        $declared = $parameter->getType();

        if (! $declared instanceof ReflectionNamedType) {
            throw new HydrationException("{$context}: declare a single (optionally nullable) type to hydrate it.");
        }

        $type = Type::named($declared->getName(), $context);

        $sources = $parameter->getAttributes(Source::class, ReflectionAttribute::IS_INSTANCEOF);

        if (count($sources) > 1) {
            throw new HydrationException("{$context}: use only one of #[From], #[Can] and #[Meta].");
        }

        $paths = $sources === [] ? [self::snake($parameter->getName())] : $sources[0]->newInstance()->paths();

        $listOf = ($parameter->getAttributes(ListOf::class)[0] ?? null)?->newInstance();

        if ($listOf !== null && $type->kind !== Type::ARRAY) {
            throw new HydrationException("{$context}: #[ListOf] needs an array parameter.");
        }

        $castWith = ($parameter->getAttributes(CastWith::class)[0] ?? null)?->newInstance();

        if ($castWith !== null && ! is_subclass_of($castWith->caster, Caster::class)) {
            throw new HydrationException("{$context}: {$castWith->caster} does not implement ".Caster::class.'.');
        }

        $defaults = ($parameter->getAttributes(Defaults::class)[0] ?? null)?->newInstance();

        return new self(
            name: $parameter->getName(),
            paths: array_map(static fn (string $path): array => explode('.', $path), $paths),
            type: $type,
            nullable: $declared->allowsNull(),
            element: $listOf === null ? null : Type::named($listOf->type, $context.' #[ListOf]'),
            caster: $castWith === null ? null : new ($castWith->caster),
            hasDefaults: $defaults !== null,
            defaults: $defaults?->value,
            hasConstructorDefault: $parameter->isDefaultValueAvailable(),
            constructorDefault: $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
        );
    }

    /**
     * The constructor argument for this parameter, read from the input array.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function value(array $data): mixed
    {
        $value = $this->resolve($data);

        if ($this->caster !== null) {
            return $this->caster->cast($value, $data);
        }

        // A nested object or list must arrive as an array; anything else
        // counts as missing.
        if ($value !== null && $this->type->needsArray() && ! is_array($value)) {
            $value = null;
        }

        if ($value === null) {
            if ($this->hasDefaults) {
                $value = $this->defaults;
            } elseif ($this->hasConstructorDefault) {
                return $this->constructorDefault;
            } elseif ($this->nullable) {
                return null;
            } else {
                return $this->type->zero();
            }
        }

        if ($this->element !== null && is_array($value)) {
            return $this->list($value);
        }

        return $this->type->cast($value, $this->nullable);
    }

    /**
     * `camelCase` to `snake_case`, keeping digit runs attached to the word
     * before them (`week1Month1` → `week1_month1`).
     */
    private static function snake(string $name): string
    {
        return mb_strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name));
    }

    /**
     * The first path that resolves to a non-null value, or null.
     *
     * @param  array<array-key, mixed>  $data
     */
    private function resolve(array $data): mixed
    {
        foreach ($this->paths as $segments) {
            $value = $data;

            foreach ($segments as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;

                    break;
                }

                $value = $value[$segment];
            }

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<mixed>
     */
    private function list(array $items): array
    {
        /** @var Type $element */
        $element = $this->element;
        $list = [];

        foreach ($items as $item) {
            [$keep, $value] = $element->castElement($item);

            if ($keep) {
                $list[] = $value;
            }
        }

        return $list;
    }
}

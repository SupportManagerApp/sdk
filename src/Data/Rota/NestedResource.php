<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * Helpers for reading resources nested inside a JSON:API attribute bag.
 *
 * The platform serialises related rota records (such as an application's
 * shift) inline as either a bare flat object or a
 * `{data: {id, attributes}}` JSON:API object. These helpers normalise both
 * shapes into the flat array a DTO's `fromArray()` expects.
 */
final readonly class NestedResource
{
    /**
     * Fold one nested resource into a flat `{id, ...attributes}` array.
     *
     * @param  array<string, mixed>  $nested
     * @return array<string, mixed>
     */
    public static function flatten(array $nested): array
    {
        /** @var array<string, mixed> $object */
        $object = is_array($nested['data'] ?? null) ? $nested['data'] : $nested;

        if (! is_array($object['attributes'] ?? null)) {
            return $object;
        }

        /** @var array<string, mixed> $attributes */
        $attributes = $object['attributes'];

        return [
            'id' => $object['id'] ?? null,
            ...$attributes,
        ];
    }

    /**
     * Fold a nested collection (`[...]` or `{data: [...]}`) into a list of flat
     * arrays.
     *
     * @param  array<array-key, mixed>  $nested
     * @return list<array<string, mixed>>
     */
    public static function flattenMany(array $nested): array
    {
        /** @var array<array-key, mixed> $items */
        $items = is_array($nested['data'] ?? null) ? $nested['data'] : $nested;

        $flat = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $flat[] = self::flatten($item);
            }
        }

        return $flat;
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

/**
 * Serialises stored records back into the exact response bodies the platform
 * emits, so the fake transport speaks the same wire dialect the SDK decodes.
 *
 * Two dialects, selected by `$type`:
 *  - JSON:API (`$type` is the resource type, e.g. `car-journeys`): records are
 *    wrapped as `{data: {type, id, attributes}}`, the shape {@see
 *    \SupportManager\Sdk\Http\Document} expects.
 *  - flat (`$type` is null): the record is returned verbatim under `data`, for
 *    the singleton/plain endpoints (keysafe, meal plan, estimated pay, …) that
 *    return a bare `{data: {...}}` rather than a JSON:API document.
 */
final class Json
{
    /**
     * Wrap one record as a single-resource document.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function item(?string $type, array $record): array
    {
        if ($type === null) {
            return ['data' => $record];
        }

        return ['data' => self::object($type, $record)];
    }

    /**
     * Wrap a list of records as a collection document with pagination metadata.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     * @return array<string, mixed>
     */
    public static function collection(?string $type, array $records, array $meta = [], array $links = []): array
    {
        $data = $type === null
            ? array_values($records)
            : array_map(static fn (array $record): array => self::object($type, $record), array_values($records));

        return ['data' => $data, 'meta' => $meta, 'links' => $links];
    }

    /**
     * The `{type, id, attributes}` object for one JSON:API record.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function object(string $type, array $record): array
    {
        $attributes = $record;
        unset($attributes['id']);

        return [
            'type' => $type,
            'id' => (string) ($record['id'] ?? ''),
            'attributes' => $attributes,
        ];
    }
}

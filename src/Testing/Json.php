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
            return ['data' => self::withoutBookkeeping($record)];
        }

        return ['data' => self::object($type, $record)];
    }

    /**
     * The `{data: {type, id, attributes}}` shape one JSON:API resource takes
     * when another resource nests it (e.g. a shift application's `rota_entry`).
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function nested(string $type, array $record): array
    {
        return self::item($type, $record);
    }

    /**
     * Wrap a list of records as a collection document. `meta` and `links` are
     * emitted only when given, as the platform emits them only for paginated
     * (or explicitly decorated) collections.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     * @return array<string, mixed>
     */
    public static function collection(?string $type, array $records, array $meta = [], array $links = []): array
    {
        $data = $type === null
            ? array_map(self::withoutBookkeeping(...), array_values($records))
            : array_map(static fn (array $record): array => self::object($type, $record), array_values($records));

        return [
            'data' => $data,
            ...($meta === [] ? [] : ['meta' => $meta]),
            ...($links === [] ? [] : ['links' => $links]),
        ];
    }

    /**
     * The `{type, id, attributes}` object for one JSON:API record. A record's
     * `meta` key (e.g. `meta.can` permission flags) is lifted out of the
     * attributes to the resource-level `meta` member, as the platform emits it.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function object(string $type, array $record): array
    {
        $attributes = self::withoutBookkeeping($record);
        unset($attributes['id'], $attributes['meta']);

        return [
            'type' => $type,
            'id' => (string) ($record['id'] ?? ''),
            'attributes' => $attributes,
            ...(is_array($record['meta'] ?? null) ? ['meta' => $record['meta']] : []),
        ];
    }

    /**
     * Drop the fake's own bookkeeping keys (those starting with `_`, e.g. the
     * `_today` flag a factory state sets for a custom route to filter on): the
     * platform never sends them.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function withoutBookkeeping(array $record): array
    {
        return array_filter($record, static fn (string|int $key): bool => ! is_string($key) || ! str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);
    }
}

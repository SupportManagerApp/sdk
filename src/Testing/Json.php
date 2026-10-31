<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

/**
 * Serialises stored records back into the exact response bodies the platform
 * emits, so the fake transport speaks the same wire dialect the SDK decodes.
 *
 * Two dialects, selected by `$type`:
 *  - JSON:API (`$type` is the resource type, e.g. `car_journeys`): records are
 *    wrapped as `{data: {type, id, attributes}}`, the shape {@see
 *    \SupportManager\Sdk\Http\Document} expects.
 *  - flat (`$type` is null): the record is returned verbatim under `data`, for
 *    the singleton/plain endpoints (keysafe, meal plan, estimated pay, …) that
 *    return a bare `{data: {...}}` rather than a JSON:API document.
 *
 * The body builders ({@see item()}, {@see collection()}, {@see flat()}) return
 * arrays for `Http::response()`. The error helpers ({@see notFound()},
 * {@see forbidden()}, {@see validation()}, {@see message()}) return the whole
 * response, because the status is part of what they describe.
 */
final class Json
{
    /**
     * The fake's catch-all 422 message, for a refusal that names no single
     * field. {@see Fake::failValidation()} uses it too.
     */
    public const string INVALID = 'The information provided was invalid.';

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
     * The plain `{data, meta}` envelope the platform's non-JSON:API endpoints
     * answer. `$data` is emitted as given, an object or a list. `meta` is
     * emitted only when given, typically `['team_id' => $fake->teamId()]`.
     *
     * @param  array<array-key, mixed>|null  $data
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function flat(?array $data, array $meta = []): array
    {
        return [
            'data' => $data,
            ...($meta === [] ? [] : ['meta' => $meta]),
        ];
    }

    /**
     * A plain `{message, meta?}` response: an action's acknowledgement, or a
     * refusal that names no field (the platform's bare 422s and 403s).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function message(string $message, int $status = 200, array $meta = []): PromiseInterface
    {
        return Http::response([
            'message' => $message,
            ...($meta === [] ? [] : ['meta' => $meta]),
        ], $status);
    }

    /**
     * The 404 for a record that isn't in the store.
     */
    public static function notFound(string $message = 'Not found'): PromiseInterface
    {
        return self::message($message, 404);
    }

    /**
     * The 403 the platform's policies answer, mapped by the SDK to an
     * authorization exception.
     */
    public static function forbidden(string $message = 'This action is unauthorized.'): PromiseInterface
    {
        return self::message($message, 403);
    }

    /**
     * The 422 `{message, errors}` body Laravel's validator emits, mapped by the
     * SDK to an {@see \SupportManager\Sdk\Exceptions\ApiValidationException}.
     * A scalar message becomes a one-item list, so both `['name' => 'Required']`
     * and `['name' => ['Required']]` work. With no `$message`, the first error
     * stands in for it, as Laravel does for a single failure.
     *
     * @param  array<string, string|list<string>>  $errors
     */
    public static function validation(array $errors, ?string $message = null): PromiseInterface
    {
        $normalised = array_map(
            static fn (string|array $messages): array => array_values((array) $messages),
            $errors,
        );

        $first = array_values($normalised)[0][0] ?? self::INVALID;

        return Http::response(['message' => $message ?? $first, 'errors' => $normalised], 422);
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

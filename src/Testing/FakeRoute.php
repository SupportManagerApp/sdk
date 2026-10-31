<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * One fake server route — a method + path pattern bound to a handler that
 * answers it against the {@see RecordStore}.
 *
 * A resource registers these (via {@see ProvidesFakeRoutes}) for the endpoints
 * the generic REST machinery can't infer: non-RESTful actions
 * (`car-journeys/start`, `notifications/{id}/read`), flat-singleton reveals, or
 * index endpoints with bespoke filtering. Matched routes are tried before the
 * generic CRUD fallback, so they take precedence.
 *
 * Patterns are written against the `/api/v1`-relative path with `{id}`-style
 * placeholders, e.g. `car-journeys/{id}/finish`; captured segments arrive in the
 * handler's `$matches` (1-indexed, as from `preg_match`). A handler is called
 * with the store, the matches, the request, the {@see Fake} and the owning
 * factory's {@see Registration}, and may declare only the leading parameters
 * it uses. It returns an `Http::response(...)` to answer, or null to fall
 * through to the next route.
 *
 * The common shapes have builders that read the owning factory's path and
 * JSON:API type from its registration, so they need no handler at all:
 *
 * ```php
 * FakeRoute::action('PATCH', 'leave/requests/{id}/approve', fn (array $request): array => ['status' => 'Approved']);
 * FakeRoute::download('payroll/payslips/{id}/download', '%PDF-1.4 payslip-bytes');
 * FakeRoute::downloadLink('documents/documents/{id}/download', 'name');
 * FakeRoute::collection('rota/shifts/today', fn (RecordStore $store): array => $store->all('rota/shifts'));
 * FakeRoute::flat('rota/calendar-feed', fn (RecordStore $store): array => ['url' => null], teamMeta: true);
 * ```
 */
final class FakeRoute
{
    /**
     * @param  string  $method  Upper-case HTTP method.
     * @param  string  $regex  Compiled full-match regex for the relative path.
     * @param  Closure(RecordStore, array<int, string>, Request, Fake, Registration): mixed  $handler
     */
    public function __construct(
        public readonly string $method,
        public readonly string $regex,
        public readonly Closure $handler,
    ) {}

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake, Registration): mixed  $handler
     */
    public static function get(string $pattern, Closure $handler): self
    {
        return self::make('GET', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake, Registration): mixed  $handler
     */
    public static function post(string $pattern, Closure $handler): self
    {
        return self::make('POST', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake, Registration): mixed  $handler
     */
    public static function patch(string $pattern, Closure $handler): self
    {
        return self::make('PATCH', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake, Registration): mixed  $handler
     */
    public static function put(string $pattern, Closure $handler): self
    {
        return self::make('PUT', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake, Registration): mixed  $handler
     */
    public static function delete(string $pattern, Closure $handler): self
    {
        return self::make('DELETE', $pattern, $handler);
    }

    /**
     * An action on one stored record (approve, finish, withdraw, …): find the
     * record whose id is the pattern's first placeholder, merge in the changes
     * `$changes` works out from it, and answer the updated record as an item,
     * or a 404 when it isn't stored.
     *
     * `$changes` is called with the stored record, the request and the fake.
     * Returning a response instead of an array refuses the action (a 403 or
     * 422 guard) and leaves the record as it was.
     *
     * ```php
     * FakeRoute::action('PATCH', 'leave/requests/{id}/reject', fn (array $record, Request $request): array => [
     *     'status' => 'Rejected',
     *     'approver_comments' => $request->data()['approver_comments'] ?? null,
     * ]);
     * ```
     *
     * @param  Closure(array<string, mixed>, Request, Fake): (array<string, mixed>|PromiseInterface)  $changes
     */
    public static function action(string $method, string $pattern, Closure $changes): self
    {
        return self::make(mb_strtoupper($method), $pattern, static function (RecordStore $store, array $matches, Request $request, Fake $fake, Registration $registration) use ($changes): PromiseInterface {
            $record = $store->find($registration->path, $matches[1]);

            if ($record === null) {
                return Json::notFound();
            }

            $result = $changes($record, $request, $fake);

            if ($result instanceof PromiseInterface) {
                return $result;
            }

            $updated = $store->update($registration->path, $matches[1], $result) ?? $record;

            return Http::response(Json::item($registration->type, $updated));
        });
    }

    /**
     * A `GET` that streams a stored record's file as raw bytes, or a 404 when
     * the record whose id is the pattern's first placeholder isn't stored.
     * `$changes` are merged into the record as it's downloaded (e.g.
     * `['viewed' => true]`); a `$filename` adds a `Content-Disposition` header.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function download(string $pattern, string $contents, string $contentType = 'application/pdf', ?string $filename = null, array $changes = []): self
    {
        return self::make('GET', $pattern, static function (RecordStore $store, array $matches, Request $request, Fake $fake, Registration $registration) use ($contents, $contentType, $filename, $changes): PromiseInterface {
            $found = $changes === []
                ? $store->find($registration->path, $matches[1])
                : $store->update($registration->path, $matches[1], $changes);

            if ($found === null) {
                return Json::notFound();
            }

            return Http::response($contents, 200, [
                'Content-Type' => $contentType,
                ...($filename === null ? [] : ['Content-Disposition' => "attachment; filename=\"{$filename}\""]),
            ]);
        });
    }

    /**
     * A `GET` that answers a short-lived signed link to a stored record's file,
     * as the flat `{data: {download_url, filename, expires_at}}` body. It 404s
     * when the record isn't stored or has no file.
     *
     * `$filename` names the attribute holding the stored filename, or is a
     * closure of the record that returns the filename, null for no file, or a
     * response to refuse (e.g. {@see Json::forbidden()}). The link defaults to
     * `https://s3.test/signed/{collection}/{id}/{filename}`; `$url`, a closure
     * of the record and filename, overrides it.
     *
     * @param  string|Closure(array<string, mixed>): (string|PromiseInterface|null)  $filename
     * @param  (Closure(array<string, mixed>, string): string)|null  $url
     */
    public static function downloadLink(string $pattern, string|Closure $filename, ?Closure $url = null, string $expiresAt = '2026-01-01T09:05:00Z'): self
    {
        return self::make('GET', $pattern, static function (RecordStore $store, array $matches, Request $request, Fake $fake, Registration $registration) use ($filename, $url, $expiresAt): PromiseInterface {
            $record = $store->find($registration->path, $matches[1]);
            $name = $record === null ? null : ($filename instanceof Closure ? $filename($record) : $record[$filename] ?? null);

            if ($name instanceof PromiseInterface) {
                return $name;
            }

            if ($record === null || ! is_string($name) || $name === '') {
                return Json::notFound();
            }

            $collection = basename($registration->path);

            return Http::response(Json::flat([
                'download_url' => $url === null
                    ? "https://s3.test/signed/{$collection}/{$matches[1]}/".rawurlencode($name)
                    : $url($record, $name),
                'filename' => $name,
                'expires_at' => $expiresAt,
            ]));
        });
    }

    /**
     * A `GET` that answers a whole, unpaginated collection in the owning
     * factory's dialect: JSON:API resource objects for a typed factory, rows
     * verbatim for a flat one. `$records` is called like a handler and returns
     * the records to list; `teamMeta` adds `meta.team_id`.
     *
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): list<array<string, mixed>>  $records
     */
    public static function collection(string $pattern, Closure $records, bool $teamMeta = false): self
    {
        return self::make('GET', $pattern, static fn (RecordStore $store, array $matches, Request $request, Fake $fake, Registration $registration): PromiseInterface => Http::response(Json::collection(
            $registration->type,
            $records($store, $matches, $request, $fake),
            $teamMeta ? ['team_id' => $fake->teamId()] : [],
        )));
    }

    /**
     * A route that answers the plain `{data, meta?}` envelope, for singleton
     * reads and summaries that aren't JSON:API. `$data` is called like a
     * handler and returns what goes under `data`; `teamMeta` adds
     * `meta.team_id`.
     *
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): (array<array-key, mixed>|null)  $data
     */
    public static function flat(string $pattern, Closure $data, bool $teamMeta = false, string $method = 'GET'): self
    {
        return self::make(mb_strtoupper($method), $pattern, static fn (RecordStore $store, array $matches, Request $request, Fake $fake): PromiseInterface => Http::response(Json::flat(
            $data($store, $matches, $request, $fake),
            $teamMeta ? ['team_id' => $fake->teamId()] : [],
        )));
    }

    /**
     * The captured path segments when this route answers the given method and
     * path (element 0 is the whole match, as from `preg_match`), or null when it
     * does not.
     *
     * @return array<int, string>|null
     */
    public function match(string $method, string $path): ?array
    {
        if ($method !== $this->method) {
            return null;
        }

        $matches = [];

        return preg_match($this->regex, $path, $matches) === 1 ? $matches : null;
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake, Registration): mixed  $handler
     */
    private static function make(string $method, string $pattern, Closure $handler): self
    {
        // Quote the literal path, then swap each escaped `\{name\}` placeholder
        // for a single-segment capture group.
        $escaped = preg_quote($pattern, '#');
        $regex = preg_replace('/\\\\\{[^}]*\\\\\}/', '([^/]+)', $escaped) ?? $escaped;

        return new self($method, '#^'.$regex.'$#', $handler);
    }
}

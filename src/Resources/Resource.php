<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use LogicException;
use SupportManager\Sdk\Client\AuthenticatedApi;
use SupportManager\Sdk\Concerns\MapsApiErrors;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Http\ResourceQuery;
use SupportManager\Sdk\Resources\Concerns\Findable;
use SupportManager\Sdk\Resources\Concerns\Listable;

/**
 * Base class for a tenant-scoped domain resource (car journeys, rota,
 * household, dashboard widgets, …).
 *
 * A resource declares its collection {@see self::path()} and how to {@see
 * self::hydrate()} one JSON:API resource object into a DTO; the request and
 * response helpers come from here. Collection reads are opt-in, because many
 * platform collections have no index or no show route: use {@see Listable}
 * for `query()` and `all()` over `GET {path}`, and {@see Findable} for
 * `find($id)` over `GET {path}/{id}`, only where the platform has them. Every
 * call routes through {@see AuthenticatedApi::tenant()}, so each one inherits
 * current-team scoping and transparent token renewal. This is the seam through
 * which the SDK grows beyond authentication: a new module is one resource class
 * plus its DTOs, shared by every client app rather than duplicated per app.
 *
 * Custom endpoints pick the reader that matches the body the platform sends.
 * Every reader maps an error response to a typed exception first:
 *
 * - JSON:API documents: {@see self::item()}, {@see self::collection()} and
 *   {@see self::paginated()}, hydrated through {@see self::hydrate()} or,
 *   given a DTO class, through that class's `fromArray()`.
 * - Plain `{data, meta}` bodies: {@see self::data()} for an object,
 *   {@see self::dataList()} for a list, {@see self::dataPaginated()} for a list
 *   plus its `meta`/`links`, and {@see self::raw()} when there is no DTO.
 * - Files: {@see self::downloadFile()} for a body that is the file itself, and
 *   {@see self::downloadLink()} for a `{data: {download_url, …}}` link.
 * - No body worth reading: `$this->throwForErrorResponse($this->delete(…))`.
 *
 * ```php
 * final class CarJourneysResource extends Resource
 * {
 *     use Findable;   // find($id): the platform has GET car-journeys/{id}
 *     use Listable;   // query(), all(): and GET car-journeys
 *
 *     protected function path(): string
 *     {
 *         return 'car-journeys';
 *     }
 *
 *     protected function hydrate(ResourceObject $object): CarJourney
 *     {
 *         return CarJourney::fromArray($object->flat());
 *     }
 *
 *     // JSON:API, hydrated by hydrate(); null on an empty `data`.
 *     public function ongoing(): ?CarJourney
 *     {
 *         return $this->item($this->get('car-journeys/ongoing'));
 *     }
 *
 *     // A plain `{data: {...}}` body, hydrated by the DTO's fromArray().
 *     public function mileage(): ?Mileage
 *     {
 *         return $this->data($this->get('car-journeys/mileage'), Mileage::class);
 *     }
 *
 *     // A body that is the PDF itself.
 *     public function downloadExport(): Download
 *     {
 *         return $this->downloadFile('car-journeys/export');
 *     }
 * }
 *
 * // SupportManager::journeys()->query()->filter('ongoing', true)->sort('-trip_start')->get();
 * ```
 *
 * @template T
 */
abstract class Resource
{
    use MapsApiErrors;

    public function __construct(protected readonly AuthenticatedApi $api) {}

    /**
     * The collection path, relative to /api/v1, e.g. `car-journeys`.
     */
    abstract protected function path(): string;

    /**
     * Turn one JSON:API resource object into this resource's DTO.
     *
     * @return T
     */
    abstract protected function hydrate(ResourceObject $object): mixed;

    /**
     * A fluent query against this resource's collection. Protected: the
     * public `query()`, `all()` and `find()` come from the {@see Listable} and
     * {@see Findable} traits, which a resource uses only when the platform
     * has the route.
     *
     * @return ResourceQuery<T>
     */
    protected function newQuery(): ResourceQuery
    {
        return new ResourceQuery($this->api, $this->path(), $this->hydrate(...));
    }

    // ──────────────────────────────── Requests ────────────────────────────────

    /**
     * Issue a tenant-scoped GET against an arbitrary path, e.g. a non-RESTful
     * action like `car-journeys/ongoing`. The path is relative to /api/v1.
     *
     * @param  array<string, mixed>  $query
     */
    protected function get(string $uri, array $query = []): Response
    {
        return $this->api->tenant(fn (PendingRequest $request) => $request->get($uri, $query));
    }

    /**
     * Issue a tenant-scoped POST.
     *
     * @param  array<string, mixed>  $data
     */
    protected function post(string $uri, array $data = []): Response
    {
        return $this->api->tenant(fn (PendingRequest $request) => $request->post($uri, $data));
    }

    /**
     * Issue a tenant-scoped PATCH.
     *
     * @param  array<string, mixed>  $data
     */
    protected function patch(string $uri, array $data = []): Response
    {
        return $this->api->tenant(fn (PendingRequest $request) => $request->patch($uri, $data));
    }

    /**
     * Issue a tenant-scoped PUT.
     *
     * @param  array<string, mixed>  $data
     */
    protected function put(string $uri, array $data = []): Response
    {
        return $this->api->tenant(fn (PendingRequest $request) => $request->put($uri, $data));
    }

    /**
     * Issue a tenant-scoped DELETE.
     *
     * @param  array<string, mixed>  $data
     */
    protected function delete(string $uri, array $data = []): Response
    {
        return $this->api->tenant(fn (PendingRequest $request) => $request->delete($uri, $data));
    }

    /**
     * Escape hatch: issue a tenant-scoped request through the renewal-aware
     * client and get the raw {@see Response} back, for the rare endpoint the
     * helpers here do not cover (multipart uploads, for instance).
     *
     * @param  Closure(PendingRequest): Response  $callback
     */
    protected function tenant(Closure $callback): Response
    {
        return $this->api->tenant($callback);
    }

    // ───────────────────────────── JSON:API bodies ─────────────────────────────

    /**
     * Hydrate a single DTO from a JSON:API item response, or null when the body
     * carries no resource (e.g. a 404 `ongoing` lookup).
     *
     * The resource object goes through {@see self::hydrate()}, or through
     * `$dto::fromArray($object->flat())` when the endpoint returns some other
     * type, e.g. `$this->item($response, Address::class)`.
     *
     * @template D of object
     *
     * @param  class-string<D>|null  $dto
     * @return ($dto is null ? T|null : D|null)
     */
    protected function item(Response $response, ?string $dto = null): mixed
    {
        $this->throwForErrorResponse($response);

        $resource = Document::fromArray($response->json() ?? [])->resource();

        if ($resource === null) {
            return null;
        }

        return $dto === null ? $this->hydrate($resource) : self::hydrateAs($dto, $resource->flat());
    }

    /**
     * Hydrate a list of DTOs from a JSON:API collection response, ignoring
     * pagination metadata. As with {@see self::item()}, pass `$dto` when the
     * endpoint returns a type other than this resource's own.
     *
     * @template D of object
     *
     * @param  class-string<D>|null  $dto
     * @return ($dto is null ? list<T> : list<D>)
     */
    protected function collection(Response $response, ?string $dto = null): array
    {
        $this->throwForErrorResponse($response);

        $resources = Document::fromArray($response->json() ?? [])->resources();

        if ($dto === null) {
            return array_map($this->hydrate(...), $resources);
        }

        return array_map(static fn (ResourceObject $resource): object => self::hydrateAs($dto, $resource->flat()), $resources);
    }

    /**
     * Hydrate a JSON:API collection response into a {@see Paginated} page,
     * keeping its `meta` and `links`. Pass a `$pageFetcher` (page number →
     * page) so callers can walk on with {@see Paginated::next()}; leave it out
     * for an endpoint that returns its whole list at once.
     *
     * @param  (Closure(int): Paginated<T>)|null  $pageFetcher
     * @return Paginated<T>
     */
    protected function paginated(Response $response, ?Closure $pageFetcher = null): Paginated
    {
        $this->throwForErrorResponse($response);

        $document = Document::fromArray($response->json() ?? []);

        return new Paginated(
            items: array_map($this->hydrate(...), $document->resources()),
            meta: $document->meta(),
            links: $document->links(),
            pageFetcher: $pageFetcher,
        );
    }

    // ─────────────────────────── Plain {data} bodies ───────────────────────────

    /**
     * Hydrate a DTO from a plain `{data: {...}}` body (not a JSON:API resource
     * object), or null when `data` is absent or not an object.
     *
     * @template D of object
     *
     * @param  class-string<D>  $dto  A DTO with a static `fromArray(array $data)`.
     * @return D|null
     */
    protected function data(Response $response, string $dto): ?object
    {
        $this->throwForErrorResponse($response);

        $data = $response->json('data');

        return is_array($data) ? self::hydrateAs($dto, $data) : null;
    }

    /**
     * Hydrate each row of a plain `{data: [...]}` list into a DTO. Rows that are
     * not objects are skipped, and a missing `data` is an empty list.
     *
     * @template D of object
     *
     * @param  class-string<D>  $dto  A DTO with a static `fromArray(array $data)`.
     * @return list<D>
     */
    protected function dataList(Response $response, string $dto): array
    {
        $this->throwForErrorResponse($response);

        return self::rows($response, $dto);
    }

    /**
     * Hydrate a plain `{data: [...], meta, links}` list into a {@see Paginated}
     * page, for an endpoint whose rows are not JSON:API resource objects. The
     * top-level `meta` and `links` ride along as they are; pass a
     * `$pageFetcher` when the endpoint really is paginated.
     *
     * @template D of object
     *
     * @param  class-string<D>  $dto  A DTO with a static `fromArray(array $data)`.
     * @param  (Closure(int): Paginated<D>)|null  $pageFetcher
     * @return Paginated<D>
     */
    protected function dataPaginated(Response $response, string $dto, ?Closure $pageFetcher = null): Paginated
    {
        $this->throwForErrorResponse($response);

        $meta = $response->json('meta');
        $links = $response->json('links');

        return new Paginated(
            items: self::rows($response, $dto),
            meta: is_array($meta) ? $meta : [],
            links: is_array($links) ? $links : [],
            pageFetcher: $pageFetcher,
        );
    }

    /**
     * The `data` member of a plain body as an array, for a payload with no DTO,
     * or a DTO that must never be null. Empty when `data` is absent or not an
     * array.
     *
     * @return array<array-key, mixed>
     */
    protected function raw(Response $response): array
    {
        $this->throwForErrorResponse($response);

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    // ───────────────────────────────── Files ─────────────────────────────────

    /**
     * GET an endpoint whose body is the file itself (a PDF, an image, …) and
     * wrap it in a {@see Download}. An error response (a JSON 403 or 404)
     * throws rather than coming back as a "file".
     *
     * @param  array<string, mixed>  $query
     */
    protected function downloadFile(string $uri, array $query = []): Download
    {
        $response = $this->get($uri, $query);

        $this->throwForErrorResponse($response);

        return new Download($response);
    }

    /**
     * GET an endpoint that answers with a short-lived link to the file, the
     * platform's `{data: {download_url, filename, expires_at}}`, rather than
     * with the file itself. An error response throws, and so does a success
     * that carries no URL.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws SupportManagerApiException
     */
    protected function downloadLink(string $uri, array $query = []): DownloadLink
    {
        $response = $this->get($uri, $query);

        $link = $this->data($response, DownloadLink::class);

        if ($link === null || $link->url === '') {
            throw new SupportManagerApiException(
                'The SupportManager download response did not include a download URL.',
                $response->status(),
                body: $response->body(),
            );
        }

        return $link;
    }

    // ─────────────────────────────── Deprecations ───────────────────────────────

    /**
     * Raise an `E_USER_DEPRECATED` from a renamed method's old name before it
     * forwards to the new one. Laravel sends it to the deprecations log
     * channel (`LOG_DEPRECATIONS_CHANNEL`), so callers get a warning instead of
     * a fatal error.
     */
    protected function deprecated(string $method, string $replacement): void
    {
        trigger_error(
            sprintf('%s::%s() is deprecated; call %s() instead.', static::class, $method, $replacement),
            E_USER_DEPRECATED,
        );
    }

    /**
     * Hydrate each object row of a plain `data` list.
     *
     * @template D of object
     *
     * @param  class-string<D>  $dto
     * @return list<D>
     */
    private static function rows(Response $response, string $dto): array
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return [];
        }

        $rows = [];

        foreach ($data as $row) {
            if (is_array($row)) {
                $rows[] = self::hydrateAs($dto, $row);
            }
        }

        return $rows;
    }

    /**
     * Build a DTO through its static `fromArray()`.
     *
     * @template D of object
     *
     * @param  class-string<D>  $dto
     * @param  array<array-key, mixed>  $data
     * @return D
     */
    private static function hydrateAs(string $dto, array $data): object
    {
        $factory = [$dto, 'fromArray'];

        if (! is_callable($factory)) {
            throw new LogicException("{$dto} has no static fromArray() to hydrate a response with.");
        }

        $object = $factory($data);

        if (! $object instanceof $dto) {
            throw new LogicException("{$dto}::fromArray() did not return a {$dto}.");
        }

        return $object;
    }
}

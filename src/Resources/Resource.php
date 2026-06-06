<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Client\AuthenticatedApi;
use SupportManager\Sdk\Concerns\MapsApiErrors;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Http\ResourceQuery;

/**
 * Base class for a tenant-scoped domain resource (car journeys, rota,
 * household, dashboard widgets, …).
 *
 * A resource declares its collection {@see self::path()} and how to {@see
 * self::hydrate()} one JSON:API resource object into a DTO; everything else —
 * fluent querying, pagination, single-item fetches — comes from here. Every
 * call routes through {@see AuthenticatedApi::tenant()}, so each one inherits
 * current-team scoping and transparent token renewal. This is the seam through
 * which the SDK grows beyond authentication: a new module is one resource class
 * plus its DTOs, shared by every client app rather than duplicated per app.
 *
 * ```php
 * final class CarJourneysResource extends Resource
 * {
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
 *     public function ongoing(): ?CarJourney
 *     {
 *         return $this->item($this->get('car-journeys/ongoing'));
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
     * Start a fluent query against this resource's collection.
     *
     * @return ResourceQuery<T>
     */
    public function query(): ResourceQuery
    {
        return new ResourceQuery($this->api, $this->path(), $this->hydrate(...));
    }

    /**
     * Fetch a single DTO by id, or null when it does not exist.
     *
     * @return T|null
     */
    public function find(string|int $id): mixed
    {
        return $this->query()->find($id);
    }

    /**
     * Every DTO in the collection's first page.
     *
     * @return Paginated<T>
     */
    public function all(): Paginated
    {
        return $this->query()->get();
    }

    // ──────────────────────────────── Helpers ────────────────────────────────

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
     * Hydrate a single DTO from a JSON:API item response, or null when the body
     * carries no resource (e.g. a 404 `ongoing` lookup).
     *
     * @return T|null
     */
    protected function item(Response $response): mixed
    {
        $this->throwForErrorResponse($response, $response->effectiveUri()?->getPath() ?? 'resource');

        $resource = Document::fromArray($response->json() ?? [])->resource();

        return $resource === null ? null : $this->hydrate($resource);
    }

    /**
     * Hydrate a list of DTOs from a JSON:API collection response, ignoring
     * pagination metadata.
     *
     * @return list<T>
     */
    protected function collection(Response $response): array
    {
        $this->throwForErrorResponse($response, $response->effectiveUri()?->getPath() ?? 'resource');

        return array_map($this->hydrate(...), Document::fromArray($response->json() ?? [])->resources());
    }

    /**
     * Escape hatch: issue a tenant-scoped request through the renewal-aware
     * client and get the raw {@see Response} back, for the rare endpoint the
     * helpers above do not cover.
     *
     * @param  Closure(PendingRequest): Response  $callback
     */
    protected function tenant(Closure $callback): Response
    {
        return $this->api->tenant($callback);
    }
}

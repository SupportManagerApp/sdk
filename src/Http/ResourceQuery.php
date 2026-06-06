<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Http;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use SupportManager\Sdk\Client\AuthenticatedApi;
use SupportManager\Sdk\Concerns\MapsApiErrors;

/**
 * A fluent, immutable query against a tenant-scoped collection endpoint.
 *
 * The builder mirrors the platform's Spatie QueryBuilder grammar one-to-one:
 * `filter()` → `filter[key]=value`, `sort()` → `sort=-a,b`, `include()` →
 * `include=user`, plus `page`/`per_page` pagination. Each builder method returns
 * a new instance, so a base query can be safely branched and reused.
 *
 * Terminal methods run the request through {@see AuthenticatedApi::tenant()} —
 * inheriting current-team scoping and transparent token renewal — decode the
 * JSON:API {@see Document}, and hand each resource object to the resource's
 * hydrator to produce DTOs.
 *
 * @template T
 */
final class ResourceQuery
{
    use MapsApiErrors;

    /** @var array<string, mixed> */
    private array $filters = [];

    /** @var list<string> */
    private array $sorts = [];

    /** @var list<string> */
    private array $includes = [];

    private ?int $page = null;

    private ?int $perPage = null;

    /**
     * @param  Closure(ResourceObject): T  $hydrator
     */
    public function __construct(
        private readonly AuthenticatedApi $api,
        private readonly string $path,
        private readonly Closure $hydrator,
    ) {}

    /**
     * Constrain the collection, e.g. `->filter('ongoing', true)`. Arrays become
     * comma-separated values, matching Spatie's multi-value filters.
     *
     * @return self<T>
     */
    public function filter(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->filters[$key] = is_array($value) ? implode(',', $value) : $value;

        return $clone;
    }

    /**
     * Append sort fields, in precedence order. Prefix a field with `-` for
     * descending, e.g. `->sort('-trip_start', 'driver_name')`.
     *
     * @return self<T>
     */
    public function sort(string ...$fields): self
    {
        $clone = clone $this;
        $clone->sorts = array_merge($clone->sorts, array_values($fields));

        return $clone;
    }

    /**
     * Request related resources be side-loaded into the document's `included`
     * member, e.g. `->include('user')`.
     *
     * @return self<T>
     */
    public function include(string ...$relations): self
    {
        $clone = clone $this;
        $clone->includes = array_merge($clone->includes, array_values($relations));

        return $clone;
    }

    /**
     * @return self<T>
     */
    public function page(int $page): self
    {
        $clone = clone $this;
        $clone->page = $page;

        return $clone;
    }

    /**
     * @return self<T>
     */
    public function perPage(int $perPage): self
    {
        $clone = clone $this;
        $clone->perPage = $perPage;

        return $clone;
    }

    /**
     * The query-string parameters this query will send.
     *
     * @return array<string, mixed>
     */
    public function params(): array
    {
        $params = [];

        if ($this->filters !== []) {
            $params['filter'] = $this->filters;
        }

        if ($this->sorts !== []) {
            $params['sort'] = implode(',', $this->sorts);
        }

        if ($this->includes !== []) {
            $params['include'] = implode(',', $this->includes);
        }

        if ($this->page !== null) {
            $params['page'] = $this->page;
        }

        if ($this->perPage !== null) {
            $params['per_page'] = $this->perPage;
        }

        return $params;
    }

    /**
     * Run the query and return a page of hydrated DTOs with pagination metadata.
     *
     * @return Paginated<T>
     */
    public function get(): Paginated
    {
        $document = $this->fetch($this->params());

        return new Paginated(
            items: array_map($this->hydrator, $document->resources()),
            meta: $document->meta(),
            links: $document->links(),
            pageFetcher: fn (int $page): Paginated => $this->page($page)->get(),
        );
    }

    /**
     * Run the query and return this page's hydrated DTOs as a Laravel
     * Collection, discarding pagination metadata. Convenient when a caller knows
     * the result is small and wants to `map`/`pluck`/`filter` over it.
     *
     * @return Collection<int, T>
     */
    public function collect(): Collection
    {
        return new Collection($this->get()->items());
    }

    /**
     * Lazily walk every page, yielding one DTO at a time. Pages are fetched on
     * demand as the iterator advances, so the whole collection is never held in
     * memory at once:
     *
     * ```php
     * foreach ($resource->query()->filter('ongoing', true)->lazy() as $journey) { … }
     * ```
     *
     * @return LazyCollection<int, T>
     */
    public function lazy(): LazyCollection
    {
        return LazyCollection::make(function (): iterable {
            $page = $this->get();

            while (true) {
                // Yield items one at a time rather than `yield from` the page
                // array, whose keys (all 0-based per page) would otherwise
                // collide when the iterator is materialised.
                foreach ($page->items() as $item) {
                    yield $item;
                }

                $page = $page->next();

                if ($page === null) {
                    break;
                }
            }
        });
    }

    /**
     * The first matching DTO, or null when the collection is empty.
     *
     * @return T|null
     */
    public function first(): mixed
    {
        $query = $this->perPage(1);
        $resource = $query->fetch($query->params())->resources()[0] ?? null;

        return $resource === null ? null : ($this->hydrator)($resource);
    }

    /**
     * Fetch a single resource by id from `{path}/{id}`, or null on a 404.
     *
     * @return T|null
     */
    public function find(string|int $id): mixed
    {
        $uri = $this->path.'/'.$id;
        $response = $this->send($uri, fn ($request) => $request->get($uri), allowStatuses: [404]);

        if ($response->status() === 404) {
            return null;
        }

        $resource = Document::fromArray($response->json() ?? [])->resource();

        return $resource === null ? null : ($this->hydrator)($resource);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function fetch(array $params): Document
    {
        $response = $this->send($this->path, fn ($request) => $request->get($this->path, $params));

        return Document::fromArray($response->json() ?? []);
    }

    /**
     * Run a tenant-scoped request, normalising connection failures and mapping
     * error responses to typed exceptions so resource calls fail the same way
     * authentication calls do.
     *
     * @param  Closure(\Illuminate\Http\Client\PendingRequest): Response  $callback
     * @param  list<int>  $allowStatuses
     */
    private function send(string $uri, Closure $callback, array $allowStatuses = []): Response
    {
        try {
            $response = $this->api->tenant($callback);
        } catch (ConnectionException $e) {
            throw $this->throwForConnectionFailure($e, $uri);
        }

        $this->throwForErrorResponse($response, $uri, $allowStatuses);

        return $response;
    }
}

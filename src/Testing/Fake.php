<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Session\AuthSession;

/**
 * An in-memory fake of the SupportManager API, installed over Laravel's HTTP
 * client.
 *
 * This is the SDK's answer to "the host app shouldn't hand-roll HTTP mocks":
 * seed records with {@see Factory factories}, and every SDK call routes through
 * the real client pipeline — token renewal, JSON:API decoding, error mapping,
 * pagination — into this store instead of the network. Host code runs unchanged:
 *
 * ```php
 * SupportManager::fake()->seed(CarJourneyFactory::new()->count(3));
 *
 * SupportManager::journeys()->all();       // paginated DTOs from the store
 * SupportManager::journeys()->find(2);     // a single DTO, or null
 * SupportManager::journeys()->start();      // mutates the store
 * ```
 *
 * Installing also primes a fake authenticated session and current team, so
 * tenant-scoped calls work without a real login. Generic CRUD (list with
 * pagination, find, create, update, delete) is inferred from each resource's
 * path; non-RESTful endpoints come from the factory's {@see ProvidesFakeRoutes}.
 *
 * Fidelity note: generic collection filtering is exact-match equality on
 * `filter[key]=value`; it does not reproduce the full Spatie QueryBuilder
 * grammar (scopes, partial matches, custom filters). Endpoints that need richer
 * behaviour declare an explicit fake route instead.
 */
final class Fake
{
    /**
     * The team a freshly installed fake acts as, until {@see actingAsTeam()}.
     */
    public const int DEFAULT_TEAM_ID = 42;

    private readonly RecordStore $store;

    private readonly string $apiPrefix;

    /** @var array<string, Registration> */
    private array $registrations = [];

    /** @var list<Failure> */
    private array $failures = [];

    private bool $installed = false;

    public function __construct(
        private readonly AuthSession $session,
        string $baseUrl,
    ) {
        $this->store = new RecordStore;
        $this->apiPrefix = mb_rtrim($baseUrl, '/').'/api/v1/';
    }

    /**
     * Install the fake over the HTTP client and prime a fake session.
     */
    public function install(): self
    {
        if ($this->installed) {
            return $this;
        }

        Http::fake(fn (Request $request) => $this->respond($request));

        if ($this->session->accessToken() === null) {
            $this->session->start(new AuthTokens('fake-access-token', 'fake-refresh-token', 'Bearer', 3600));
            $this->session->setCurrentTeamId(self::DEFAULT_TEAM_ID);
        }

        $this->installed = true;

        return $this;
    }

    /**
     * Set the current team the faked session acts as. Records seeded after
     * this belong to it, and every `meta.team_id` the fake answers reports it.
     * Records already seeded keep the team they were seeded with.
     */
    public function actingAsTeam(int $teamId): self
    {
        $this->session->setCurrentTeamId($teamId);

        return $this;
    }

    /**
     * The team the faked session is acting as, for route handlers that echo
     * `meta.team_id` or stamp a created record's `team_id`.
     */
    public function teamId(): int
    {
        return $this->session->currentTeamId() ?? self::DEFAULT_TEAM_ID;
    }

    /**
     * Seed records from one or more factories, registering each resource's wire
     * dialect and custom routes.
     */
    public function seed(Factory ...$factories): self
    {
        foreach ($factories as $factory) {
            $factory = $factory->withDefaultTeam($this->teamId());
            $this->register($factory);
            $this->store->put($factory->path(), [
                ...$this->store->all($factory->path()),
                ...$this->prepare($factory),
            ]);
        }

        return $this;
    }

    /**
     * The underlying record store, for assertions or bespoke setup.
     */
    public function store(): RecordStore
    {
        return $this->store;
    }

    // ──────────────────────────────── Unhappy path ────────────────────────────────
    //
    // Programmed failures are matched before any seeded data, so a host can drive
    // the SDK's real error mapping (typed exceptions, connection normalisation)
    // without seeding records. They persist until the fake is rebuilt.

    /**
     * Fail requests matching a path glob (`*` = any characters) with an HTTP
     * error, mapped by the SDK to a {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}.
     * Optionally restrict to a single HTTP method (e.g. only `POST`s).
     *
     * @param  array<string, mixed>  $body
     */
    public function fail(string $pathPattern = '*', int $status = 500, array $body = [], ?string $method = null): self
    {
        $this->failures[] = new Failure(
            method: $method === null ? null : mb_strtoupper($method),
            regex: Failure::compile($pathPattern),
            connection: false,
            status: $status,
            body: $body,
        );

        return $this;
    }

    /**
     * Fail requests with a 422, mapped by the SDK to an
     * {@see \SupportManager\Sdk\Exceptions\ApiValidationException} carrying the
     * given per-field errors. Scalars are normalised to single-message lists, so
     * both `['name' => 'Required']` and `['name' => ['Required']]` work.
     *
     * @param  array<string, string|list<string>>  $errors
     */
    public function failValidation(string $pathPattern = '*', array $errors = [], ?string $method = null, string $message = Json::INVALID): self
    {
        $normalised = array_map(
            static fn (string|array $messages): array => array_values((array) $messages),
            $errors,
        );

        return $this->fail($pathPattern, 422, ['message' => $message, 'errors' => $normalised], $method);
    }

    /**
     * Simulate an unreachable API for requests matching a path glob: the
     * transport throws, which the SDK normalises to a
     * {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}.
     */
    public function unreachable(string $pathPattern = '*', ?string $method = null): self
    {
        $this->failures[] = new Failure(
            method: $method === null ? null : mb_strtoupper($method),
            regex: Failure::compile($pathPattern),
            connection: true,
        );

        return $this;
    }

    /**
     * Clear every programmed failure, restoring normal (happy-path) behaviour.
     */
    public function clearFailures(): self
    {
        $this->failures = [];

        return $this;
    }

    /**
     * Build a paginated collection response from a set of records, honouring the
     * request's `page`/`per_page` and merging any extra `meta` (e.g. an
     * `unread_count`). Public so custom index routes can reuse it.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $extraMeta
     */
    public function paginate(string $path, ?string $type, array $records, Request $request, array $extraMeta = []): mixed
    {
        $query = $this->query($request);
        $perPage = max(1, (int) ($query['per_page'] ?? 15));
        $page = max(1, (int) ($query['page'] ?? 1));
        $total = count($records);
        $lastPage = max(1, (int) ceil($total / $perPage));

        $items = array_slice($records, ($page - 1) * $perPage, $perPage);
        $next = $page < $lastPage ? $this->apiPrefix.$path.'?page='.($page + 1) : null;

        return Http::response(Json::collection($type, array_values($items), [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            ...$extraMeta,
        ], ['next' => $next]));
    }

    /**
     * Narrow records by the request's `filter[key]=value` constraints: exact
     * (string) equality, as generic REST applies them. Public so custom index
     * routes can reuse it.
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    public function filter(array $records, Request $request): array
    {
        $filters = $this->query($request)['filter'] ?? null;

        if (! is_array($filters)) {
            return $records;
        }

        foreach ($filters as $key => $value) {
            $records = array_values(array_filter(
                $records,
                static fn (array $record): bool => isset($record[$key])
                    && (string) $record[$key] === (string) $value,
            ));
        }

        return $records;
    }

    /**
     * The decoded query-string parameters of a request. Keys are whatever
     * `parse_str` yields (the platform only ever sends string keys, but a
     * numeric-looking key would decode to an int).
     *
     * @return array<array-key, mixed>
     */
    public function query(Request $request): array
    {
        $queryString = parse_url($request->url(), PHP_URL_QUERY);

        if (! is_string($queryString) || $queryString === '') {
            return [];
        }

        parse_str($queryString, $params);

        return $params;
    }

    /**
     * Route one faked request: custom routes first, then generic REST.
     */
    private function respond(Request $request): mixed
    {
        $path = $this->relativePath($request->url());
        $method = mb_strtoupper($request->method());

        foreach ($this->failures as $failure) {
            if (! $failure->matches($method, $path)) {
                continue;
            }

            if ($failure->connection) {
                throw new ConnectionException('Unable to reach SupportManager (faked).');
            }

            return Http::response($failure->body, $failure->status);
        }

        $registration = $this->matchRegistration($path);

        // A custom route may live outside its factory's collection path (e.g.
        // `household/consumable-variants/{id}/stock` for consumables), so an
        // unclaimed path is offered to every registration's routes first.
        if ($registration === null && ($result = $this->routeAnywhere($method, $path, $request)) !== null) {
            return $result;
        }

        if ($registration === null) {
            // An unseeded collection reads as empty rather than hitting the
            // network; seed the resource to get records back.
            return Http::response(Json::collection(null, [], [
                'current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0,
            ], ['next' => null]));
        }

        foreach ($registration->routes as $route) {
            $matches = $route->match($method, $path);

            if ($matches !== null) {
                $result = ($route->handler)($this->store, $matches, $request, $this, $registration);

                if ($result !== null) {
                    return $result;
                }
            }
        }

        return $this->rest($registration, $path, $method, $request);
    }

    /**
     * Answer a request from any registration's custom routes, or null when none
     * matches.
     */
    private function routeAnywhere(string $method, string $path, Request $request): mixed
    {
        foreach ($this->registrations as $registration) {
            foreach ($registration->routes as $route) {
                $matches = $route->match($method, $path);

                if ($matches !== null && ($result = ($route->handler)($this->store, $matches, $request, $this, $registration)) !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Generic REST against the store, keyed off the resource's path.
     */
    private function rest(Registration $registration, string $path, string $method, Request $request): mixed
    {
        $type = $registration->type;
        $base = $registration->path;

        $id = null;

        if ($path !== $base) {
            $rest = mb_substr($path, mb_strlen($base) + 1);

            // A deeper, unrecognised sub-path (it would have matched a custom
            // route) is a miss.
            if (str_contains($rest, '/')) {
                return Json::notFound();
            }

            $id = $rest;
        }

        // Flat singletons have no collection or ids — the one seeded record is
        // the whole resource.
        if ($type === null) {
            return $this->restSingleton($base, $method, $request);
        }

        if ($id === null) {
            return match ($method) {
                'GET' => $this->paginate($base, $type, $this->filter($this->store->all($base), $request), $request),
                'POST' => $this->create($base, $type, $request),
                default => Http::response(['message' => 'Method not allowed'], 405),
            };
        }

        return match ($method) {
            'GET' => $this->itemOr404($type, $this->store->find($base, $id)),
            'PATCH', 'PUT' => $this->itemOr404($type, $this->store->update($base, $id, $request->data())),
            'DELETE' => $this->store->delete($base, $id)
                ? Json::message('Deleted')
                : Json::notFound(),
            default => Http::response(['message' => 'Method not allowed'], 405),
        };
    }

    private function restSingleton(string $path, string $method, Request $request): mixed
    {
        $record = $this->store->all($path)[0] ?? null;

        return match ($method) {
            'GET' => $record === null
                ? Json::notFound()
                : Http::response(Json::item(null, $record)),
            'PATCH', 'PUT' => $this->updateSingleton($path, $record ?? [], $request->data()),
            default => Http::response(['message' => 'Method not allowed'], 405),
        };
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $attributes
     */
    private function updateSingleton(string $path, array $record, array $attributes): mixed
    {
        $updated = [...$record, ...$attributes];
        $this->store->put($path, [$updated]);

        return Http::response(Json::item(null, $updated));
    }

    private function create(string $path, string $type, Request $request): mixed
    {
        $record = $request->data();
        $record['id'] = $request->data()['id'] ?? $this->store->nextId($path);
        $this->store->append($path, $record);

        return Http::response(Json::item($type, $record), 201);
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    private function itemOr404(string $type, ?array $record): mixed
    {
        return $record === null
            ? Json::notFound()
            : Http::response(Json::item($type, $record));
    }

    /**
     * Register a factory's path, dialect and routes.
     */
    private function register(Factory $factory): void
    {
        $this->registrations[$factory->path()] = new Registration(
            path: $factory->path(),
            type: $factory->type(),
            routes: $factory instanceof ProvidesFakeRoutes ? $factory->fakeRoutes() : [],
        );
    }

    /**
     * Build a factory's records, assigning ids for JSON:API collections.
     *
     * @return list<array<string, mixed>>
     */
    private function prepare(Factory $factory): array
    {
        $records = $factory->make();

        if ($factory->type() === null) {
            return $records;
        }

        return array_map(function (array $record) use ($factory): array {
            $record['id'] = isset($record['id'])
                ? (string) $record['id']
                : $this->store->nextId($factory->path());

            return $record;
        }, $records);
    }

    /**
     * The registration whose path is the longest prefix of the request path.
     */
    private function matchRegistration(string $path): ?Registration
    {
        $match = null;

        foreach ($this->registrations as $registration) {
            $base = $registration->path;

            if ($path !== $base && ! str_starts_with($path, $base.'/')) {
                continue;
            }

            if ($match === null || mb_strlen($base) > mb_strlen($match->path)) {
                $match = $registration;
            }
        }

        return $match;
    }

    /**
     * The `/api/v1`-relative path of a request URL, without the query string.
     */
    private function relativePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $marker = '/api/v1/';
        $position = mb_strpos($path, $marker);

        return $position === false
            ? mb_ltrim($path, '/')
            : mb_substr($path, $position + mb_strlen($marker));
    }
}

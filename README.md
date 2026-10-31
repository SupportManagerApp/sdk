# SupportManager PHP SDK

`supportmanager/sdk` — the shared PHP client for the SupportManager backend
API (v1). It is consumed by every first-party Laravel app (the NativePHP mobile
app today, a desktop app later) so the networking layer is written once, not
duplicated per platform.

To add an endpoint, or to learn the SDK's conventions, read [CONTRIBUTING.md](CONTRIBUTING.md).

## What lives here

The SDK owns everything that is **the same on every platform**: talking to the
API and holding the session.

- **`Client\SupportManagerClient`** — the HTTP client. Login, social login,
  refresh, logout, device verification, password reset, and a pre-authenticated
  request builder for tenant-scoped module endpoints.
- **`Client\AuthenticatedApi`** — issues tenant-scoped requests with transparent
  token renewal: a mid-session 401 refreshes once and retries. Feature code
  should make every authenticated call through this.
- **`Session\AuthSession`** — the persisted session (tokens, expiry, cached
  user, trusted-device token, current team) over a `SecureStore`.
- **`Session\RefreshSession`** — exchanges the refresh token for a new pair;
  `ifExpiringWithin()` backs a host app's proactive/background refresh.
- **`Session\CurrentTeam`** — resolves and caches the tenant team id.
- **`Avatar\CachedAvatar`** — caches avatar bytes from short-lived signed URLs.
- **`Data\*`** — immutable DTOs. **`Exceptions\*`** — typed API failures.
- **`Resources\Resource`** — base class for domain modules (see below).

### The boundary

The SDK returns DTOs and throws typed exceptions. It never returns anything
UI-shaped. Host apps own rendering, mapping errors to messages, navigation,
loading states, and serving bytes over HTTP. The one thing each host **must
provide** is a `Contracts\SecureStore` binding — the device keychain on mobile,
the OS secret store on desktop, an in-memory fake in tests.

## Growing beyond auth

New domains — car journeys, the rota, the household, dashboard widgets — are
added here as `Resources\Resource` subclasses with their own DTOs, so they are
shared by every client app instead of reimplemented per platform:

```php
/** @extends \SupportManager\Sdk\Resources\Resource<Journey> */
final class JourneysResource extends Resource
{
    /** @use Findable<Journey> */
    use Findable;

    /** @use Listable<Journey> */
    use Listable;

    protected function path(): string
    {
        return 'journeys';
    }

    protected function hydrate(ResourceObject $object): Journey
    {
        return Journey::fromArray($object->flat());
    }

    /** @return list<Journey> */
    public function upcoming(): array
    {
        return $this->collection($this->get('journeys/upcoming'));
    }

    public function summary(): ?JourneySummary
    {
        return $this->data($this->get('journeys/summary'), JourneySummary::class);
    }

    public function downloadExport(string|int $id): Download
    {
        return $this->downloadFile("journeys/{$id}/export");
    }
}
```

Collection reads are opt-in, because many platform collections have no index
or no show route (`apiResource(...)->except(['show'])`, for one). Use
`Resources\Concerns\Listable` for `query()` and `all()` when the platform lists
a JSON:API collection at `GET {path}`, and `Resources\Concerns\Findable` for
`find($id)` when it shows one at `GET {path}/{id}`. Leave them out otherwise:
`tests/Arch/CollectionReadsTest.php` fails for a resource that offers one of
these without the route in the spec.

`query()` builds `filter[key]`, `sort`, `page` and `per_page`, and every one
of its terminals (`get()`, `collect()`, `lazy()`, `first()`, and `next()` on the
page it returns) reads `GET {path}` and nothing else. A query has no `find()`:
fetch one record with the resource's own `find($id)`, which exists only where
the platform has the show route. Its `include()`
is deprecated and does nothing: no platform endpoint allows Spatie includes,
so the SDK no longer sends `include`, and the call only raises an
`E_USER_DEPRECATED`. Remove it from your queries.

For a custom endpoint, issue the
request with `get()`, `post()`, `put()`, `patch()` or `delete()` (or `tenant()`
for something like a multipart upload), then read the response with the helper
that matches the body the platform sends. Every helper turns an error response
into a typed exception first, so don't check the status yourself.

| The platform returns | Read it with | You get |
| --- | --- | --- |
| A JSON:API resource object | `item($response)`, or `item($response, Other::class)` for a different DTO | `?T` |
| A JSON:API collection | `collection($response)` (it takes a DTO class too) | `list<T>` |
| A JSON:API collection whose `meta`/`links` you need | `paginated($response, $pageFetcher)` | `Paginated<T>` |
| A plain `{data: {...}}` object | `data($response, Dto::class)` | `?Dto` |
| A plain `{data: [...]}` list | `dataList($response, Dto::class)` | `list<Dto>` |
| A plain list plus `meta` (and `links`) | `dataPaginated($response, Dto::class, $pageFetcher)` | `Paginated<Dto>` |
| A plain `data` with no DTO, or a DTO that must never be null | `raw($response)`, e.g. `Dto::fromArray($this->raw($response))` | `array` |
| The file itself (a PDF, an image) | `downloadFile($uri, $query)` | `Download` |
| `{data: {download_url, filename, expires_at}}` | `downloadLink($uri, $query)` | `DownloadLink` |
| Nothing worth reading (204, a message) | `throwForErrorResponse($this->delete($uri))` | — |

A plain-body DTO only needs a static `fromArray(array $data)`, which the
hydrator provides. Leave out the `$pageFetcher` for an endpoint that returns its
whole list at once. `throwForErrorResponse()` labels its log entry with the
request path, so there's no need to pass a label.

### Downloads

A `Download` wraps a file body: `body()`, `size()`, `mimeType()`, `filename()`
(from `Content-Disposition`, preferring the RFC 5987 `filename*=UTF-8''…` form),
`saveTo($path)` (a directory saves under `filename()`) and `response()` for
anything else. A `DownloadLink` carries `url`, `filename` and `expiresAt`. Its
URL is a signed link that stops working after a few minutes, so fetch it
straight away rather than storing it.

```php
SupportManager::payslips()->download(9)->saveTo(storage_path('payslips'));
SupportManager::documents()->download(7)->url;
```

## Installation

Auto-discovered via `SupportManager\Sdk\SdkServiceProvider`. Each host app binds
its own secure store:

```php
$this->app->singleton(\SupportManager\Sdk\Contracts\SecureStore::class, MySecureStore::class);
```

Publish the config to override API URL, timeouts, refresh skew, etc.:

```bash
php artisan vendor:publish --tag=supportmanager-config
```

## Modules

### Accidents

`SupportManager::accidents()` is the team's accident book (`/accidents`). The
collection is newest first and sorts by `happened_at`/`report_number`.
`create()` records an accident; `subject.type` takes an `AccidentSubjectType`
(`me`, `team_member` with `subject.user_id`, or `other` with `subject.name` and
an `AccidentSubjectCapacity`). `recordSubjectDeclaration()` is for the injured
person to confirm the report later. Offer it only when
`AccidentReport::$canRecordSubjectDeclaration` (the report's `meta.can`) is
true, because otherwise the platform answers 422. `riddor` is null except for
admins and managers.

```php
SupportManager::accidents()->query()->sort('-happened_at')->get();
SupportManager::accidents()->create([
    'subject' => ['type' => AccidentSubjectType::SomeoneElse, 'name' => 'Alex Visitor', 'capacity' => AccidentSubjectCapacity::Visitor],
    'happened_at' => '2026-10-30T14:15:00+00:00',
    'location' => 'Front steps',
    'how_it_happened' => 'Missed the bottom step.',
    'reporter_declared_name' => 'Sam Carer',
]);
SupportManager::accidents()->recordSubjectDeclaration(7, 'Alex Visitor');
```

### Dashboard

`SupportManager::dashboard()` has three methods. `layout()` (`/dashboard`) returns
the widget placements, quick links and alerts, but not the widget contents:
fetch those from each widget's own module resource, and skip widget keys you
don't recognise. `capabilities()` (`/capabilities`) returns the user's role,
the team's subscription, plan features, optional modules and own-page
navigation. It still answers when the team has no subscription, so check
`subscribed` before calling anything else.

`weather()` (`/dashboard/weather`) is the content of the `dashboard.weather`
widget: a `WeatherForecast` with the `current` weather and the coming `days`,
each with a `WeatherCondition`. It returns null while the platform has no
forecast, so hide the widget then. No module gates it, but former PAs get a
403. The `household.bin-day` widget's content comes from
`SupportManager::binDay()->show()` (`/household/bin-day`), a
`BinCollectionSchedule` of each `BinType`'s next collection. The web app shows
that widget only while `hasBinsDueTomorrow()`. It is part of the Household
module, so it throws a 404 while the team has Household switched off.

```php
SupportManager::dashboard()->layout()->widgets;
SupportManager::dashboard()->capabilities()->usesModule(OptionalModule::Accidents);
SupportManager::dashboard()->capabilities()->hasPlanFeature(PlanFeature::ESigning);
SupportManager::dashboard()->weather()?->current->condition;    // WeatherCondition::PartlyCloudy
SupportManager::binDay()->show()->binsDueTomorrow;              // [BinType::Rubbish, BinType::Food]
```

### E-signing

`SupportManager::signatureRequests()` covers documents sent to the user to sign
(`/signing/requests`). `all()` lists the awaiting inbox, or
`all(SignatureRequestStatus::Signed)` for the documents already signed.
`find()` returns one request with its fields and consent statement, and marks
it viewed. To sign, draw `downloadOriginal()` (the original PDF, as a
`Download`), `saveValues()` as the user fills in fields, then `sign()` once
they agree to the consent statement. `downloadSigned()` returns a
`DownloadLink`, a short-lived link to the completed copy. Use `canSign` and
`canDownloadSigned` (the request's `meta.can`) to decide what to offer.

```php
$request = SupportManager::signatureRequests()->find(12);
SupportManager::signatureRequests()->saveValues(12, [$request->myFields()[0]->id => 'data:image/png;base64,…']);
SupportManager::signatureRequests()->sign(12, agreesToSignElectronically: true);
SupportManager::signatureRequests()->downloadSigned(12)->url;
```

## Faking the API in host apps

Host apps should never hand-roll HTTP mocks of the JSON:API wire format — that
couples every consumer to the SDK's internals. Instead, swap the SDK onto an
in-memory fake and seed it with factories. Every call still runs through the real
client pipeline (token renewal, JSON:API decoding, error mapping, pagination),
so you're exercising the SDK, not a stub:

```php
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\CarJourneyFactory;

SupportManager::fake()->seed(
    CarJourneyFactory::new()->count(3),
    CarJourneyFactory::new()->ongoing()->state(['driver_name' => 'Ada']),
);

// Your code under test runs unchanged:
SupportManager::journeys()->all();        // paginated DTOs from the store
SupportManager::journeys()->find(2);      // a single DTO, or null
SupportManager::journeys()->start();      // mutates the store
```

`fake()` also primes a fake authenticated session and current team, so
tenant-scoped calls work without a login. Generic CRUD (list with pagination,
find, create, update, delete) is inferred from each resource's path; non-RESTful
actions and flat (non-JSON:API) endpoints are described by the factory itself
(`SupportManager\Sdk\Testing\Factory` + `ProvidesFakeRoutes`). Every module
ships a factory in `SupportManager\Sdk\Testing\Factories`. See
`tests/Feature/Testing/` for the full surface.

### Teams

The fake acts as team `42` (`Fake::DEFAULT_TEAM_ID`) until you call
`actingAsTeam()`. Records seeded afterwards belong to that team, records
created through the fake are stamped with it, and every `meta.team_id` the fake
answers reports it. Records already seeded keep their team, so switch first:

```php
SupportManager::fake()->actingAsTeam(7)->seed(LeaveRequestFactory::new());

SupportManager::leaveRequests()->find(1)->teamId;    // 7
LeaveRequestFactory::new()->forTeam(3);              // pin one factory to another team
```

### Errors

Programmed failures run through the SDK's real error mapping:

```php
SupportManager::fake()->fail('car-journeys*', 503);
SupportManager::fake()->failValidation('car-journeys', ['driver_name' => 'Required'], method: 'POST');
SupportManager::fake()->unreachable();
```

### Writing a factory

A factory names its endpoint (`path()`), its JSON:API type (`type()`, the
platform's own, e.g. `rota_entries`, or null for a flat `{data}` endpoint) and
one record's defaults (`definition()`). Read the team from `$this->teamId()`
rather than writing a literal. For the endpoints generic REST can't infer,
implement `ProvidesFakeRoutes`. These `FakeRoute` builders cover the common
shapes and read the factory's path and type for you:

| Builder | Answers |
|---|---|
| `FakeRoute::action($method, 'things/{id}/approve', fn (array $record, Request $request): array => [...])` | Merges the changes into the stored record and answers it as an item, or a 404. Return a response instead of an array to refuse, e.g. `Json::forbidden()`. |
| `FakeRoute::download('things/{id}/download', $bytes, $mime = 'application/pdf', $filename = null, changes: [])` | Raw bytes, read back as a `Download`, or a 404. |
| `FakeRoute::downloadLink('things/{id}/file', 'file_attribute')` | A signed `{download_url, filename, expires_at}` link, read back as a `DownloadLink`, or a 404 when there's no file. |
| `FakeRoute::collection('things/today', fn (RecordStore $store): array => [...], teamMeta: false)` | An unpaginated collection in the factory's dialect. |
| `FakeRoute::flat('things/summary', fn (RecordStore $store): array => [...], teamMeta: false)` | A plain `{data, meta?}` envelope, for singleton reads. |

Anything else is a plain `FakeRoute::get()`/`post()`/`patch()`/`put()`/`delete()`.
Its handler gets `(RecordStore $store, array $matches, Request $request, Fake $fake, Registration $registration)`,
and can declare only the leading parameters it uses. Build its answer with `Json`:
`Json::item()`, `Json::collection()`, `Json::flat($data, $meta)`, `Json::notFound()`,
`Json::forbidden()`, `Json::validation($errors, $message)`, `Json::message($message, $status)`.
`$fake->teamId()`, `$fake->query($request)`, `$fake->filter($records, $request)`
and `$fake->paginate(...)` cover the rest. Keys starting with `_` are fake
bookkeeping (e.g. a `_today` flag a state sets for a route to filter on), and
`Json` never sends them.

For example, a host app's factory for a resource it registered itself:

```php
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

final class IncidentFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'incidents';
    }

    public function type(): string
    {
        return 'incidents';
    }

    public function definition(int $index): array
    {
        return [
            'team_id' => $this->teamId(),
            'title' => 'Incident '.($index + 1),
            'status' => 'open',
            'report' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed', 'report' => 'report.pdf']);
    }

    public function fakeRoutes(): array
    {
        return [
            // Close an open incident; closing one twice is refused.
            FakeRoute::action('PATCH', 'incidents/{id}/close', static fn (array $incident, Request $request): array|PromiseInterface => $incident['status'] === 'closed'
                ? Json::validation(['status' => 'This incident is already closed.'])
                : ['status' => 'closed', 'closing_note' => $request->data()['note'] ?? null]),

            FakeRoute::downloadLink('incidents/{id}/report', 'report'),

            FakeRoute::collection('incidents/open', static fn (RecordStore $store): array => array_values(array_filter(
                $store->all('incidents'),
                static fn (array $incident): bool => $incident['status'] === 'open',
            )), teamMeta: true),

            FakeRoute::flat('incidents/summary', static fn (RecordStore $store): array => [
                'open' => count(array_filter($store->all('incidents'), static fn (array $incident): bool => $incident['status'] === 'open')),
            ]),

            // Anything bespoke is an ordinary handler.
            FakeRoute::post('incidents/{id}/escalate', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                return $store->find('incidents', $matches[1]) === null
                    ? Json::notFound()
                    : Json::message('Escalated.', meta: ['team_id' => $fake->teamId()]);
            }),
        ];
    }
}
```

Fake responses are checked against the platform's OpenAPI spec like any other
(see [Contract tests](#contract-tests)), so a factory for a first-party
resource has to stay spec-valid.

## Tests

```bash
composer install
vendor/bin/pest
```

### Writing tests

A resource test lives in `tests/Feature/<Module>/`, where every test already
runs inside a booted tenant session (team 42). The helpers in `tests/Pest.php`
keep it short:

```php
it('reads the task badge count', function () {
    fakeApi(['tasks/count' => [
        'data' => ['count' => 5, 'my_tasks' => 3, 'manager_inbox' => 2],
        'meta' => ['team_id' => 42],
    ]]);

    expect(sdk()->tasks()->count()->myTasks)->toBe(3);

    assertSentTo('GET', 'tasks/count');
});
```

- `fakeApi([...])` fakes routes by path relative to `/api/v1` (a trailing `*`
  matches a query string). An array value is a 200 JSON body; anything else
  is passed to `Http::fake()` as is, e.g. `Http::response($body, 422)` or
  `Http::sequence()`. It starts a session when none is active.
- `sdk()` is the SDK entry point, so `sdk()->tasks()` goes through the same
  accessor and container wiring as `SupportManager::tasks()`.
- `assertSentTo('POST', 'tasks/x/complete', fn ($request) => …)` checks a
  request's method and path, plus anything else in the callback.
- `bootTenantSession($teamId)` and `apiUrl($path)` are there for tests that
  need another team or build their own sessions.

Don't test the shared behaviour again in a resource's own file: the bearer
token and `X-Team-ID`, 422s becoming `ApiValidationException`, 403/404/500
becoming `SupportManagerApiException`, and the 401 refresh.
`tests/Feature/SharedResourceBehaviourTest.php` covers those for one call per
resource. A new resource needs one row in its `sharedResourceCalls()`, and the
suite fails until it has one. Per-resource tests keep what is specific to the
resource, such as a 404 that reads as null or a 422 whose message the platform
documents.

## Contract tests

Every request the SDK sends in a test, and every response it gets back, is
checked against the platform's OpenAPI spec. That covers both `Http::fake()`
in resource tests and the `SupportManager::fake()` factories. The spec is
vendored at `tests/Contract/openapi.yaml`, and its header names the platform
commit it came from. The check runs in `tests/TestCase.php`
(`assertPostConditions`) over the exchanges Laravel recorded, so no test has to
opt in. A mismatch fails the test with the operation, the status, the JSON
pointer and the schema error:

```
GET /rota/shifts → 200 response: at /data/0/attributes: Keyword validation failed: Data has additional properties (driver_name) which are not allowed
```

The validator is `league/openapi-psr7-validator`, and it is strict. Every
object schema that lists `properties` is closed (`additionalProperties: false`),
so a field the spec doesn't list fails, and so does an undocumented query
parameter. A request to a path or method the spec doesn't have also fails.

Error responses are checked against the schema the operation documents for
that status. Where the spec doesn't document a 4xx or 5xx, the body must be a
plain Laravel error, `{message, errors?}`. A 5xx with a non-JSON body (a proxy
or gateway page) is not checked. When a request gets a 400 or 422, the test
sent invalid input on purpose, so only the response is checked. URLs outside
`/api/v1/`, such as avatar CDNs or signed S3 links, are not checked.

### Re-syncing the spec

```bash
composer sync-openapi -- /path/to/Platform     # or set SUPPORTMANAGER_PLATFORM_PATH
vendor/bin/pest
```

### When a check fails

Read the platform code (its routes, controllers and API Resources). The
platform code is the source of truth, not the spec. Then pick one of these:

1. **The fixture is wrong.** Fix the test's fake body or the factory in
   `src/Testing/Factories`. This is the usual case.
2. **The spec is wrong.** Add an entry to `tests/Contract/spec-exceptions.php`.
3. **The SDK is wrong.** Correcting the fixture makes the SDK misbehave. Add an
   entry to `tests/Contract/known-sdk-bugs.php` so the suite stays green, and
   fix the SDK.

An entry in either list patches one operation's schema at one place:

```php
[
    'operation' => 'GET /rota/shift-applications',
    'in' => 200,                                  // a status, or 'request', 'query', 'path'
    'pointer' => '/data/*/attributes/is_provisional', // `*` = every array item; '/<name>' for a parameter
    'schema' => ['type' => 'boolean'],            // and/or 'nullable' => true, 'optional' => true
    'reason' => 'ShiftApplicationResource sends it; the spec omits it.',
],
```

A known-SDK-bugs entry with `'missing' => true` (plus `operation` and
`reason`) names an endpoint the SDK calls that the platform doesn't have.

The check follows Laravel's request handling: a multipart POST carrying
`_method` counts as the spoofed method, a multipart-only operation also takes
JSON, and `''` stands in for null in a form field.

Keep entries narrow and the lists short. The suite reports any entry that no
exchange needs any more, for example once the platform's spec is fixed and
re-synced. Remove those entries.

### API coverage

`tests/Contract/CoverageTest.php` records the operations the suite actually
calls, so paths built with string interpolation are counted too. It fails on
any spec operation that nothing calls, unless that operation is listed with a
reason in `tests/Contract/unwrapped-operations.php`. After a re-sync, it lists
exactly the platform's new endpoints. Run `composer coverage:api` for the full
report. Coverage needs the whole suite in one process, so it skips on filtered
or `--parallel` runs.

### Method coverage

Contract validation only judges the requests tests send, so the same file also
checks the other direction: every public method of every resource, its own or
from `Listable`/`Findable`, must send a request in some test, and that request
must pass contract validation against an operation the spec has. Together the
two checks mean every public resource method reaches a documented route at
least once in CI.

A test-only HTTP client middleware (`tests/Contract/Support/MethodCoverage.php`,
registered in `tests/TestCase.php`) notes the outermost public resource method
on the call stack for each request, keyed by the concrete resource class. A
trait method counts for the resource that uses it, and a deprecated alias for
the alias. At the end of a passing test, a noted method is credited only if its
request was recorded and matched the spec, so a request a stub threw on, a
non-API URL, or an endpoint allowed in by a `'missing' => true` entry credits
nothing.

The check fails on any public resource method never credited, unless
`tests/Contract/request-free-methods.php` lists it, with a reason, as sending
no request itself. That list holds builders only (today just `query()`). Test
anything else in its module's resource test. If the platform has no route for
it, fix or remove the method. Like API coverage, it skips on partial runs.

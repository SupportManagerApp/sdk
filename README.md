# SupportManager PHP SDK

`supportmanager/sdk` — the shared PHP client for the SupportManager backend
API (v1). It is consumed by every first-party Laravel app (the NativePHP mobile
app today, a desktop app later) so the networking layer is written once, not
duplicated per platform.

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

    public function export(string|int $id): Download
    {
        return $this->downloadFile("journeys/{$id}/export");
    }
}
```

`query()`, `find()` and `all()` come for free. For a custom endpoint, issue the
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

`SupportManager::dashboard()` has two methods. `layout()` (`/dashboard`) returns
the widget placements, quick links and alerts, but not the widget contents:
fetch those from each widget's own module resource, and skip widget keys you
don't recognise. `capabilities()` (`/capabilities`) returns the user's role,
the team's subscription, plan features, optional modules and own-page
navigation. It still answers when the team has no subscription, so check
`subscribed` before calling anything else.

```php
SupportManager::dashboard()->layout()->widgets;
SupportManager::dashboard()->capabilities()->usesModule(OptionalModule::Accidents);
SupportManager::dashboard()->capabilities()->hasPlanFeature(PlanFeature::ESigning);
```

### E-signing

`SupportManager::signatureRequests()` covers documents sent to the user to sign
(`/signing/requests`). `all()` lists the awaiting inbox, or
`all(SignatureRequestStatus::Signed)` for the documents already signed.
`find()` returns one request with its fields and consent statement, and marks
it viewed. To sign, draw `file()` (the original PDF, as a `Download`),
`saveValues()` as the user fills in fields, then `sign()` once they agree to the
consent statement. `downloadSigned()` returns a `DownloadLink`, a short-lived
link to the completed copy. Use
`canSign` and `canDownloadSigned` (the request's `meta.can`) to decide what to
offer.

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
(`SupportManager\Sdk\Testing\Factory` + `ProvidesFakeRoutes`). See
`tests/Feature/Testing/FakeTest.php` for the full surface.

> Reference factories ship for CarJourneys, Notifications (flat filter +
> `unread_count`) and the Keysafe flat singleton. The remaining resources follow
> the same recipe.

## Tests

```bash
composer install
vendor/bin/pest
```

# SupportManager PHP SDK

`support-manager/sdk` — the shared PHP client for the SupportManager backend
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
final class JourneysResource extends Resource
{
    /** @return list<Journey> */
    public function upcoming(): array
    {
        $data = $this->tenant(fn ($request) => $request->get('journeys/upcoming'))->json('data', []);

        return array_map(Journey::fromArray(...), $data);
    }
}
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
SupportManager::journeys()->start('Ada'); // mutates the store
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

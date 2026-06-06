# SupportManager SDK

`support-manager/sdk` is the shared PHP client for the SupportManager backend API (v1). When this package is installed, talk to the API **only** through it — never hand-roll `Http::` calls, token storage, or refresh logic.

## Setup (required)

- The host app **must** bind a secure store, or the session layer has nowhere to persist tokens:
  ```php
  $this->app->singleton(\SupportManager\Sdk\Contracts\SecureStore::class, MyKeychainStore::class);
  ```
  On mobile this wraps the device keychain; on desktop the OS secret store; in tests an in-memory fake.
- Configure `SUPPORTMANAGER_API_URL` (the app root, **without** `/api/v1` — the client appends it). Publish config with `php artisan vendor:publish --tag=supportmanager-config` to override timeouts, device name, refresh skew, etc.
- Everything is reached through the auto-aliased `SupportManager` facade: `auth()`, `session()`, `team()`, `resource()`.

## Authentication

- `SupportManager::auth()` returns the HTTP client: `login()`, `loginWithProvider()`, `refresh()`, `logout()`, device verification, and password reset. These return DTOs; they do **not** persist anything.
- After a successful `login()`/`verifyDevice()`, persist the session yourself:
  ```php
  $tokens = SupportManager::auth()->login($email, $password);
  SupportManager::session()->start($tokens); // rememberDevice: true by default
  ```
- Token renewal is automatic on the tenant path (a mid-session 401 refreshes once and retries), so feature code never refreshes by hand.

## Tenant-scoped resources

- Every domain lives in a `Resources\Resource` subclass that returns DTOs. Declare `path()` and `hydrate()`; the base class gives you fluent querying, pagination, and single-item fetches:
  ```php
  final class JourneysResource extends \SupportManager\Sdk\Resources\Resource
  {
      protected function path(): string { return 'car-journeys'; }

      protected function hydrate(\SupportManager\Sdk\Http\ResourceObject $object): Journey
      {
          return Journey::fromArray($object->flat());
      }
  }
  ```
- Query with the fluent builder (it maps 1:1 to the backend's filter/sort/include/pagination grammar):
  ```php
  $page = SupportManager::resource(JourneysResource::class)
      ->query()
      ->filter('ongoing', true)
      ->sort('-trip_start')
      ->include('user')
      ->perPage(15)
      ->get();                 // Paginated<Journey>
  ```
- Terminal methods: `get()` → `Paginated` (iterable; `next()`/`hasMorePages()`), `lazy()` → `LazyCollection` that auto-pages, `collect()` → `Collection`, `first()`, and `find($id)` (returns `null` on 404). For non-RESTful actions use the `get()/post()/patch()/put()/delete()` helpers plus `item()`/`collection()` to hydrate.
- The current team (`X-Team-ID`) is resolved automatically; do not pass it manually. There is no server-side default team.

## DTOs

- DTOs are immutable `readonly` classes hydrated via `fromArray($object->flat())`. `flat()` folds the JSON:API `id` and `type` in beside the attributes.
- **JSON:API ids are always strings** — type DTO id fields as `string`, not `int`.
- The SDK only ever returns DTOs or throws; it never returns UI-shaped data. Host apps own rendering and error-to-message mapping.

## Errors — one model for auth and resources

Wrap calls and catch the typed exceptions (all extend `SupportManagerApiException`):

- `ApiValidationException` (422) — `->errors` / `->first('field')` to surface on form fields.
- `DeviceVerificationRequiredException` (403) — `->challenge` drives the SMS/email verification flow.
- `InvalidCredentialsException` (401) on the auth path.
- `SessionExpiredException` — the session is already cleared; full-redirect to the login gate.
- `SupportManagerApiException` — network failure or unexpected status; `->status` / `->body` for diagnostics.

A 422 or 500 on a resource query throws exactly like it does on `login()` — never assume an empty result means "no data."

## Autocomplete-friendly access

- Prefer the **typed** accessor `SupportManager::resource(JourneysResource::class)` — its generics flow through `query()` → `Paginated<Journey>` → DTOs, so editors and PHPStan resolve concrete types end to end.
- `SupportManager::register('journeys', JourneysResource::class)` + `SupportManager::journeys()` is terser but returns the base `Resource` (no autocomplete). If you want the named form typed, add a real accessor method plus a `@method` tag on a facade.
- Keep PHPStan green: the SDK is analysed at level 8 with full generics. New resources and DTOs should preserve their type parameters.

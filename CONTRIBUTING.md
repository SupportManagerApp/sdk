# Contributing to the SupportManager PHP SDK

This guide walks through adding a platform endpoint to the SDK end to end, then
lists the conventions the SDK follows and the tests that enforce them. The
E-signing module (`Data\Signing`, `Resources\Signing\SignatureRequestsResource`,
`Testing\Factories\SignatureRequestFactory`) is the running example, because it
uses most of the moving parts.

Setup:

```bash
composer install
vendor/bin/pest --compact
```

## Adding an endpoint end to end

### 1. Sync the spec and read the coverage report

The contract tests validate every faked request and response against a vendored
copy of the platform's OpenAPI spec, `tests/Contract/openapi.yaml`. Start by
bringing it up to date with a local platform checkout:

```bash
composer sync-openapi -- /path/to/Platform    # or set SUPPORTMANAGER_PLATFORM_PATH
composer coverage:api
```

`composer coverage:api` runs the whole suite and lists the spec operations that
nothing in the SDK calls. After a sync, that list is exactly the platform's new
endpoints, which is your to-do list. Wrap each one, or add it to
`tests/Contract/unwrapped-operations.php` with the reason the SDK leaves it out.

Read the platform's route, controller and API Resource for the endpoint before
you write anything. The platform code is the source of truth, not the spec.

### 2. Write the DTO

A DTO lives in `src/Data/<Module>/`. It is a `final readonly` class whose
promoted constructor parameters are its fields, and its `fromArray()` is one
line that hands the payload to the `Data\Hydration\Hydrator`:

```php
namespace SupportManager\Sdk\Data\Signing;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Signing\SigningChannel;
use SupportManager\Sdk\Enums\Signing\SigningStatus;

final readonly class SignatureRequest
{
    /**
     * @param  list<SigningField>  $fields
     */
    public function __construct(
        public string $id,
        #[From('document.name')]
        public string $documentName,
        #[From('document.status')]
        public ?SigningStatus $documentStatus,
        // …
        public ?string $signedAt,
        public ?SigningChannel $signedVia,
        #[ListOf(SigningField::class)]
        public array $fields,
        public ?string $consentStatement,
        #[Can('sign')]
        public bool $canSign,
        #[Can('download_signed')]
        public bool $canDownloadSigned,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
```

The hydrator reads each parameter from the snake_case form of its name
(`signedAt` ← `signed_at`) and casts it by its declared type. The attributes in
`src/Data/Hydration/Attributes` cover everything else:

- **A nested object, flattened.** `#[From('document.name')]` reads a dot-path
  into a nested object. Several paths form a fallback chain:
  `#[From('frequency_label', 'frequency')]`.
- **A nested object, as its own DTO.** Declare the parameter as that DTO's class
  and the hydrator builds it through the nested DTO's own `fromArray()`, e.g.
  `public ?AccidentRiddorOutcome $riddor` on `Data\Accidents\AccidentReport`.
  When it's nullable, a missing value gives null.
- **An enum.** Declare a backed enum type. A nullable enum uses `tryFrom()`, so an
  unknown value gives null. A non-nullable one uses `from()`, so an unknown
  value throws a `ValueError`. `#[Defaults(...)]` supplies a value when the key
  is missing.
- **A list.** PHP can't type an array's elements, so `#[ListOf(SigningField::class)]`
  says what they are: a DTO class, a backed enum, or `'string'`, `'int'`,
  `'float'`, `'bool'` or `'array'`. Elements that can't be that type are
  dropped. Add the `@param list<…>` tag for PHPStan too.
- **A `meta.can` flag.** `#[Can('sign')]` reads `meta.can.sign`.
  `Http\ResourceObject::flat()` keeps the resource's `meta` under a `meta` key,
  so `fromArray()` sees it. Declare it `bool` for "false unless the platform
  says so", or `?bool` to tell "not sent" apart. `#[Meta('key')]` reads any
  other `meta` member.
- **Anything else.** `#[CastWith(EmptyStringAsNull::class)]` hands the value to
  a `Data\Hydration\Caster`. For a one-off rule, pass ready-made constructor
  arguments as `Hydrator::hydrate()`'s third argument, `$overrides`.

Put new constructor parameters at the end, with defaults. See
[Constructors](#constructors).

### 3. Add the resource method

A resource lives in `src/Resources/<Module>/<Name>Resource.php`. It is `final`,
extends `Resources\Resource`, and names its DTO in an `@extends` tag. It
declares its collection path and how to hydrate one JSON:API resource object.

Collection reads are opt-in. Use the `Resources\Concerns\Listable` trait for
`query()` and `all()` only when the platform lists a JSON:API collection at
`GET {path}` with the Spatie grammar, and `Resources\Concerns\Findable` for
`find($id)` only when it shows one record at `GET {path}/{id}`. Check the
route file: `apiResource(...)->except(['show'])` has no show route, and a
singleton such as `household/keysafe` has neither. When the index has another
shape (a plain `{data}` list, its own query parameters), write an `all()` that
reads it instead of using `Listable`. The signing inbox takes a `status`
parameter rather than Spatie filters, so it has its own `all()` and only
`Findable`:

```php
/**
 * @extends Resource<SignatureRequest>
 */
final class SignatureRequestsResource extends Resource
{
    /** @use Findable<SignatureRequest> */
    use Findable;

    public function sign(string|int $id, bool $agreesToSignElectronically): ?SignatureRequest
    {
        return $this->item($this->post("{$this->path()}/{$id}/sign", [
            'agrees_to_sign_electronically' => $agreesToSignElectronically,
        ]));
    }

    // The body is the PDF itself.
    public function downloadOriginal(string|int $id): Download
    {
        return $this->downloadFile("{$this->path()}/{$id}/file");
    }

    // The body is {data: {download_url, filename, expires_at}}.
    public function downloadSigned(string|int $id): DownloadLink
    {
        return $this->downloadLink("{$this->path()}/{$id}/signed");
    }

    protected function path(): string
    {
        return 'signing/requests';
    }

    protected function hydrate(ResourceObject $object): SignatureRequest
    {
        return SignatureRequest::fromArray($object->flat());
    }
}
```

Issue the request with `get()`, `post()`, `put()`, `patch()` or `delete()`
(paths are relative to `/api/v1`), or `tenant()` for something like a multipart
upload. Then read the response with the helper that matches the body the
platform sends: `item()`, `collection()` or `paginated()` for JSON:API,
`data()`, `dataList()`, `dataPaginated()` or `raw()` for a plain `{data}` body,
and `downloadFile()` or `downloadLink()` for files. For a body with nothing
worth reading, use `$this->throwForErrorResponse($this->delete(…))`. The table
in the README's [Growing beyond auth](README.md#growing-beyond-auth) section
says which helper fits which body. Every helper turns an error response into a
typed exception, so never check the status yourself.

Name the method by the [naming conventions](#naming), and give it a docblock
saying what the platform does and when it refuses.

### 4. Wire the accessor and the facade

Add a typed accessor to `src/SupportManager.php`, in the "Domain resources"
block:

```php
/** Signing — documents sent to the user to fill in and sign. */
public function signatureRequests(): SignatureRequestsResource
{
    return $this->resource(SignatureRequestsResource::class);
}
```

Then add the matching tag to the docblock of `src/Facades/SupportManager.php`,
with the same name and return type:

```php
 * @method static \SupportManager\Sdk\Resources\Signing\SignatureRequestsResource signatureRequests()
```

`tests/Arch/WiringTest.php` fails if a resource has no accessor, or more than
one, or if an accessor and its facade tag disagree.

### 5. Add a fake factory

Host apps test against `SupportManager::fake()`, which needs a factory for the
resource in `src/Testing/Factories/<Dto>Factory.php` (`final`, extending
`Testing\Factory`). How to write one, including `ProvidesFakeRoutes` and the
`FakeRoute` builders for actions and downloads, is in the README's
[Faking the API in host apps](README.md#faking-the-api-in-host-apps) section,
under [Writing a factory](README.md#writing-a-factory).
`SignatureRequestFactory` is the first-party example for this module.

### 6. Write the tests and run the checks

- **Resource tests** go in `tests/Feature/<Module>/<Name>ResourceTest.php`.
  Fake the HTTP response with `Http::fake()`, call `bootTenantSession()` (from
  `tests/Pest.php`), and assert both what the SDK sent and what it returned.
  Use the platform's real response body, including the error cases.
- **Fake tests** go in `tests/Feature/Testing/<Module>FakeTest.php`. Seed the
  factory and drive the resource through `SupportManager::fake()`.
- **Every public method needs a test that sends its request.** At the end of
  a full run, `tests/Contract/CoverageTest.php` fails on any public resource
  method that never sent a contract-validated request. A method that sends
  nothing (a builder such as `query()`) goes in
  `tests/Contract/request-free-methods.php` with its reason instead.
- **Types:** if the method returns something generic, pin it with
  `assertType()` in `tests/types/`, which PHPStan analyses.

Then run all three checks. Each must pass before you open a PR:

```bash
vendor/bin/pest --compact
vendor/bin/phpstan analyse --memory-limit=1G --no-progress    # or composer analyse
vendor/bin/pint --test                                         # vendor/bin/pint to fix
```

When a contract check fails, it names the operation, the status, the JSON
pointer and the schema error. Read the platform code, then decide which of
these it is:

1. **The fixture is wrong.** Fix the test's fake body or the factory. This is
   the usual case.
2. **The spec is wrong,** and the platform really does send or accept this. Add
   a narrow entry to `tests/Contract/spec-exceptions.php`.
3. **The SDK is wrong.** Correcting the fixture makes the SDK misbehave. Add an
   entry to `tests/Contract/known-sdk-bugs.php` so the suite stays green, and
   fix the SDK, here or in a follow-up.

The entry format is in the README's
[When a check fails](README.md#when-a-check-fails) section. The suite reports
entries that nothing needs any more. Delete those.

### 7. Changelog and versioning

Add your change to `CHANGELOG.md` under the next version's heading, in the
existing style: a **Breaking** section for anything that can break a caller
(say what to change), then **Added**, **Deprecated** and **Fixed**.

While the SDK is pre-1.0, releases are `0.0.x`: every release bumps the patch
version (`0.0.3` → `0.0.4`), whether or not it breaks anything. A release may
contain breaking changes, so list every one under **Breaking**, with what the
caller has to change. Breaking includes removing or renaming a public method or
class, changing a signature or a return type, inserting a DTO constructor
parameter mid-list or removing its default, and changing what a test fake
answers.

To release, merge the release PR into `main`, then tag the merge commit and
push the tag:

```bash
git tag -a v0.0.3 -m "v0.0.3" <merge-commit-sha>
git push origin v0.0.3
```

Packagist reads the version from the git tag. There is no version string in
`composer.json` or anywhere in the code, so don't add one.

## Conventions

### Layout

- A resource is `src/Resources/<Module>/<Name>Resource.php`, a DTO is
  `src/Data/<Module>/<Name>.php`, an enum is `src/Enums/<Module>/<Name>.php`,
  and a fake factory is `src/Testing/Factories/<Dto>Factory.php`.
- Every PHP file declares `declare(strict_types=1);`.
- Enums are backed (`enum X: string`), so they round-trip through JSON.
- Runtime code in `src/` never uses `tests/`, and reaches `src/Testing` only
  through `SupportManager::fake()`.

### Naming

Resource methods are named by what they do on the platform:

| The method | Name it |
| --- | --- |
| Lists, finds or queries the collection | `all()`, `find($id)`, `query()` (from `Listable`/`Findable`, or the resource's own when the route has another shape) |
| Reads a singleton | `show()` |
| Creates or updates a record | `create($data)`, `update($id, $data)` |
| Issues `DELETE {path}/{id}` for one record | `destroy($id)` |
| Moves a record through a state the platform models as an action, usually `PATCH`/`POST {path}/{id}/<action>` | The platform's domain verb: `approve()`, `reject()`, `cancel()`, `withdraw()`, `complete()`, `sign()`… |
| Returns a file (`Download` or `DownloadLink`) | `download*()`: `downloadSigned()`, `downloadOriginal()`… |
| Acts on a secondary record of the resource | The verb plus the noun: `createEntitlement()`, `deleteEmergencyContact()`, `removeDay()` |

`destroy()` is for the HTTP DELETE of the record, even when the product calls
it withdrawing or retracting (`shiftApplications()->destroy()`). Keep
`cancel()`, `withdraw()` and the like for transitions that leave the record in
place, such as `leaveRequests()->cancel()` (`PATCH leave/requests/{id}/cancel`).

### Renaming a public method

A rename breaks callers, so keep the old name as a deprecated proxy for at least
one release:

```php
public function destroy(string|int $id): void
{
    $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
}

/**
 * @deprecated Use {@see self::destroy()}, the name every resource gives a DELETE of one record.
 */
public function cancel(string|int $id): void
{
    $this->deprecated(__FUNCTION__, 'destroy');

    $this->destroy($id);
}
```

`Resource::deprecated()` raises an `E_USER_DEPRECATED`, which Laravel sends to
its deprecations log channel (`LOG_DEPRECATIONS_CHANNEL`), and the
`@deprecated` tag makes IDEs strike the call through. Add a case to `tests/Feature/DeprecatedAliasesTest.php`,
and list the rename under **Deprecated** in the changelog.

### Constructors

New DTO constructor parameters go at the end, with a default, so that code
constructing a DTO positionally (host apps' own tests, mostly) keeps working
between releases. Don't insert a parameter mid-list, reorder or rename
parameters, or remove a default, except as a breaking change.

`tests/Arch/DtoConstructorsTest.php` compares every DTO constructor with
`tests/Arch/snapshots/dto-constructors.json`. After a change that keeps the
rule, or a deliberate breaking change, refresh the snapshot and commit it with
the change:

```bash
UPDATE_DTO_CONSTRUCTORS=1 vendor/bin/pest tests/Arch/DtoConstructorsTest.php
```

The snapshot diff then shows the reviewer exactly what moved.

### Docblocks

Use the imported short name in generics, e.g. `@extends Resource<SignatureRequest>`.
`pint.json` limits Pint's `phpdoc_types` fixer to its `simple` and `alias`
groups, because its `meta` group used to rewrite `Resource<…>` to the
`resource<…>` pseudo-type.

## Conventions are enforced by

- `tests/Arch/ResourcesTest.php`: resources are final, extend `Resource`, are
  named `*Resource`, live in `src/Resources/<Module>/` and carry an
  `@extends Resource<…>` tag. A method that issues `DELETE {path}/{id}` is
  `destroy()`, and a method that returns a `Download` or `DownloadLink` is
  `download*()`.
- `tests/Arch/DataTest.php`: DTOs in `src/Data` are `final readonly`, with a
  `public static function fromArray(array $data): self` that uses the
  hydrator, and every enum is backed. Exceptions are allowlisted there, each
  with its reason.
- `tests/Arch/DtoConstructorsTest.php`: DTO constructors only grow at the end,
  with defaults.
- `tests/Arch/LayersTest.php`: `src/` doesn't depend on `tests/`, only
  `SupportManager::fake()` reaches into `src/Testing`, fake factories are final
  and extend `Testing\Factory`, and every PHP file declares strict types.
- `tests/Arch/CollectionReadsTest.php`: a resource with `find()` has
  `GET {path}/{id}` in the spec, and one with `all()` or `query()` has
  `GET {path}`. Where the method comes from `Findable` or `Listable`, the
  spec's 200 must also be a JSON:API item or collection, which is what those
  traits read. `ResourceQuery`'s public methods are pinned to its builders
  and its `GET {path}` terminals, so `query()` can't reach another route.
- `tests/Arch/WiringTest.php`: every resource has exactly one accessor on
  `SupportManager` and a matching facade `@method`, and neither has orphans.
- `tests/Contract/`: every faked request and response matches the OpenAPI
  spec, and every spec operation is called or listed as unwrapped.
- `tests/Contract/CoverageTest.php` (method coverage): every public method on
  a resource, its own or from a trait, sends a request that passes contract
  validation in at least one test, or is listed in
  `tests/Contract/request-free-methods.php` as sending nothing. With the
  contract check, every public resource method reaches a documented route.
  It runs on full, single-process runs only.
- PHPStan at level 8, including the `assertType()` probes in `tests/types/`.
- Pint, configured by `pint.json`.

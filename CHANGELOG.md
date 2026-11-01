# Changelog

All notable changes to `supportmanager/sdk` are documented here. While the SDK is pre-1.0, every release bumps the patch version (`0.0.x`) and may contain breaking changes; they are always listed under **Breaking** (see `CONTRIBUTING.md`).

## v0.0.5 — unreleased

Makes SDK traffic cheaper for hosts on slow links, from the mobile app's request-efficiency proposal (`docs/plans/request-efficiency.md`). Nothing here needs a platform change.

### Breaking

- **`dashboard()->capabilities()` is remembered for the session.** The first call fetches it; later calls in the same process return that answer until sign-out, a team switch or a different user signing in. Pass `capabilities(fresh: true)` to refetch it, for example on pull-to-refresh. A host test that changes the faked capabilities between two calls must now pass `fresh: true` (or call `SupportManager::session()->forgetRemembered()`) to see the change.
- **`lazy()` asks for 100 records a page** (`ResourceQuery::LAZY_PAGE_SIZE`) when the query sets no `perPage()`, instead of taking the platform's default of 15–50. A test that fakes a page walk and asserts on `per_page`, or on the number of pages fetched, sees the new size. Call `perPage()` to keep a specific size.

### Added

- **`SupportManager::concurrently(...$calls)` and `settle(...$calls)`** make several calls at once. Each closure gets the SDK and calls it as usual. The requests go out together, so a screen waits for its slowest read rather than the sum of them all. Name the arguments to key the results. `concurrently()` throws the first failure after every call has finished. `settle()` never throws: it returns a `Client\Settled` per call, with `ok()`, `failed()`, `value()` and `exception()`. Each call fails with the same typed exception it throws on its own. A 401 in several calls renews the session once. A call that makes several requests in turn, such as a `lazy()` walk inside its closure, keeps its own order. Every resource method works inside a call, unchanged, and `Http::fake()` and `SupportManager::fake()` work too. Calls run in PHP fibers over one curl multi handle, so they need the curl extension; without it they run one after another.
- **`AuthSession::remember($key, $compute)` and `forgetRemembered(?$key)`**, the in-memory session store behind the capabilities cache. Remembered values are never written to secure storage.

### Fixed

- **Concurrent token renewal no longer signs the user out.** Before, if two requests sent with the same expired token both got a 401, each one refreshed. The second refresh then sent a refresh token the platform had already rotated out, and the rejection cleared the session. Now a request whose token has already been replaced retries with the new token instead of refreshing again.
- **Requests reuse connections.** Every request used to build a fresh curl handle, so each one paid for a new TCP connection and TLS handshake. The client now keeps one Guzzle handler for its lifetime. On Guzzle 7.11+ it also asks for persistent transport sharing, which on PHP 8.5+ keeps connections and TLS sessions across PHP requests in the same process. Only connections, TLS sessions and DNS are shared, never cookies or credentials.

## v0.0.4 — 2026-10-31

Wraps the platform's API wave 3 (platform PR #1066): shift admin, account security, and the weather and bin-day widgets. The pinned spec is now platform commit `0aae918cf`. The SDK calls 223 of its 231 operations; the other 8 are the same deliberately unwrapped ones as before. Nothing in this release breaks existing callers.

### Added

- **Shift admin** on `shifts()`, for admins and managers:
  - `update($id, $data, $scope)` and `destroy($id, $scope)`. `$scope` is a new `Enums\Rota\RecurringShiftScope`: `Single` (the default) or `Future`, which also changes later shifts in a recurring series.
  - `duplicate($id, ?$date)` copies a shift onto another day, or onto its own day when `$date` is null.
  - `assign($id, $userId)` gives the shift to someone.
  - `create()` now documents the platform's new `pay_enhancement_id` and `is_pay_enhancement_private` fields.
- **`Shift` DTO:**
  - `canUpdate`, `canDelete`, `canDuplicate` and `canAssign` flags.
  - `payEnhancement`, a new `ShiftPayEnhancement` (`id`, `name`, `factor`, `isPrivate`). The platform always sent it; the SDK used to drop it.
- **Account security.** Three new resources. They don't send a team header, so they work for a user with no team.
  - `trustedDevices()`: `all()`, `destroy($id)` and `destroyOthers()`, which returns how many devices were revoked. See **Known issue** below.
  - `socialAccounts()`: `all()`, `providers()`, `link($provider, $token)` and `destroy($provider)`.
  - `twoFactor()`:
    - `show()`
    - `beginTotpSetup()`, which returns the secret, an otpauth URI and a QR code.
    - `confirmTotpSetup($code)` and `regenerateBackupCodes($code)`, which return `BackupCodes`.
    - `disableTotp($code)`.

    Wrong codes raise `ApiValidationException` on `code`. Calling a method in the wrong state, for example disabling an app that isn't set up, returns 409, and repeated wrong codes return 429. `TotpSetup` and `BackupCodes` redact their secrets when dumped.
  - New enums: `Enums\Security\SocialProvider` and `TrustedDeviceType`. `auth()->loginWithProvider()` now also accepts a `SocialProvider`.
- **Today widgets:**
  - `dashboard()->weather()` returns a `WeatherForecast` (current conditions and daily entries, with a `WeatherCondition` enum), or null when the platform has no forecast.
  - `binDay()->show()` returns a `BinCollectionSchedule`: each bin's next collection and which are due tomorrow, with a `BinType` enum. It throws when the team has the Household module switched off, so check `capabilities()` first.
- **Fakes:**
  - New factories for trusted devices, social accounts, two-factor, weather and bin day.
  - New `ShiftFactory` states: `manageable()`, `withPayEnhancement()`, `recurring()` and `onCompletedPayslip()`.

### Known issue

The platform identifies "this device" by a hash of the request's User-Agent alone, and every SDK install sends the same default User-Agent. Until the platform identifies the device another way, every phone using the SDK counts as the same device: `trustedDevices()->all()` marks all of them as current, and `destroyOthers()` keeps all of them.

## v0.0.3 — 2026-10-31

An internal rework that makes the SDK cheaper to extend and checks it against the platform's API spec, plus fixes for the bugs that check found. It also includes the leave-calculation and journey-start fixes that were merged as v0.1.0 but never tagged, so v0.1.0 was not released. Adding an endpoint is now a typed DTO, one resource method using the base helpers, and a short test. `CONTRIBUTING.md` walks through it end to end.

### Breaking

- **`leaveBalance()->calculate()`** now returns a `LeaveCalculation` instead of a `LeaveBalance`. It used to read the response as a balance, so its hours and sleeps always came back as zero. `LeaveCalculation` has:
  - `requirement`: the hours and sleeps the request needs
  - `currentBalance`: the user's balance for that leave year, as a `LeaveBalance`
  - `balanceAfterRequest`: what would be left; a negative value means the request would overdraw the balance
  - `year`

  Replace `calculate(...)->hours->remaining` with `calculate(...)->balanceAfterRequest->hours`, or with `->currentBalance->hours->remaining` if you want the balance before the request.
- **`journeys()->start()`** takes no arguments. The platform always ignored `$driverName` and `$comments`: it records the signed-in user as the driver and leaves comments empty, so set comments afterwards with `update()`. It also never closed an open journey. It refuses with a 422, which the SDK now documents as an `ApiValidationException` ("A journey is already in progress."). Call `finish()` on the open journey first.
- **Test fakes:**
  - The `leave/balance/calculate` fake now returns the platform's response shape. It takes the requirement off the seeded balance; the default is one 8-hour day, and `LeaveBalanceFactory::calculating($hours, $sleeps)` sets your own.
  - The `car-journeys/start` fake now refuses with a 422 while a journey is open, instead of closing it. The driver it records defaults to `Fake User`; set it with `CarJourneyFactory::startedBy($name)`.
- **Downloads return typed results** and throw `SupportManagerApiException` on an error response, instead of returning the raw `Response`.
  - **Return `Http\Download`** (`body()`, `filename()`, `mimeType()`, `size()`, `saveTo()`, `response()`):
    - `payslips()`, `p45s()`, `p60s()` and `timesheets()`: `download()`
    - `profile()->downloadEmploymentConfirmationLetter()`
    - `signatureRequests()->downloadOriginal()`
  - **Return `Http\DownloadLink`** (`url`, `filename`, `expiresAt`):
    - `documents()->download()`
    - `dbsChecks()->downloadCertificate()`
    - `rightToWork()->downloadPrimaryDocument()` and `downloadSupportingDocument()`
    - `driversLicenceChecks()->downloadCheckResultDocument()`
    - `signatureRequests()->downloadSigned()`
- **`ResourceQuery::find()` is removed.** It let any resource with `query()` call `GET {path}/{id}`, including 11 where that route doesn't exist, for example `chores()->query()->find(1)`. Use the resource's own `find($id)`, which exists only where the route does.
- **`find()`, `all()` and `query()` exist only where the platform has the route.** They used to be inherited by every resource, so calls like `chores()->find()` failed with a 405 against the real platform. They now come from the `Resources\Concerns\Listable` (`query()`, `all()`) and `Findable` (`find()`) traits, and an architecture test checks each against the spec.
  - **No longer offer any of the three:** `dashboard()`, `keysafe()`, `mealPlan()`, `leaveAdmin()`, `leaveBalance()`, `automaticPayEnhancements()`, `estimatedPay()`, `starterDeclaration()`, `profile()`, `rotaCalendar()`, `quickAdd()`, `shiftBlocks()`.
  - **No longer offer `find()`:** `choreCategories()`, `chores()`, `consumables()`, `shoppingList()`, `leaveEntitlements()`, `leaveAdjustments()`, `leaveNotWorkingNotices()`, `deviceTokens()`, `shiftAmendmentRequests()`, `shiftApplications()`, `shiftRequests()`.
  - **No longer offer `find()` or `query()`, but keep their own `all()`:** `notifications()`, `tasks()`, `teams()`, `payRuns()`.
  - **No longer offer `query()`:** `signatureRequests()`. It keeps `all(?status)` and `find()`.
- **Calendar:** `rotaCalendar()->bankHolidays()`, `birthdays()` and `anniversaries()` now take required `$from` and `$to` dates (`string` or `DateTimeInterface`) instead of an optional query array. The platform rejected calls without them.
- **Keysafe:** `keysafe()->reveal()` returns the code as `?string`. It used to return a `Keysafe` whose other fields were always false, because the platform only sends the code.
- **Profile:** `deleteAddress()`, `deleteAvatar()` and `changePassword()` now throw on error responses instead of failing silently.
- **DTOs:** `fromArray()` takes a single array everywhere. `AccidentReport`, `LeaveRequest`, `NotWorkingNotice`, `StarterDeclaration` and `SignatureRequest` no longer accept a second `$meta` argument. Permission flags are read from the flattened resource's `meta.can`.
- **Test fakes:**
  - **JSON:API types:** fake responses now use the platform's real snake_case `type` values, for example `rota_entries`, `car_journeys`, `leave_requests`, `shopping_list_items`, `d_b_s_checks`, `right_to_works`. Thirteen factories changed; host tests that assert on the old kebab-case values need updating.
  - **Route handlers:** custom `FakeRoute` handlers now receive a fifth `Registration` argument. Handlers with fewer parameters still work.
  - **Team id:** records take the team id of the acting team when they are seeded (`actingAsTeam()` before `seed()`), instead of a fixed `42`.
  - A few fake 404 messages now read "Not found".

### Deprecated

These still work, and raise `E_USER_DEPRECATED` pointing at the new name:

| Deprecated | Use instead |
|---|---|
| `shiftApplications()->cancel()` / `remove()` | `destroy()` |
| `shiftRequests()->cancel()` | `destroy()` |
| `chores()->delete()` / `shoppingList()->delete()` | `destroy()` |
| `signatureRequests()->file()` | `downloadOriginal()` |
| `profile()->employmentConfirmationLetter()` | `downloadEmploymentConfirmationLetter()` |
| `query()->include()` | nothing: the platform ignores `include` everywhere, so it now sends nothing |

### Fixed

- `auth()->loginWithProvider()` now sends the device token as `X-Device-Token`, through a new optional `$deviceToken` argument. Trusted devices signing in with Google, Microsoft or Apple are no longer asked to verify again.
- `EstimatedPay` now includes the per-type `hoursBreakdown` and `sleepsBreakdown` lists (`EstimatedPayBreakdownLine`), which it used to drop.
- The car journey `create()` and `update()` examples now use the fields the platform accepts.

### Added

- **Shared hydrator:** DTOs are hydrated by an attribute-driven `Data\Hydration\Hydrator` (`#[From]`, `#[Can]`, `#[ListOf]` and others), so a new DTO is just a typed constructor.
- **Base `Resource` helpers:** `item()`, `collection()`, `paginated()`, `data()`, `dataList()`, `dataPaginated()`, `raw()`, `downloadFile()` and `downloadLink()`.
- **Fake building blocks:** `FakeRoute::action()`, `download()`, `downloadLink()`, `collection()` and `flat()`; `Json::flat()`, `notFound()`, `forbidden()` and `validation()`; `Fake::teamId()`; and `Factory::forTeam()`.
- **Contract tests:**
  - Every faked request and response in the test suite is checked against the platform's OpenAPI spec (`tests/Contract/openapi.yaml`, refreshed with `composer sync-openapi`).
  - `composer coverage:api` reports which spec operations the SDK calls: currently 206 of 214, with the other 8 listed with a reason.
  - Places where the spec disagrees with the platform code are recorded in `tests/Contract/spec-exceptions.php`.
- **Architecture tests** enforce the conventions:
  - Naming: `destroy()`, `download*()`.
  - Each resource is wired to an accessor and a facade `@method`.
  - `final readonly` DTOs using the hydrator.
  - DTO constructors only grow at the end, checked by a snapshot.
  - Backed enums, strict types and layer boundaries.
- **Route guarantee:** every public resource method must send a contract-validated request in at least one test, or the suite fails. Combined with the spec check, no public SDK method can call a route the platform doesn't have. The auth client's 15 paths were checked against the spec by hand.
- **Docs:** `CONTRIBUTING.md`, a guide to adding an endpoint end to end, plus the conventions.
- `Data\Leave\LeaveCalculation` and `Data\Leave\LeaveAmount`.

## v0.0.2 — 2026-10-31

Brings the SDK up to the platform's API wave 2. This release needs a platform that serves wave 2: v0.0.1 calls `tasks/tasks/*` and `user/emergency-contact`, which the platform no longer serves.

### Breaking

- **Tasks:** `tasks()` now calls `/tasks/*` instead of `/tasks/tasks/*`, following the platform's route move. Method signatures are unchanged.
- **Profile:** the singular emergency contact is now a collection.
  - `emergencyContact()` and `saveEmergencyContact()` are removed. Use `emergencyContacts()` and `saveEmergencyContacts()`.
  - `deleteEmergencyContact()` now takes an id, and throws on failure.
  - `Profile::$emergencyContact` is replaced by `Profile::$emergencyContacts` (a list).
- **Documents:** `Document::$file` is removed. `Policy::$content` is replaced by `Policy::$contentHtml`.
- **Household:** the meal plan is now read as a JSON:API resource, which v0.0.1 cannot read from a wave 2 platform. The chores filter `overdue` is now `due`.
- **Leave:** `LeaveYears::$years` now includes years locked at year end, which are also listed in the new `$locked`.
- **Data object constructors:** new required parameters were added to `Profile`, `LeaveRequest`, `LeaveBalance`, `LeaveYears`, `Payslip` and `Timesheet`. This only affects code that constructs them directly; `fromArray()` is unchanged. Other data objects gained new parameters at the end with defaults.
- **Test fakes:**
  - `ProfileFactory` seeds `emergency_contacts` as a list.
  - `P45Factory` uses the `2025-26` tax-year format.
  - `RightToWorkFactory` seeds the platform's real document type values.
  - The P45 and P60 fakes mark a document viewed when it is opened.
  - Seeded records carry `meta.can` permission flags.

### Added

- **New modules:**
  - Accidents (`accidents()`): list, find, record, and the subject declaration.
  - E-signing (`signatureRequests()`): list, find, download the file to sign, save field values, sign, and download the signed copy.
  - Dashboard (`dashboard()`): `layout()` and `capabilities()`.
- **Rota:**
  - `shifts()->requestAmendment()` and `shiftAmendmentRequests()`.
  - `shiftRequests()` for extra hours.
  - `shiftBlocks()` for sleepover start options, applying and withdrawing.
  - `appointments()->update()` and `destroy()`.
  - `rotaCalendar()->feed()` and `regenerateFeed()`.
- **Leave:**
  - `leaveNotWorkingNotices()`: list, record and withdraw.
  - `leaveBalance()->overtime()` and `leaveAdjustments()->mine()`.
  - Permission flags and leave type on `LeaveRequest`.
  - An entitlement breakdown on `LeaveBalance`.
- **Payroll:**
  - `starterDeclaration()`: `show()` and `update()`, with an optional P45 upload.
  - New fields on `Payslip` and `Timesheet`.
- **Profile and users:**
  - `addressSuggestions()` and `addressSuggestion()`.
  - `emergencyContacts()` and `saveEmergencyContacts()`.
  - `employment()` and `employmentConfirmationLetter()`.
  - `users()->options()` for colleague pickers.
- **Compliance:**
  - `submit()` on `dbsChecks()`, `rightToWork()` and `driversLicenceChecks()`.
  - Document download methods for each check type.
- **Household:**
  - `consumables()` with `adjustStock()`.
  - `chores()->completeMany()`, `completeGroup()`, `markNotNeeded()` and `markGroupNotNeeded()`.
- **Documents:** folder breadcrumbs (`ancestors`) and a document `scope`.
- **JSON:API:** a resource object's own `meta` (for example `meta.can`) is now kept on `ResourceObject::$meta` and included in `flat()`. Data objects across modules expose these as `can*` flags.
- **Fakes:** factories and routes for everything above. A factory's custom routes may now answer paths outside its own collection.

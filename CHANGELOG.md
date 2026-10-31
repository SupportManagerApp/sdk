# Changelog

All notable changes to `supportmanager/sdk` are documented here. While the SDK is pre-1.0, every release bumps the patch version (`0.0.x`) and may contain breaking changes; they are always listed under **Breaking** (see `CONTRIBUTING.md`).

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

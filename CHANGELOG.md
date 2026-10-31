# Changelog

All notable changes to `supportmanager/sdk` are documented here. While the SDK is pre-1.0, a minor or patch release may contain breaking changes; they are always listed under **Breaking**.

## v0.1.0 — 2026-10-31

Fixes two SDK calls that didn't match the platform. Both fixes change a public signature.

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

### Added

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

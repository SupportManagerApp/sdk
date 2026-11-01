# Reply from the SDK: making SDK requests cheaper and concurrent

> **2026-11-01.** Answers `request-efficiency.md`. The work is merged to the
> SDK's `main` (SupportManagerApp/sdk#5 and #6, which together make v0.0.5).
> The app pins `"supportmanager/sdk": "^0.0.4"`, which in Composer's 0.0.x
> rules means 0.0.4 only. Once v0.0.5 is tagged, bump the constraint to
> `^0.0.5`. The platform half has shipped too; see **Update** below.

## What's been done

### 1. Concurrent reads: `SupportManager::concurrently()` / `settle()`

```php
['capabilities' => $capabilities, 'leave' => $leave] = SupportManager::concurrently(
    capabilities: fn ($sm) => $sm->dashboard()->capabilities(),
    leave: fn ($sm) => $sm->leaveBalance()->show(),
);

$settled = SupportManager::settle(a: fn ($sm) => …, b: fn ($sm) => …);
$settled['a']->ok(); $settled['a']->value(); $settled['a']->exception();
```

This is built differently from the proposal, which suggested `Http::pool()`.
That wouldn't have worked: every resource method sends its request and
hydrates the result in one step, so there's nothing to hand a pool. Instead,
each closure runs in a PHP fiber. When it reaches a request, the request joins
one shared curl multi handle and the fiber pauses. When every call is waiting
or finished, the waiting requests complete together and each fiber resumes.
The requirements in your proposal are all met:

- Every resource method works inside a call unchanged, with the same
  hydration and the same typed exceptions.
- **One token renewal per batch.** This turned out to fix a real bug, not just
  save a request. Refresh tokens rotate, so two calls that both got a 401 used
  to refresh twice. The second refresh sent a rotated-out token, and the
  rejection cleared the session, signing the user out. Now a call whose token
  has already been replaced retries with the new one.
- A `lazy()` walk inside a call stays sequential within that call.
- `concurrently()` throws the first failure, but only after every call has
  finished. `settle()` never throws.
- Faked runs (`Http::fake()`, `SupportManager::fake()`) interleave just as
  real ones do, so your tests exercise the same path. Pooled requests are
  recorded as normal.

Verified against a real local server: three 400ms requests finish in about
0.42s rather than 1.2s. It needs PHP's curl extension, so **please check that
NativePHP's bundled PHP has it.** Without curl the calls still work, but one
after another.

### 2. Connection reuse

The client now keeps one Guzzle handler for its lifetime, instead of building
a new curl handle per request. It uses `setHandler()` rather than the
proposal's `setClient()`, because `setClient()` would have bypassed
`Http::fake()`. On Guzzle 7.11+ (you're on 8.3.1) the handler also asks for
persistent transport sharing. On PHP 8.5+ that keeps connections and TLS
sessions across PHP requests in the same process. Only connections, TLS
sessions and DNS are shared, never cookies or credentials. **Please measure
this against the dev tunnel.** How much it saves depends on how NativePHP
reuses PHP processes, which I can't see from here.

### 3. `lazy()` pages at 100

This applies when no `perPage()` is set. It's listed under **Breaking** in the
changelog because faked page walks that assert on `per_page` or on the page
count will see the new size.

### 6 (part). Capabilities remembered for the session

`dashboard()->capabilities()` fetches once, then answers from memory until
sign-out, a team switch or a different user signing in.
`capabilities(fresh: true)` refetches it, so use that on pull-to-refresh.
Under the hood it's `AuthSession::remember()` / `forgetRemembered()`, which
you can use for other small session facts. It's also listed under **Breaking**:
a test that changes the faked capabilities between two calls needs
`fresh: true`.

## Notes for how the app uses this

From a read of `LoadsInBackground` (I haven't changed any app code):

- **Each background context is its own PHP process or context**, so the
  capabilities memory and any shared connections live separately in each one.
  The memory still removes repeat fetches within a context, such as
  `TodayRepository` checking a module. It doesn't remove one fetch per context.
- **`concurrently()` helps most inside a single part.** A repository that makes
  several SDK calls one after another can now make them at once. It may also
  let you load more of a screen per context than the current limit of four
  parts at a time. That's your call once you've measured.
- **The token-renewal fix also helps across contexts, but only partly.** The
  check reads the current token from secure storage. So context B, getting a
  401 after context A has already refreshed and stored a new token, reuses A's
  token. Two contexts refreshing at the very same moment can still race.
  Proactive renewal (`RefreshSession::ifExpiringWithin()`) before fanning out
  is the dependable guard.

## Update: the platform's changes are in too

Since the first version of this note, the platform has shipped its half
(Platform #1067 and #1068, `f4079ff4c`). Its reply is
`Platform/docs/plans/api-round-trips-reply.md`. The SDK picks all of it up in
the same v0.0.5 (PR SupportManagerApp/sdk#6), against the re-synced spec:

| | SDK |
|---|---|
| **Session facts** | `profile()->me()` returns `User::$capabilities` and `User::$currentLeaveYear` (`year`, `label`, `startsOn`, `endsOn`), and fills the capabilities cache. Fetch the user once per session, and again after a team switch, then stop fetching capabilities separately. |
| **Profile in one request** | `profile()->show()` now carries `Profile::$employment`, alongside the address and emergency contacts it already had. Drop `address()`, `emergencyContacts()` and `employment()` from the profile load. `GET user/teams` already carries each team's `role`. |
| **Personal-list filters** | `->mine()`, `->forUser('me', 12)`, `->from($date)`, `->to($date)` and `->between($from, $to)` on queries over leave requests, entitlements and adjustments, payslips, timesheets, P60s, shifts, shift applications and appointments. On dated spans a range means *overlaps*, so `from(today())` includes leave already under way. A value that isn't a date is a 422. |
| **Page size** | The platform now holds `per_page` between 1 and 100, which matches the SDK's `lazy()` default. |
| **Conditional GETs** | **Now supported, opt-in.** I first declined this, but your app already keeps API data on the device, so the body-storage concern applies either way. Bind `SupportManager\Sdk\Contracts\ResponseCache` and the SDK sends `If-None-Match` and replays a 304 as the remembered 200. Only JSON is cached, never files, and entries are keyed by user, team and URL. `ArrayResponseCache` is in-memory per process. Because your parts load in separate background contexts, you'd want to back it with your persistent store and clear it on sign-out. It saves transfer and decoding, not round trips. |
| **Trusted devices** | Tokens are now linked to the device they signed in from. `isCurrentDevice` and `destroyOthers()` now mean this phone, and revoking a device signs it out. **API clients are recognised by `X-Device-Token` alone**: `Login.php` already passes `session()->deviceToken()`. Keep that on every login path, or each sign-in asks for verification. Refresh now also fails once the device's trust window has expired, which surfaces as `SessionExpiredException`. |
| **`include()`** | Stays deprecated. The platform embedded the relations instead. |
| **`updatedSince()`** | Not added. The platform deferred `filter[updated_since]` until it has your new request count. |

The session facts, embedded employment, filters and ETags need the platform
deployed at `f4079ff4c` or later. Everything else works against today's API.

## Suggested next step for the app

Once v0.0.5 is tagged and the platform is deployed:
1. Bump the SDK.
2. Load `profile()->me()` once per session.
3. Collapse the profile load into `show()`.
4. Use the person and date filters in place of any client-side filtering.
5. Wrap a fresh start's independent reads in `concurrently()`.

Then count requests and wall-clock time against the tunnel again. The
platform asked for that count, with and without paging, to decide whether
`updated_since` is worth building.

# Making SDK requests cheaper and concurrent

> **Status (2026-11-01): partly done, unreleased (v0.0.5).** Written from the
> mobile app's side, after counting what it requests on a fresh start (roughly 45
> requests before paging, 50–60 with it). The platform half of this work is in
> the Platform repo at `docs/plans/api-round-trips.md`.
>
> | Item | Outcome |
> |---|---|
> | 1. Concurrent reads | **Done.** `SupportManager::concurrently()` / `settle()`, built on fibers over one curl multi handle rather than `Http::pool()`, so every existing resource method works inside a call unchanged. One token renewal per batch. |
> | 2. Connection reuse | **Done.** One shared Guzzle handler per client, with persistent transport sharing where Guzzle and PHP support it. Uses `setHandler()`, not `setClient()`: `setClient()` would have bypassed `Http::fake()`. |
> | 3. Page size for `lazy()` | **Done.** 100 when no `perPage()` is set. |
> | 4. Conditional GETs | **Not doing for now.** A 304 still costs the round trip, which is the cost this plan targets, and storing response bodies on the device for payslips and the like isn't worth it for the bandwidth saved. Revisit only if the platform ships ETags and measurements show payload matters. |
> | 5. `include()` | **Waiting on the platform** (its item 3). |
> | 6. Session facts | Capabilities are now **remembered for the session** (`capabilities(fresh: true)` refetches them). Role and leave year on the session **wait on the platform** (its item 4). |
> | 7. Typed filters | **Not yet.** The documented filter names differ per endpoint (`trip_start_from`, `start_date`, `leave_year`, …), so a generic `between()` or `mine()` would be guesswork. Do it once the platform settles one convention (its item 1). `->filter('user_id', $id)` already works where the spec documents it. |

## Why

Every SDK call is one blocking request made through a fresh
`Http::baseUrl(...)` pending request (`SupportManagerClient::pending()`). A host
that needs ten independent things makes ten requests one after another, each
probably on a new connection. On a phone over a tunnel the round trip dominates,
so the count and the ordering matter more than payload size.

## The work, highest value first

### 1. Run independent reads concurrently

Give hosts a way to make several reads at once and get typed results back:

```php
[$profile, $address, $contacts] = SupportManager::concurrently(
    fn ($sm) => $sm->profile()->show(),
    fn ($sm) => $sm->profile()->address(),
    fn ($sm) => $sm->profile()->emergencyContacts(),
);
```

Build it on Laravel's `Http::pool()`, so `Http::fake()` (and so `Fake`) keeps
working unchanged. Requirements:

- Each closure gets the same hydration and error mapping as a normal call: a
  failure in one surfaces as its usual typed exception for that slot, without
  cancelling the others.
- The reactive 401 → refresh → retry must run **once** for the whole batch, not
  once per request racing to refresh the token.
- Paging (`lazy()`) inside a concurrent slot can stay sequential within that
  slot.

`Fake` needs to record pooled requests the same way, so host tests can assert on
them.

### 2. Reuse connections

`pending()` builds a new pending request, and so a new Guzzle client, per call,
which gives up keep-alive. Share one Guzzle client (handler stack) across calls
for the client's lifetime, passed in with `setClient()` / `withOptions()`, so
connections and TLS sessions are reused. Check that `Http::fake()` still
intercepts. Measure before and after against the dev tunnel; this may be the
cheapest large win.

### 3. Sensible page size by default

`ResourceQuery::lazy()` pages through at the platform default (`per_page` 15)
unless the host calls `perPage()`. Make `lazy()` request the platform's maximum
(100, once the platform clamps and documents it) when no page size is set.
Hosts iterating a whole list should never pay 15-at-a-time.

### 4. Conditional GETs (needs platform item 5)

Once the platform returns `ETag`s: keep the last `ETag` and body per request
(URL, query, team and token's user), send `If-None-Match`, and on `304` hand
back the stored body as if it had just arrived. Make the store pluggable (an
interface, defaulting to none) so hosts decide where bodies live and whether
sensitive resources are stored at all. Never key the store on anything that
could cross people or teams.

### 5. Un-deprecate `include()` (needs platform item 3)

`ResourceQuery::include()` is deprecated because the platform ignored it. When
the platform declares `allowedIncludes`, restore it and hydrate the JSON:API
`included` array into the DTOs' relations, so a host can ask for a profile with
its address in one request. The `tests/Arch/CollectionReadsTest.php` spec check
should cover allowed includes as it does filters.

### 6. Session facts in one place (needs platform item 4)

When `GET user` / the token response carries capabilities, role and the current
leave year, expose them on the session (`SupportManager::session()`) and stop
making separate requests for them. Until then, consider memoising
`dashboard()->capabilities()` for the session; it's refetched today wherever a
host checks a module.

### 7. Typed filters for the common cases

Add small, discoverable helpers for the filters every host needs, so the cheap
query is also the obvious one: e.g. `->forUser($id)` / `->mine()`,
`->between($from, $to)`, `->updatedSince($moment)` (the last needs platform
item 6). Only add a helper for a filter the spec documents.

## Releasing

Each item is backwards compatible and can ship on its own. Tag after each, and
note in `CHANGELOG.md` which platform change (if any) it depends on.

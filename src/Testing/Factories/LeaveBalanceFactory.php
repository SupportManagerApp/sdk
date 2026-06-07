<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake leave balance — a flat endpoint (`type()` is null, so responses are a
 * bare `{data: {year, hours, sleeps}}`, not a JSON:API document).
 *
 * Seed one balance for the common case, or seed several (varying year via
 * {@see forYear()}) to test year navigation: the current-user and another-user
 * routes serve the seeded balance whose `year` matches the request's `?year=`,
 * falling back to the first seeded record when nothing matches (so a single-seed
 * test works regardless of the query it passes).
 *
 * The selectable-years endpoint (`leave/balance/years`) is derived from the
 * seeded balance's year — the current year plus the next — with any year passed
 * to {@see lockingYear()} excluded, mirroring the platform's locked-year logic.
 *
 * ```php
 * SupportManager::fake()->seed(LeaveBalanceFactory::new());
 * SupportManager::leaveBalance()->show()->hours->remaining;
 * SupportManager::leaveBalance()->availableYears()->years; // [2026, 2027]
 * ```
 */
final class LeaveBalanceFactory extends Factory implements ProvidesFakeRoutes
{
    /** @var list<int> */
    private array $lockedYears = [];

    public function path(): string
    {
        return 'leave/balance';
    }

    public function definition(int $index): array
    {
        return [
            'year' => 2026,
            'hours' => [
                'entitlement' => 224.0,
                'pending' => 8.0,
                'approved' => 16.0,
                'remaining' => 200.0,
                'percentages' => ['used' => 7],
            ],
            'sleeps' => [
                'entitlement' => 10.0,
                'pending' => 1.0,
                'approved' => 2.0,
                'remaining' => 7.0,
                'percentages' => null,
            ],
        ];
    }

    /**
     * Target a specific leave year, so year navigation resolves distinct
     * per-year balances.
     */
    public function forYear(int $year): static
    {
        return $this->state(['year' => $year]);
    }

    /**
     * Exclude a year from the selectable-years list, as a year-end lock would.
     */
    public function lockingYear(int $year): static
    {
        $clone = clone $this;
        $clone->lockedYears = [...$this->lockedYears, $year];

        return $clone;
    }

    public function fakeRoutes(): array
    {
        $lockedYears = $this->lockedYears;

        return [
            // Selectable years — registered before `leave/balance/{id}` so
            // "years" is not mistaken for a user id.
            FakeRoute::get('leave/balance/years', static function (RecordStore $store) use ($lockedYears): mixed {
                $current = (int) ($store->all('leave/balance')[0]['year'] ?? 2026);

                $years = array_values(array_filter(
                    [$current, $current + 1],
                    static fn (int $year): bool => ! in_array($year, $lockedYears, true),
                ));

                $labels = [];
                foreach ($years as $year) {
                    $labels[$year] = (string) $year;
                }

                return Http::response([
                    'data' => ['years' => $years, 'current' => $current, 'labels' => $labels],
                    'meta' => ['team_id' => 42],
                ]);
            }),

            // Another user's balance: serve the record matching ?year, else the
            // first seeded record.
            FakeRoute::get('leave/balance/{id}', static function (RecordStore $store, array $matches, Request $request): mixed {
                return self::balanceForYear($store, $request);
            }),

            // Current user's balance, year-aware (the generic flat singleton
            // would ignore ?year).
            FakeRoute::get('leave/balance', static function (RecordStore $store, array $matches, Request $request): mixed {
                return self::balanceForYear($store, $request);
            }),

            // Prospective-request preview: echo the seeded balance back.
            FakeRoute::post('leave/balance/calculate', static function (RecordStore $store): mixed {
                $record = $store->all('leave/balance')[0] ?? null;

                return $record === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item(null, $record));
            }),
        ];
    }

    /**
     * Serve the seeded balance matching the request's `?year=`, falling back to
     * the first record.
     */
    private static function balanceForYear(RecordStore $store, Request $request): mixed
    {
        $records = $store->all('leave/balance');

        if ($records === []) {
            return Http::response(['message' => 'Not found'], 404);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        $match = null;
        $year = $query['year'] ?? null;

        if (is_string($year)) {
            foreach ($records as $record) {
                if ((string) ($record['year'] ?? '') === $year) {
                    $match = $record;
                    break;
                }
            }
        }

        return Http::response(Json::item(null, $match ?? $records[0]));
    }
}

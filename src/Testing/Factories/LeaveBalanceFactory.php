<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
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
 * The browsable-years endpoint (`leave/balance/years`) is derived from the
 * seeded balance's year — the current year plus the next — with any year passed
 * to {@see lockingYear()} kept in `years` and also listed in `locked`, as the
 * platform reports year-end locks. Asking for `include=breakdown` adds an
 * entitlement breakdown that puts the whole entitlement down to the contract.
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

    /** @var array{hours: float, sleeps: float} */
    private array $requirement = ['hours' => 8.0, 'sleeps' => 0.0];

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
                'percentages' => ['approved' => 7.14, 'pending' => 3.57, 'remaining' => 89.29],
            ],
            'sleeps' => [
                'entitlement' => 10.0,
                'pending' => 1.0,
                'approved' => 2.0,
                'remaining' => 7.0,
                'percentages' => ['approved' => 20.0, 'pending' => 10.0, 'remaining' => 70.0],
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
     * Set what a calculated request needs (one 8-hour day by default). The fake
     * can't read a working pattern, so this stands in for the platform's
     * calculation, and is taken off the seeded balance's `remaining`.
     */
    public function calculating(float $hours, float $sleeps = 0.0): static
    {
        $clone = clone $this;
        $clone->requirement = ['hours' => $hours, 'sleeps' => $sleeps];

        return $clone;
    }

    /**
     * Lock a year at year end: it stays browsable and is listed in `locked`.
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
        $requirement = $this->requirement;

        return [
            // Selectable years — registered before `leave/balance/{id}` so
            // "years" is not mistaken for a user id.
            FakeRoute::flat('leave/balance/years', static function (RecordStore $store) use ($lockedYears): array {
                $current = (int) ($store->all('leave/balance')[0]['year'] ?? 2026);
                $years = [$current, $current + 1];

                $labels = [];
                foreach ($years as $year) {
                    $labels[$year] = (string) $year;
                }

                return [
                    'years' => $years,
                    'minimum' => $current,
                    'maximum' => $current + 1,
                    'current' => $current,
                    'locked' => array_values(array_intersect($years, $lockedYears)),
                    'labels' => $labels,
                ];
            }, teamMeta: true),

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

            // Prospective-request preview: the configured requirement taken off
            // the seeded balance.
            FakeRoute::post('leave/balance/calculate', static function (RecordStore $store, array $matches, Request $request, Fake $fake) use ($requirement): mixed {
                $record = $store->all('leave/balance')[0] ?? null;

                if ($record === null) {
                    return Json::notFound();
                }

                $lines = [];
                foreach (['hours', 'sleeps'] as $axis) {
                    $line = is_array($record[$axis] ?? null) ? $record[$axis] : [];
                    unset($line['percentages']);
                    $lines[$axis] = $line;
                }

                return Http::response(Json::flat([
                    'requirement' => $requirement,
                    'current_balance' => $lines,
                    'balance_after_request' => [
                        'hours' => (float) ($lines['hours']['remaining'] ?? 0) - $requirement['hours'],
                        'sleeps' => (float) ($lines['sleeps']['remaining'] ?? 0) - $requirement['sleeps'],
                    ],
                    'year' => (int) ($record['year'] ?? 2026),
                ], ['team_id' => $fake->teamId()]));
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
            return Json::notFound();
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

        $balance = $match ?? $records[0];

        if (($query['include'] ?? null) === 'breakdown') {
            $balance['breakdown'] = self::breakdown($balance);
        }

        return Http::response(Json::item(null, $balance));
    }

    /**
     * An entitlement breakdown for a balance, crediting the whole entitlement
     * to the contract and nothing to the other sources.
     *
     * @param  array<string, mixed>  $balance
     * @return array<string, array<string, mixed>>
     */
    private static function breakdown(array $balance): array
    {
        $hours = is_array($balance['hours'] ?? null) ? (float) ($balance['hours']['entitlement'] ?? 0) : 0.0;
        $sleeps = is_array($balance['sleeps'] ?? null) ? (float) ($balance['sleeps']['entitlement'] ?? 0) : 0.0;
        $none = ['hours' => 0.0, 'sleeps' => 0.0, 'has_data' => false];

        return [
            'contract' => ['hours' => $hours, 'sleeps' => $sleeps],
            'overtime' => $none,
            'toil' => $none,
            'adjustments' => $none,
            'total' => ['hours' => $hours, 'sleeps' => $sleeps],
        ];
    }
}

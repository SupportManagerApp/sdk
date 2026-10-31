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
 * Fake overtime (TOIL) log months for the current user — a flat endpoint
 * (`type()` is null).
 *
 * Each record is one logged month, tagged with the `leave_year` it belongs to.
 * `leave/overtime` answers the platform's `{data: {year, months, totals}}` body
 * for the requested `?year=` (or the first seeded month's year), with the months
 * in date order and the totals summed over them. Ids follow the batch position.
 *
 * ```php
 * SupportManager::fake()->seed(OvertimeLogFactory::new()->count(3));
 * SupportManager::leaveBalance()->overtime()->totals->hoursAccrued; // 6.0
 * ```
 */
final class OvertimeLogFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'leave/overtime';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'id' => $number,
            'leave_year' => 2026,
            'month_end_date' => date('Y-m-t', (int) mktime(0, 0, 0, $number, 1, 2026)),
            'hours_worked' => 20.0,
            'hours_accrued' => 2.0,
            'sleeps_worked' => 1.0,
            'sleeps_accrued' => 0.5,
        ];
    }

    /**
     * Log the months in a specific leave year.
     */
    public function forYear(int $year): static
    {
        return $this->state(['leave_year' => $year]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('leave/overtime', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $all = $store->all('leave/overtime');
                $year = (int) ($fake->query($request)['year'] ?? $all[0]['leave_year'] ?? 2026);

                $months = array_values(array_filter(
                    $all,
                    static fn (array $month): bool => (int) ($month['leave_year'] ?? 0) === $year,
                ));
                usort($months, static fn (array $a, array $b): int => strcmp((string) $a['month_end_date'], (string) $b['month_end_date']));

                $fields = ['hours_worked', 'hours_accrued', 'sleeps_worked', 'sleeps_accrued'];
                $totals = array_fill_keys($fields, 0.0);

                foreach ($months as $month) {
                    foreach ($fields as $field) {
                        $totals[$field] += (float) ($month[$field] ?? 0);
                    }
                }

                return Http::response(Json::flat([
                    'year' => $year,
                    'months' => array_map(static function (array $month): array {
                        unset($month['leave_year']);

                        return $month;
                    }, $months),
                    'totals' => $totals,
                ], ['team_id' => $fake->teamId()]));
            }),
        ];
    }
}

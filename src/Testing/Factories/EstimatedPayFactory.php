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
 * Fake estimated-pay projection — a flat singleton (`type()` is null, so the
 * response is a bare `{data: {...}}`, not a JSON:API document).
 *
 * Seed one projection for the common case, or seed several (varying year/month
 * via {@see forMonth()}) to test month navigation: the custom `show` route
 * serves the seeded projection whose `year`/`month` match the request's
 * `?year=`/`?month=` query, falling back to the first seeded record when nothing
 * matches (so a single-seed test works regardless of the query it passes).
 *
 * ```php
 * SupportManager::fake()->seed(
 *     EstimatedPayFactory::new()->forMonth(2026, 6),
 *     EstimatedPayFactory::new()->forMonth(2026, 7),
 * );
 * SupportManager::estimatedPay()->show(['year' => 2026, 'month' => 7]);
 * ```
 */
final class EstimatedPayFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'payroll/estimated-pay';
    }

    public function definition(int $index): array
    {
        return [
            'year' => 2026,
            'month' => 6,
            'month_label' => 'June 2026',
            'hours' => [
                'total_value' => 150.0,
                'total_cost' => 2250.0,
                'breakdown' => [
                    ['type' => 'Contracted Hours', 'total_payroll_value' => 150.0, 'total_cost' => 2250.0],
                ],
            ],
            'sleeps' => [
                'total_value' => 4.0,
                'total_cost' => 200.0,
                'breakdown' => [
                    ['type' => 'Contracted Sleeping Night', 'total_payroll_value' => 4.0, 'total_cost' => 200.0],
                ],
            ],
            'enhancements' => [
                ['type' => 'Bank Holiday', 'total_value' => 8.0, 'total_cost' => 120.0],
            ],
            'grand_total' => ['total_value' => 162.0, 'total_cost' => 2570.0],
            'gross_pay' => 2570.0,
            'net_pay' => 2100.0,
            'deductions_total' => 470.0,
            'deductions' => [
                'income_tax' => 300.5,
                'national_insurance' => 120.0,
                'pension' => 40.0,
                'student_loan' => 9.5,
                'postgraduate_loan' => 0.0,
                'corrections' => [
                    ['description' => 'Prior-month underpayment', 'amount' => 12.0],
                ],
                'earnings_orders' => [
                    ['description' => 'Attachment of Earnings', 'amount' => 25.0],
                ],
            ],
        ];
    }

    /**
     * Target a specific year/month (also setting a matching `month_label`), so
     * month navigation resolves distinct per-month projections.
     */
    public function forMonth(int $year, int $month): static
    {
        $label = self::monthLabel($year, $month);

        return $this->state([
            'year' => $year,
            'month' => $month,
            'month_label' => $label,
        ]);
    }

    /**
     * Seed the bare worked-hours projection with no net-pay breakdown.
     */
    public function withoutNetPay(): static
    {
        return $this->state([
            'gross_pay' => null,
            'net_pay' => null,
            'deductions_total' => null,
            'deductions' => null,
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            // Singleton show with month navigation: pick the projection matching
            // ?year=&month=, else fall back to the first seeded record.
            FakeRoute::get('payroll/estimated-pay', static function (RecordStore $store, array $matches, Request $request): mixed {
                $records = $store->all('payroll/estimated-pay');

                if ($records === []) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                $match = null;

                $year = $query['year'] ?? null;
                $month = $query['month'] ?? null;

                if (is_string($year) && is_string($month)) {
                    foreach ($records as $record) {
                        if (
                            (string) ($record['year'] ?? '') === $year
                            && (string) ($record['month'] ?? '') === $month
                        ) {
                            $match = $record;
                            break;
                        }
                    }
                }

                return Http::response(Json::item(null, $match ?? $records[0]));
            }),
        ];
    }

    private static function monthLabel(int $year, int $month): string
    {
        $name = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ][$month] ?? (string) $month;

        return "{$name} {$year}";
    }
}

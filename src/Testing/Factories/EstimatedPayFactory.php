<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake estimated-pay projection — a flat singleton (`type()` is null, so the
 * response is a bare `{data: {...}}`, not a JSON:API document).
 *
 * The resource's `show()` reads `response.json('data')` directly, which the
 * generic flat-singleton GET serves; seed one record and ignore the
 * year/month/user_id query params (the fake returns the seeded projection
 * regardless).
 *
 * ```php
 * SupportManager::fake()->seed(EstimatedPayFactory::new());
 * SupportManager::estimatedPay()->show(['year' => 2026, 'month' => 6]);
 * ```
 */
final class EstimatedPayFactory extends Factory
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
            'hours' => ['total_value' => 150.0, 'total_cost' => 2250.0],
            'sleeps' => ['total_value' => 4.0, 'total_cost' => 200.0],
            'enhancements' => [
                ['type' => 'Bank Holiday', 'total_value' => 8.0, 'total_cost' => 120.0],
            ],
            'grand_total' => ['total_value' => 162.0, 'total_cost' => 2570.0],
            'gross_pay' => 2570.0,
            'net_pay' => 2100.0,
            'deductions_total' => 470.0,
            'deductions' => ['income_tax' => 300.5, 'national_insurance' => 170.0],
        ];
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
}

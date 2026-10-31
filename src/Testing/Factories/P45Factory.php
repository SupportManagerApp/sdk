<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;

/**
 * Fake leaver P45 documents.
 *
 * A JSON:API collection (type `p45s`): the generic list comes for free; this
 * factory adds `find()` and the raw PDF `{id}/download`, both of which mark the
 * P45 `viewed` as the platform does.
 *
 * ```php
 * SupportManager::fake()->seed(P45Factory::new()->count(2));
 * SupportManager::p45s()->download(1);
 * ```
 */
final class P45Factory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'payroll/p45s';
    }

    public function type(): string
    {
        return 'p45s';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => $this->teamId(),
            'employee_id' => 7,
            'employee' => ['id' => 7, 'name' => "Employee {$number}"],
            'tax_year' => '2025-26',
            'leaving_date' => '2026-01-31',
            'tax_code' => '1257L',
            'week1_month1' => false,
            'month_number' => 10,
            'student_loan_deductions_continue' => false,
            'total_pay_to_date' => 25000.0,
            'total_tax_to_date' => 2486.0,
            'this_employment_pay' => null,
            'this_employment_tax' => null,
            'published_at' => '2026-02-01T00:00:00.000000Z',
            'viewed' => false,
            'created_at' => '2026-02-01T09:00:00.000000Z',
            'updated_at' => '2026-02-01T09:00:00.000000Z',
        ];
    }

    /**
     * Mark this P45 as already viewed by the employee.
     */
    public function viewed(): static
    {
        return $this->state(['viewed' => true]);
    }

    /**
     * Show this employment's pay and tax separately from the year-to-date totals.
     */
    public function withThisEmployment(): static
    {
        return $this->state([
            'this_employment_pay' => 18000.0,
            'this_employment_tax' => 1600.0,
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            // Opening or downloading marks the P45 viewed, as the platform does.
            FakeRoute::action('GET', 'payroll/p45s/{id}', static fn (): array => ['viewed' => true]),
            FakeRoute::download('payroll/p45s/{id}/download', '%PDF-1.4 p45-bytes', changes: ['viewed' => true]),
        ];
    }
}

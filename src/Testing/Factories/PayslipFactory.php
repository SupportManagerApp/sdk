<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake monthly payslips.
 *
 * A JSON:API collection (type `payslips`): generic list/find come for free; this
 * factory adds the non-RESTful {@see latest()} upcoming payslip and the raw PDF
 * `{id}/download`. The nested payment/deduction/total line rows live inline in
 * {@see definition()} and {@see withLines()}.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     PayslipFactory::new()->count(3),
 *     PayslipFactory::new()->latest(),
 * );
 * SupportManager::payslips()->latest();
 * ```
 */
final class PayslipFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'payroll/payslips';
    }

    public function type(): string
    {
        return 'payslips';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => 7,
            'user' => ['id' => 7, 'name' => "Employee {$number}"],
            'process_date' => '2026-06-30T00:00:00Z',
            'total_gross' => 2500.0,
            'net_pay_formatted' => '1,950.00',
            'deductions_total_formatted' => '550.00',
            'status' => 'published',
            'published_at' => '2026-06-25T09:00:00Z',
            'first_opened_at' => null,
            'payments' => [],
            'deductions' => [],
            'period_totals' => [],
            'ytd_totals' => [],
            'created_at' => '2026-06-01T09:00:00Z',
            'updated_at' => '2026-06-25T09:00:00Z',
        ];
    }

    /**
     * Mark this payslip as the one `latest()` returns.
     */
    public function latest(): static
    {
        return $this->state(['is_latest' => true]);
    }

    /**
     * Seed the side-loaded line-item breakdowns a single show() returns.
     */
    public function withLines(): static
    {
        return $this->state([
            'payments' => [['description' => 'Basic Pay', 'hours' => 160.0, 'rate' => 15.0, 'total' => 2400.0]],
            'deductions' => [['description' => 'PAYE Tax', 'total' => 320.0]],
            'period_totals' => [['description' => 'Gross', 'amount' => 2400.0]],
            'ytd_totals' => [['description' => 'Gross YTD', 'amount' => 7200.0]],
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('payroll/payslips/latest', static function (RecordStore $store): mixed {
                foreach ($store->all('payroll/payslips') as $record) {
                    if ($record['is_latest'] ?? false) {
                        return Http::response(Json::item('payslips', $record));
                    }
                }

                return Http::response(['data' => null]);
            }),

            FakeRoute::get('payroll/payslips/{id}/download', static function (RecordStore $store, array $matches): mixed {
                if ($store->find('payroll/payslips', $matches[1]) === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                return Http::response('%PDF-1.4 payslip-bytes', 200, ['Content-Type' => 'application/pdf']);
            }),
        ];
    }
}

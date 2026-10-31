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
    private const array SHOW_ONLY_KEYS = ['payments', 'deductions', 'period_totals', 'ytd_totals', 'employer'];

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
            'team_id' => $this->teamId(),
            'user_id' => 7,
            'user' => ['id' => 7, 'name' => "Employee {$number}"],
            'process_date' => '2026-06-30T00:00:00Z',
            'pay_period_start' => '2026-06-01',
            'pay_period_end' => '2026-06-30',
            'tax_code' => '1257L',
            'ni_letter' => 'A',
            'total_gross' => 2500.0,
            'taxable_gross' => 2500.0,
            'niable_gross' => 2500.0,
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
        return $this->state(['_latest' => true]);
    }

    /**
     * Seed the employer details a single show() returns.
     */
    public function withEmployer(): static
    {
        return $this->state(['employer' => ['name' => 'Jane Smith', 'paye_reference' => '123/AB45678']]);
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
            // The platform's index eager-loads only the user, so the line-item
            // relations and the show-only employer block are absent
            // (PayslipController::index, PayslipResource::whenLoaded()).
            FakeRoute::get('payroll/payslips', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $rows = array_map(
                    static fn (array $record): array => array_diff_key($record, array_flip(self::SHOW_ONLY_KEYS)),
                    $fake->filter($store->all('payroll/payslips'), $request),
                );

                return $fake->paginate('payroll/payslips', 'payslips', $rows, $request, ['team_id' => $fake->teamId()]);
            }),

            // The `_latest` marker is fake bookkeeping, which Json strips.
            FakeRoute::get('payroll/payslips/latest', static function (RecordStore $store): mixed {
                foreach ($store->all('payroll/payslips') as $record) {
                    if ($record['_latest'] ?? false) {
                        return Http::response(Json::item('payslips', $record));
                    }
                }

                return Http::response(Json::flat(null));
            }),

            FakeRoute::download('payroll/payslips/{id}/download', '%PDF-1.4 payslip-bytes'),
        ];
    }
}

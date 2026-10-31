<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\Payslip;
use SupportManager\Sdk\Http\Download;

function payrollPayslipPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'payslips',
            'id' => (string) $page,
            'attributes' => [
                'user_id' => 7,
                'user' => ['id' => 7, 'name' => "Employee {$page}"],
                'process_date' => '2026-06-30T00:00:00Z',
                'total_gross' => 2500.0,
                'net_pay_formatted' => '1,950.00',
                'status' => 'published',
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/payroll/payslips?page='.($page + 1) : null],
    ];
}

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    fakeApi(['payroll/payslips*' => Http::response(payrollPayslipPage(1, 1))]);

    sdk()->payslips()->query()
        ->filter('year', 2026)
        ->sort('-process_date')
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[year]=2026')
            && str_contains($url, 'sort=-process_date')
            && str_contains($url, 'per_page=15')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates payslip DTOs and exposes pagination metadata', function () {
    fakeApi(['payroll/payslips*' => Http::response(payrollPayslipPage(1, 3))]);

    $page = sdk()->payslips()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(Payslip::class)
        ->and($page->items()[0]->userName)->toBe('Employee 1')
        ->and($page->items()[0]->totalGross)->toBe(2500.0)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a payslip by id with nested payment and deduction lines', function () {
    fakeApi([
        'payroll/payslips/9' => [
            'data' => [
                'type' => 'payslips',
                'id' => '9',
                'attributes' => [
                    'status' => 'published',
                    'payments' => [['description' => 'Basic Pay', 'hours' => 160.0, 'rate' => 15.0, 'total' => 2400.0]],
                    'deductions' => [['description' => 'PAYE Tax', 'total' => 320.0]],
                    'period_totals' => [['description' => 'Gross', 'amount' => 2400.0]],
                    'ytd_totals' => [['description' => 'Gross YTD', 'amount' => 7200.0]],
                ],
            ],
        ],
    ]);

    $payslip = sdk()->payslips()->find(9);

    expect($payslip)->toBeInstanceOf(Payslip::class)
        ->and($payslip->id)->toBe('9')
        ->and($payslip->payments)->toHaveCount(1)
        ->and($payslip->payments[0]->description)->toBe('Basic Pay')
        ->and($payslip->payments[0]->hours)->toBe(160.0)
        ->and($payslip->deductions[0]->total)->toBe(320.0)
        ->and($payslip->periodTotals[0]->total)->toBe(2400.0)
        ->and($payslip->ytdTotals[0]->total)->toBe(7200.0);
});

it('finds a payslip with its pay period, tax details and employer', function () {
    fakeApi([
        'payroll/payslips/9' => [
            'data' => [
                'type' => 'payslips',
                'id' => '9',
                'attributes' => [
                    'status' => 'published',
                    'pay_period_start' => '2026-06-01',
                    'pay_period_end' => '2026-06-30',
                    'tax_code' => '1257L',
                    'ni_letter' => 'A',
                    'total_gross' => 2500.0,
                    'taxable_gross' => 2400.0,
                    'niable_gross' => 2450.0,
                    'first_opened_at' => '2026-07-01T09:00:00.000000Z',
                    'employer' => ['name' => 'Jane Smith', 'paye_reference' => '123/AB45678'],
                ],
            ],
        ],
    ]);

    $payslip = sdk()->payslips()->find(9);

    expect($payslip->payPeriodStart)->toBe('2026-06-01')
        ->and($payslip->payPeriodEnd)->toBe('2026-06-30')
        ->and($payslip->taxCode)->toBe('1257L')
        ->and($payslip->niLetter)->toBe('A')
        ->and($payslip->taxableGross)->toBe(2400.0)
        ->and($payslip->niableGross)->toBe(2450.0)
        ->and($payslip->firstOpenedAt)->toBe('2026-07-01T09:00:00.000000Z')
        ->and($payslip->employerName)->toBe('Jane Smith')
        ->and($payslip->employerPayeReference)->toBe('123/AB45678');
});

it('falls back to the total gross and leaves the employer null when absent', function () {
    fakeApi([
        'payroll/payslips/9' => [
            'data' => ['type' => 'payslips', 'id' => '9', 'attributes' => ['status' => 'published', 'total_gross' => 2500.0]],
        ],
    ]);

    $payslip = sdk()->payslips()->find(9);

    expect($payslip->taxableGross)->toBe(2500.0)
        ->and($payslip->niableGross)->toBe(2500.0)
        ->and($payslip->employerName)->toBeNull()
        ->and($payslip->payPeriodStart)->toBeNull();
});

it('returns null when finding a missing payslip (404)', function () {
    fakeApi(['payroll/payslips/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->payslips()->find(999))->toBeNull();
});

it('fetches the latest upcoming payslip', function () {
    fakeApi([
        'payroll/payslips/latest' => [
            'data' => ['type' => 'payslips', 'id' => '12', 'attributes' => ['status' => 'published', 'net_pay_formatted' => '2,100.00']],
        ],
    ]);

    $payslip = sdk()->payslips()->latest();

    expect($payslip)->toBeInstanceOf(Payslip::class)
        ->and($payslip->id)->toBe('12')
        ->and($payslip->netPayFormatted)->toBe('2,100.00');
});

it('returns null when there is no latest payslip', function () {
    fakeApi(['payroll/payslips/latest' => ['data' => null]]);

    expect(sdk()->payslips()->latest())->toBeNull();
});

it('downloads a payslip PDF as a raw response', function () {
    fakeApi(['payroll/payslips/9/download' => Http::response('%PDF-1.4 payslip-bytes', 200, ['Content-Type' => 'application/pdf'])]);

    $response = sdk()->payslips()->download(9);

    expect($response)->toBeInstanceOf(Download::class)
        ->and($response->body())->toBe('%PDF-1.4 payslip-bytes')
        ->and($response->mimeType())->toBe('application/pdf');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\P45;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Resources\Payroll\P45sResource;
use SupportManager\Sdk\Testing\Factories\P45Factory;

function payrollP45s(): P45sResource
{
    return SupportManager::p45s();
}

function payrollP45Page(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'p45s',
            'id' => (string) $page,
            'attributes' => [
                'team_id' => 42,
                'employee_id' => 7,
                'employee' => ['id' => 7, 'name' => "Employee {$page}"],
                'tax_year' => '2025-26',
                'leaving_date' => '2026-01-31',
                'tax_code' => '1257L',
                'week1_month1' => true,
                'month_number' => 10,
                'student_loan_deductions_continue' => false,
                'total_pay_to_date' => 25000.0,
                'total_tax_to_date' => 2486.0,
                'this_employment_pay' => null,
                'this_employment_tax' => null,
                'published_at' => '2026-02-01T00:00:00.000000Z',
                'viewed' => false,
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/payroll/p45s?page='.($page + 1) : null],
    ];
}

// ── HTTP layer ──────────────────────────────────────────────────────────────

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/payroll/p45s*' => Http::response(payrollP45Page(1, 1))]);
    bootTenantSession();

    payrollP45s()->query()->filter('tax_year', '2025-26')->sort('-leaving_date')->perPage(20)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[tax_year]=2025-26')
            && str_contains($url, 'sort=-leaving_date')
            && str_contains($url, 'per_page=20')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates P45 DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/payroll/p45s*' => Http::response(payrollP45Page(1, 2))]);
    bootTenantSession();

    $page = payrollP45s()->query()->get();
    $p45 = $page->items()[0];

    expect($p45)->toBeInstanceOf(P45::class)
        ->and($p45->teamId)->toBe(42)
        ->and($p45->employeeName)->toBe('Employee 1')
        ->and($p45->taxYear)->toBe('2025-26')
        ->and($p45->leavingDate)->toBe('2026-01-31')
        ->and($p45->taxCode)->toBe('1257L')
        ->and($p45->week1Month1)->toBeTrue()
        ->and($p45->monthNumber)->toBe(10)
        ->and($p45->totalPayToDate)->toBe(25000.0)
        ->and($p45->totalTaxToDate)->toBe(2486.0)
        ->and($p45->thisEmploymentPay)->toBeNull()
        ->and($p45->thisEmploymentTax)->toBeNull()
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a P45 by id', function () {
    Http::fake([
        'api.test/api/v1/payroll/p45s/4' => Http::response([
            'data' => [
                'type' => 'p45s',
                'id' => '4',
                'attributes' => [
                    'leaving_date' => '2026-01-31',
                    'total_pay_to_date' => 25000.0,
                    'total_tax_to_date' => 2486.0,
                    'this_employment_pay' => 18000.0,
                    'this_employment_tax' => 1600.0,
                    'student_loan_deductions_continue' => true,
                    'viewed' => true,
                ],
            ],
        ]),
    ]);
    bootTenantSession();

    $p45 = payrollP45s()->find(4);

    expect($p45)->toBeInstanceOf(P45::class)
        ->and($p45->id)->toBe('4')
        ->and($p45->viewed)->toBeTrue()
        ->and($p45->studentLoanDeductionsContinue)->toBeTrue()
        ->and($p45->thisEmploymentPay)->toBe(18000.0)
        ->and($p45->thisEmploymentTax)->toBe(1600.0);
});

it('returns null when finding a missing P45 (404)', function () {
    Http::fake(['api.test/api/v1/payroll/p45s/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(payrollP45s()->find(999))->toBeNull();
});

it('maps a 422 on the p45s path to a validation exception', function () {
    Http::fake(['api.test/api/v1/payroll/p45s*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter' => ['The tax_year filter is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        payrollP45s()->query()->filter('tax_year', 'nope')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('filter'))->toBe('The tax_year filter is invalid.');
    }
});

it('downloads a P45 PDF as a raw response with a team header', function () {
    Http::fake(['api.test/api/v1/payroll/p45s/4/download' => Http::response('%PDF-1.4 p45-bytes', 200, ['Content-Type' => 'application/pdf'])]);
    bootTenantSession();

    $response = payrollP45s()->download(4);

    expect($response)->toBeInstanceOf(Download::class)
        ->and($response->body())->toBe('%PDF-1.4 p45-bytes');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

// ── SupportManager::fake() ──────────────────────────────────────────────────

it('lists, filters and finds seeded P45s through the fake', function () {
    SupportManager::fake()->seed(
        P45Factory::new()->viewed()->count(2),
        P45Factory::new()->withThisEmployment()->state(['tax_year' => '2024-25']),
    );

    $page = payrollP45s()->all();
    $filtered = payrollP45s()->query()->filter('tax_year', '2024-25')->get();

    $p45 = payrollP45s()->find(3);
    assert($p45 instanceof P45);

    expect($page->total())->toBe(3)
        ->and($page->items()[0])->toBeInstanceOf(P45::class)
        ->and($page->items()[0]->viewed)->toBeTrue()
        ->and($page->items()[0]->leavingDate)->toBe('2026-01-31')
        ->and($filtered->items())->toHaveCount(1)
        ->and($p45->thisEmploymentPay)->toBe(18000.0)
        ->and(payrollP45s()->find(999))->toBeNull();
});

it('downloads a seeded P45 PDF through the fake', function () {
    SupportManager::fake()->seed(P45Factory::new()->count(1));

    $response = payrollP45s()->download(1);

    expect($response->body())->toBe('%PDF-1.4 p45-bytes')
        ->and($response->mimeType())->toBe('application/pdf')
        ->and(fn () => payrollP45s()->download(999))->toThrow(SupportManagerApiException::class);
});

it('marks a seeded P45 viewed when it is opened or downloaded', function () {
    SupportManager::fake()->seed(P45Factory::new()->count(2));

    expect(payrollP45s()->all()->items()[0]->viewed)->toBeFalse()
        ->and(payrollP45s()->find(1)?->viewed)->toBeTrue();

    payrollP45s()->download(2);

    expect(payrollP45s()->all()->items()[1]->viewed)->toBeTrue();
});

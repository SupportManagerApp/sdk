<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\P60;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Resources\Payroll\P60sResource;

function payrollP60s(): P60sResource
{
    return app(P60sResource::class);
}

function payrollP60Page(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'p60s',
            'id' => (string) $page,
            'attributes' => [
                'employee_id' => 7,
                'employee' => ['id' => 7, 'name' => "Employee {$page}"],
                'tax_year' => '2025/26',
                'payroll_number' => 'PR-100',
                'download_available' => true,
                'viewed' => false,
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/payroll/p60s?page='.($page + 1) : null],
    ];
}

it('encodes filter and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/payroll/p60s*' => Http::response(payrollP60Page(1, 1))]);
    bootTenantSession();

    payrollP60s()->query()->filter('tax_year', '2025/26')->perPage(20)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[tax_year]=2025/26')
            && str_contains($url, 'per_page=20')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates P60 DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/payroll/p60s*' => Http::response(payrollP60Page(1, 2))]);
    bootTenantSession();

    $page = payrollP60s()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(P60::class)
        ->and($page->items()[0]->taxYear)->toBe('2025/26')
        ->and($page->items()[0]->employeeName)->toBe('Employee 1')
        ->and($page->items()[0]->downloadAvailable)->toBeTrue()
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a P60 by id with nested detail rows', function () {
    Http::fake([
        'api.test/api/v1/payroll/p60s/4' => Http::response([
            'data' => [
                'type' => 'p60s',
                'id' => '4',
                'attributes' => [
                    'tax_year' => '2025/26',
                    'download_available' => true,
                    'viewed' => true,
                    'pay_details' => [['id' => 1, 'description' => 'Total Pay', 'amount' => 30000.0]],
                    'ni_contributions' => [['id' => 1, 'ni_letter' => 'A', 'employee_contributions' => 1200.0]],
                ],
            ],
        ]),
    ]);
    bootTenantSession();

    $p60 = payrollP60s()->find(4);

    expect($p60)->toBeInstanceOf(P60::class)
        ->and($p60->id)->toBe('4')
        ->and($p60->viewed)->toBeTrue()
        ->and($p60->payDetails)->toHaveCount(1)
        ->and($p60->payDetails[0]['description'])->toBe('Total Pay')
        ->and($p60->niContributions[0]['ni_letter'])->toBe('A');
});

it('returns null when finding a missing P60 (404)', function () {
    Http::fake(['api.test/api/v1/payroll/p60s/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(payrollP60s()->find(999))->toBeNull();
});

it('maps a 422 on the p60s path to a validation exception', function () {
    // The only 422 this index returns is SetApiTenant's missing/invalid team
    // response, which carries a message and no field errors.
    Http::fake(['api.test/api/v1/payroll/p60s*' => Http::response([
        'message' => 'Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.',
    ], 422)]);
    bootTenantSession();

    try {
        payrollP60s()->query()->filter('tax_year', 'nope')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->getMessage())->toContain('Invalid or missing team')
            ->and($e->errors)->toBe([]);
    }
});

it('downloads a P60 PDF as a raw response', function () {
    Http::fake(['api.test/api/v1/payroll/p60s/4/download' => Http::response('%PDF-1.4 p60-bytes', 200, ['Content-Type' => 'application/pdf'])]);
    bootTenantSession();

    $response = payrollP60s()->download(4);

    expect($response)->toBeInstanceOf(Download::class)
        ->and($response->body())->toBe('%PDF-1.4 p60-bytes');
});

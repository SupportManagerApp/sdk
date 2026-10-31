<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\P60;
use SupportManager\Sdk\Http\Download;

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
    fakeApi(['payroll/p60s*' => Http::response(payrollP60Page(1, 1))]);

    sdk()->p60s()->query()->filter('tax_year', '2025/26')->perPage(20)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[tax_year]=2025/26')
            && str_contains($url, 'per_page=20')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates P60 DTOs and exposes pagination metadata', function () {
    fakeApi(['payroll/p60s*' => Http::response(payrollP60Page(1, 2))]);

    $page = sdk()->p60s()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(P60::class)
        ->and($page->items()[0]->taxYear)->toBe('2025/26')
        ->and($page->items()[0]->employeeName)->toBe('Employee 1')
        ->and($page->items()[0]->downloadAvailable)->toBeTrue()
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a P60 by id with nested detail rows', function () {
    fakeApi([
        'payroll/p60s/4' => [
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
        ],
    ]);

    $p60 = sdk()->p60s()->find(4);

    expect($p60)->toBeInstanceOf(P60::class)
        ->and($p60->id)->toBe('4')
        ->and($p60->viewed)->toBeTrue()
        ->and($p60->payDetails)->toHaveCount(1)
        ->and($p60->payDetails[0]['description'])->toBe('Total Pay')
        ->and($p60->niContributions[0]['ni_letter'])->toBe('A');
});

it('returns null when finding a missing P60 (404)', function () {
    fakeApi(['payroll/p60s/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->p60s()->find(999))->toBeNull();
});

it('downloads a P60 PDF as a raw response', function () {
    fakeApi(['payroll/p60s/4/download' => Http::response('%PDF-1.4 p60-bytes', 200, ['Content-Type' => 'application/pdf'])]);

    $response = sdk()->p60s()->download(4);

    expect($response)->toBeInstanceOf(Download::class)
        ->and($response->body())->toBe('%PDF-1.4 p60-bytes');
});

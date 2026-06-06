<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\PayRun;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Payroll\PayRunsResource;

function payrollPayRuns(): PayRunsResource
{
    return app(PayRunsResource::class);
}

function payrollPayRunBody(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'month_key' => '2026-0'.$page,
            'month_label' => "Month {$page}",
            'process_date' => '2026-0'.$page.'-30',
            'payslip_count' => 10,
            'published_count' => 10,
            'status' => 'published',
            'total_gross' => 25000.0,
            'total_net' => 19500.0,
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage, 'team_id' => 42],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/payroll/pay-runs?page='.($page + 1) : null],
    ];
}

it('hydrates pay-run summaries and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/payroll/pay-runs*' => Http::response(payrollPayRunBody(1, 3))]);
    bootTenantSession();

    $page = payrollPayRuns()->all();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(PayRun::class)
        ->and($page->items()[0]->monthKey)->toBe('2026-01')
        ->and($page->items()[0]->status)->toBe('published')
        ->and($page->items()[0]->totalGross)->toBe(25000.0)
        ->and($page->items()[0]->totalNet)->toBe(19500.0)
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('walks to the next page of pay runs', function () {
    Http::fake([
        'api.test/api/v1/payroll/pay-runs*' => Http::sequence()
            ->push(payrollPayRunBody(1, 2))
            ->push(payrollPayRunBody(2, 2)),
    ]);
    bootTenantSession();

    $next = payrollPayRuns()->all()->next();

    expect($next)->not->toBeNull()
        ->and($next->currentPage())->toBe(2)
        ->and($next->items()[0]->monthKey)->toBe('2026-02')
        ->and($next->hasMorePages())->toBeFalse();

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'page=2'));
});

it('maps a server error on the pay-runs path to an api exception', function () {
    Http::fake(['api.test/api/v1/payroll/pay-runs*' => Http::response('<html>boom</html>', 500)]);
    bootTenantSession();

    expect(fn () => payrollPayRuns()->all())
        ->toThrow(SupportManagerApiException::class);
});

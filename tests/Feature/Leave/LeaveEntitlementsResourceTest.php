<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Resources\Leave\LeaveEntitlementsResource;

function leaveEntitlements(): LeaveEntitlementsResource
{
    return app(LeaveEntitlementsResource::class);
}

function leaveEntitlementPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'leave-entitlements',
            'id' => (string) $page,
            'attributes' => [
                'user_id' => 3,
                'working_pattern_id' => 2,
                'working_pattern' => ['id' => 2, 'name' => 'Full Time'],
                'effective_from' => '2026-01-01',
                'effective_until' => '2026-12-31',
                'hours_holiday' => 224.0,
                'sleeping_nights_holiday' => 0.0,
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/leave/entitlements?page='.($page + 1) : null],
    ];
}

it('queries entitlements with filters and hydrates DTOs', function () {
    Http::fake(['api.test/api/v1/leave/entitlements*' => Http::response(leaveEntitlementPage(1, 1))]);
    bootTenantSession();

    $page = leaveEntitlements()->query()->filter('user_id', 3)->sort('-effective_from')->get();

    expect($page->items()[0])->toBeInstanceOf(LeaveEntitlement::class)
        ->and($page->items()[0]->hoursHoliday)->toBe(224.0)
        ->and($page->items()[0]->workingPatternName)->toBe('Full Time')
        ->and($page->items()[0]->effectiveFrom)->toBe('2026-01-01');

    Http::assertSent(function ($r) {
        $url = urldecode($r->url());

        return str_contains($url, 'filter[user_id]=3')
            && str_contains($url, 'sort=-effective_from')
            && $r->hasHeader('X-Team-ID', '42');
    });
});

it('paginates entitlements', function () {
    Http::fake(['api.test/api/v1/leave/entitlements*' => Http::response(leaveEntitlementPage(1, 2))]);
    bootTenantSession();

    $page = leaveEntitlements()->all();

    expect($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(2)
        ->and($page->hasMorePages())->toBeTrue();
});

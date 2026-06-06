<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\LeaveBalance;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Leave\LeaveBalanceResource;

function leaveBalance(): LeaveBalanceResource
{
    return app(LeaveBalanceResource::class);
}

function leaveBalanceBody(): array
{
    return [
        'data' => [
            'year' => 2026,
            'hours' => ['entitlement' => 224.0, 'pending' => 8.0, 'approved' => 16.0, 'remaining' => 200.0, 'percentages' => ['used' => 7]],
            'sleeps' => ['entitlement' => 10.0, 'pending' => 1.0, 'approved' => 2.0, 'remaining' => 7.0, 'percentages' => null],
        ],
        'meta' => ['team_id' => 42],
    ];
}

it('shows the current user balance', function () {
    Http::fake(['api.test/api/v1/leave/balance' => Http::response(leaveBalanceBody())]);
    bootTenantSession();

    $balance = leaveBalance()->show();

    expect($balance)->toBeInstanceOf(LeaveBalance::class)
        ->and($balance->year)->toBe(2026)
        ->and($balance->hours->entitlement)->toBe(224.0)
        ->and($balance->hours->remaining)->toBe(200.0)
        ->and($balance->hours->percentages)->toBe(['used' => 7])
        ->and($balance->sleeps->remaining)->toBe(7.0)
        ->and($balance->sleeps->percentages)->toBeNull();

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('shows another user balance', function () {
    Http::fake(['api.test/api/v1/leave/balance/3' => Http::response(leaveBalanceBody())]);
    bootTenantSession();

    expect(leaveBalance()->forUser(3)->hours->approved)->toBe(16.0);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'leave/balance/3'));
});

it('calculates the impact of a prospective request', function () {
    Http::fake(['api.test/api/v1/leave/balance/calculate' => Http::response([
        'data' => [
            'year' => 2026,
            'hours' => ['entitlement' => 224.0, 'pending' => 0.0, 'approved' => 0.0, 'remaining' => 216.0],
            'sleeps' => ['entitlement' => 0.0, 'pending' => 0.0, 'approved' => 0.0, 'remaining' => 0.0],
        ],
    ])]);
    bootTenantSession();

    $balance = leaveBalance()->calculate(['start_date' => '2026-07-01', 'type' => 'Whole Days']);

    expect($balance->hours->remaining)->toBe(216.0);
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['type'] === 'Whole Days');
});

it('maps a 422 on calculate to a validation exception', function () {
    Http::fake(['api.test/api/v1/leave/balance/calculate' => Http::response([
        'message' => 'invalid',
        'errors' => ['start_date' => ['The start date is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        leaveBalance()->calculate([]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('start_date'))->toBe('The start date is required.');
    }
});

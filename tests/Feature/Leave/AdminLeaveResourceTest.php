<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Data\Leave\LeaveRequest;
use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Leave\AdminLeaveResource;

function leaveAdmin(): AdminLeaveResource
{
    return app(AdminLeaveResource::class);
}

it('lists pending requests from a flat data block', function () {
    Http::fake(['api.test/api/v1/leave/admin/requests/pending' => Http::response([
        'data' => [
            [
                'id' => 7,
                'user' => ['id' => 3, 'name' => 'Ada'],
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-05',
                'type' => 'Whole Days',
                'status' => 'Pending',
                'hours' => 30.0,
                'sleeps' => 0.0,
                'requester_comments' => 'Trip',
            ],
        ],
        'meta' => ['current_page' => 1, 'total' => 1, 'team_id' => 42],
    ])]);
    bootTenantSession();

    $pending = leaveAdmin()->pendingRequests();

    expect($pending)->toHaveCount(1)
        ->and($pending[0])->toBeInstanceOf(LeaveRequest::class)
        ->and($pending[0]->id)->toBe('7')
        ->and($pending[0]->userName)->toBe('Ada')
        ->and($pending[0]->status)->toBe(LeaveStatus::Pending);

    Http::assertSent(fn ($r) => $r->hasHeader('X-Team-ID', '42'));
});

it('bulk approves requests', function () {
    Http::fake(['api.test/api/v1/leave/admin/requests/bulk-approve' => Http::response([
        'message' => '2 request(s) approved successfully', 'approved_count' => 2,
    ])]);
    bootTenantSession();

    leaveAdmin()->bulkApprove([1, 2]);

    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['request_ids'] === [1, 2]);
});

it('bulk rejects requests with a reason', function () {
    Http::fake(['api.test/api/v1/leave/admin/requests/bulk-reject' => Http::response([
        'message' => '1 request(s) rejected successfully', 'rejected_count' => 1,
    ])]);
    bootTenantSession();

    leaveAdmin()->bulkReject([3], 'Short staffed');

    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['request_ids'] === [3] && $r['reason'] === 'Short staffed');
});

it('returns team balances as raw arrays', function () {
    Http::fake(['api.test/api/v1/leave/admin/balances' => Http::response([
        'data' => [
            ['user' => ['id' => 3, 'name' => 'Ada'], 'leave_year' => 2026, 'remaining' => ['hours' => 200.0, 'sleeps' => 5.0]],
        ],
        'meta' => ['current_page' => 1, 'total' => 1],
    ])]);
    bootTenantSession();

    $balances = leaveAdmin()->balances();

    expect($balances)->toHaveCount(1)
        ->and($balances[0]['user']['name'])->toBe('Ada')
        ->and((float) $balances[0]['remaining']['hours'])->toBe(200.0);
});

it('returns a single user balance breakdown', function () {
    Http::fake(['api.test/api/v1/leave/admin/balances/3' => Http::response([
        'data' => ['user' => ['id' => 3, 'name' => 'Ada'], 'leave_year' => 2026],
    ])]);
    bootTenantSession();

    expect(leaveAdmin()->balanceFor(3)['user']['name'])->toBe('Ada');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'leave/admin/balances/3'));
});

it('lists admin adjustments (JSON:API) as DTOs', function () {
    Http::fake(['api.test/api/v1/leave/admin/adjustments' => Http::response([
        'data' => [[
            'type' => 'manual-adjustments',
            'id' => '5',
            'attributes' => ['user_id' => 3, 'leave_year' => 2026, 'hours_adjustment' => 4.0, 'reason' => 'Comp'],
        ]],
        'meta' => ['current_page' => 1, 'total' => 1],
    ])]);
    bootTenantSession();

    $adjustments = leaveAdmin()->adjustments();

    expect($adjustments[0])->toBeInstanceOf(ManualAdjustment::class)
        ->and($adjustments[0]->reason)->toBe('Comp');
});

it('lists admin entitlements (JSON:API) as DTOs', function () {
    Http::fake(['api.test/api/v1/leave/admin/entitlements' => Http::response([
        'data' => [[
            'type' => 'leave-entitlements',
            'id' => '6',
            'attributes' => ['user_id' => 3, 'hours_holiday' => 224.0, 'sleeping_nights_holiday' => 0.0],
        ]],
        'meta' => ['current_page' => 1, 'total' => 1],
    ])]);
    bootTenantSession();

    $entitlements = leaveAdmin()->entitlements();

    expect($entitlements[0])->toBeInstanceOf(LeaveEntitlement::class)
        ->and($entitlements[0]->hoursHoliday)->toBe(224.0);
});

it('creates an admin entitlement', function () {
    Http::fake(['api.test/api/v1/leave/admin/entitlements' => Http::response([
        'data' => ['type' => 'leave-entitlements', 'id' => '6', 'attributes' => ['hours_holiday' => 100.0]],
    ], 201)]);
    bootTenantSession();

    $entitlement = leaveAdmin()->createEntitlement(['user_id' => 3, 'hours_holiday' => 100.0]);

    expect($entitlement->id)->toBe('6')
        ->and($entitlement->hoursHoliday)->toBe(100.0);
    Http::assertSent(fn ($r) => $r->method() === 'POST');
});

it('updates an admin entitlement via PUT', function () {
    Http::fake(['api.test/api/v1/leave/admin/entitlements/6' => Http::response([
        'data' => ['type' => 'leave-entitlements', 'id' => '6', 'attributes' => ['hours_holiday' => 120.0]],
    ])]);
    bootTenantSession();

    expect(leaveAdmin()->updateEntitlement(6, ['hours_holiday' => 120.0])->hoursHoliday)->toBe(120.0);
    Http::assertSent(fn ($r) => $r->method() === 'PUT');
});

it('deletes an admin entitlement', function () {
    Http::fake(['api.test/api/v1/leave/admin/entitlements/6' => Http::response(['message' => 'deleted'])]);
    bootTenantSession();

    leaveAdmin()->deleteEntitlement(6);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'leave/admin/entitlements/6'));
});

it('returns the admin summary as a raw array', function () {
    Http::fake(['api.test/api/v1/leave/admin/summary' => Http::response([
        'data' => [
            'pending_requests_count' => 3,
            'approved_this_month' => 5,
            'team_members_on_leave_today' => 1,
            'upcoming_leave_next_7_days' => [],
            'low_balance_warnings' => [],
        ],
    ])]);
    bootTenantSession();

    expect(leaveAdmin()->summary()['pending_requests_count'])->toBe(3);
});

it('maps a 422 on entitlement create to a validation exception', function () {
    Http::fake(['api.test/api/v1/leave/admin/entitlements' => Http::response([
        'message' => 'invalid',
        'errors' => ['user_id' => ['The user id is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        leaveAdmin()->createEntitlement([]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('user_id'))->toBe('The user id is required.');
    }
});

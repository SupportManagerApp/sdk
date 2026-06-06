<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Leave\LeaveBalance;
use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Data\Leave\LeaveRequest;
use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\AdminLeaveFactory;
use SupportManager\Sdk\Testing\Factories\LeaveBalanceFactory;
use SupportManager\Sdk\Testing\Factories\LeaveEntitlementFactory;
use SupportManager\Sdk\Testing\Factories\LeaveRequestFactory;
use SupportManager\Sdk\Testing\Factories\ManualAdjustmentFactory;

// Host-style: seed factories, call the SDK normally, assert on DTOs. No
// Http::fake(), no JSON:API envelopes by hand.

// ── Leave requests ──────────────────────────────────────────────────────────

it('lists seeded leave requests as DTOs', function () {
    SupportManager::fake()->seed(LeaveRequestFactory::new()->count(3));

    $page = SupportManager::leaveRequests()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(LeaveRequest::class)
        ->and($page->items()[0]->userName)->toBe('User 1')
        ->and($page->items()[0]->status)->toBe(LeaveStatus::Pending)
        ->and($page->total())->toBe(3);
});

it('finds a leave request by id and returns null for a miss', function () {
    SupportManager::fake()->seed(LeaveRequestFactory::new()->count(2));

    expect(SupportManager::leaveRequests()->find(2))->toBeInstanceOf(LeaveRequest::class)
        ->and(SupportManager::leaveRequests()->find(2)?->id)->toBe('2')
        ->and(SupportManager::leaveRequests()->find(999))->toBeNull();
});

it('drives the approve/reject/cancel workflow', function () {
    SupportManager::fake()->seed(LeaveRequestFactory::new()->count(3));

    expect(SupportManager::leaveRequests()->approve(1)?->status)->toBe(LeaveStatus::Approved);

    $rejected = SupportManager::leaveRequests()->reject(2, 'No cover');
    expect($rejected?->status)->toBe(LeaveStatus::Rejected)
        ->and($rejected?->approverComments)->toBe('No cover');

    expect(SupportManager::leaveRequests()->cancel(3, 'changed plans')?->status)->toBe(LeaveStatus::Cancelled);
});

it('surfaces a 404 when a workflow action targets a missing request', function () {
    SupportManager::fake()->seed(LeaveRequestFactory::new()->count(1));

    expect(fn () => SupportManager::leaveRequests()->approve(999))
        ->toThrow(SupportManagerApiException::class);
});

// ── Manual adjustments ──────────────────────────────────────────────────────

it('lists and mutates manual adjustments', function () {
    SupportManager::fake()->seed(ManualAdjustmentFactory::new()->count(2));

    $page = SupportManager::leaveAdjustments()->all();
    expect($page->items()[0])->toBeInstanceOf(ManualAdjustment::class)
        ->and($page->items()[0]->hoursAdjustment)->toBe(5.0)
        ->and($page->items()[0]->leaveYear)->toBe(2026);

    $created = SupportManager::leaveAdjustments()->create(['user_id' => 9, 'reason' => 'Bonus']);
    expect($created)->toBeInstanceOf(ManualAdjustment::class)
        ->and($created?->reason)->toBe('Bonus')
        ->and(SupportManager::leaveAdjustments()->all()->total())->toBe(3);

    SupportManager::leaveAdjustments()->destroy((string) $created?->id);
    expect(SupportManager::leaveAdjustments()->all()->total())->toBe(2);
});

// ── Entitlements (read-only) ────────────────────────────────────────────────

it('lists read-only entitlements as DTOs', function () {
    SupportManager::fake()->seed(LeaveEntitlementFactory::new()->count(2));

    $page = SupportManager::leaveEntitlements()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(LeaveEntitlement::class)
        ->and($page->items()[0]->hoursHoliday)->toBe(224.0)
        ->and($page->items()[0]->workingPatternName)->toBe('Full Time');
});

// ── Leave balance (flat) ────────────────────────────────────────────────────

it('serves the flat leave balance for the current and another user', function () {
    SupportManager::fake()->seed(LeaveBalanceFactory::new());

    $balance = SupportManager::leaveBalance()->show();
    expect($balance)->toBeInstanceOf(LeaveBalance::class)
        ->and($balance?->year)->toBe(2026)
        ->and($balance?->hours->remaining)->toBe(200.0)
        ->and($balance?->hours->percentages)->toBe(['used' => 7])
        ->and($balance?->sleeps->percentages)->toBeNull();

    expect(SupportManager::leaveBalance()->forUser(3)?->hours->approved)->toBe(16.0);
});

it('calculates the impact of a prospective request', function () {
    SupportManager::fake()->seed(LeaveBalanceFactory::new());

    $balance = SupportManager::leaveBalance()->calculate(['start_date' => '2026-07-01', 'type' => 'Whole Days']);

    expect($balance)->toBeInstanceOf(LeaveBalance::class)
        ->and($balance?->hours->remaining)->toBe(200.0);
});

// ── Admin leave ─────────────────────────────────────────────────────────────

it('lists the flat pending queue and runs bulk actions', function () {
    SupportManager::fake()->seed(AdminLeaveFactory::new());

    $pending = SupportManager::leaveAdmin()->pendingRequests();
    expect($pending)->toHaveCount(1)
        ->and($pending[0])->toBeInstanceOf(LeaveRequest::class)
        ->and($pending[0]->id)->toBe('7')
        ->and($pending[0]->userName)->toBe('Ada')
        ->and($pending[0]->status)->toBe(LeaveStatus::Pending);

    // Bulk actions are message-only and should not throw.
    SupportManager::leaveAdmin()->bulkApprove([7]);
    SupportManager::leaveAdmin()->bulkReject([7], 'Short staffed');
});

it('returns admin balances and a single user breakdown as raw arrays', function () {
    SupportManager::fake()->seed(AdminLeaveFactory::new());

    $balances = SupportManager::leaveAdmin()->balances();
    expect($balances)->toHaveCount(1)
        ->and($balances[0]['user']['name'])->toBe('Ada');

    $breakdown = SupportManager::leaveAdmin()->balanceFor(3) ?? [];
    expect($breakdown['user']['name'])->toBe('Ada');
});

it('lists admin adjustments and entitlements registers as DTOs', function () {
    SupportManager::fake()->seed(AdminLeaveFactory::new());

    $adjustments = SupportManager::leaveAdmin()->adjustments();
    expect($adjustments[0])->toBeInstanceOf(ManualAdjustment::class)
        ->and($adjustments[0]->reason)->toBe('Comp');

    $entitlements = SupportManager::leaveAdmin()->entitlements();
    expect($entitlements[0])->toBeInstanceOf(LeaveEntitlement::class)
        ->and($entitlements[0]->hoursHoliday)->toBe(224.0);
});

it('creates, updates and deletes an admin entitlement', function () {
    SupportManager::fake()->seed(AdminLeaveFactory::new());

    $created = SupportManager::leaveAdmin()->createEntitlement(['user_id' => 4, 'hours_holiday' => 100.0]);
    expect($created)->toBeInstanceOf(LeaveEntitlement::class)
        ->and($created?->hoursHoliday)->toBe(100.0)
        ->and(SupportManager::leaveAdmin()->entitlements())->toHaveCount(2);

    $updated = SupportManager::leaveAdmin()->updateEntitlement((string) $created?->id, ['hours_holiday' => 120.0]);
    expect($updated?->hoursHoliday)->toBe(120.0);

    SupportManager::leaveAdmin()->deleteEntitlement((string) $created?->id);
});

it('returns the admin summary as a raw array', function () {
    SupportManager::fake()->seed(AdminLeaveFactory::new());

    expect(SupportManager::leaveAdmin()->summary()['pending_requests_count'])->toBe(3);
});

<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Leave\LeaveBalance;
use SupportManager\Sdk\Data\Leave\LeaveCalculation;
use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Data\Leave\LeaveRequest;
use SupportManager\Sdk\Data\Leave\LeaveYears;
use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Data\Leave\NotWorkingNotice;
use SupportManager\Sdk\Data\Leave\OvertimeLog;
use SupportManager\Sdk\Enums\Leave\LeaveDurationType;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\AdminLeaveFactory;
use SupportManager\Sdk\Testing\Factories\LeaveBalanceFactory;
use SupportManager\Sdk\Testing\Factories\LeaveEntitlementFactory;
use SupportManager\Sdk\Testing\Factories\LeaveRequestFactory;
use SupportManager\Sdk\Testing\Factories\ManualAdjustmentFactory;
use SupportManager\Sdk\Testing\Factories\NotWorkingNoticeFactory;
use SupportManager\Sdk\Testing\Factories\OvertimeLogFactory;

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

it('carries the leave type and viewer permissions, which cancelling revokes', function () {
    SupportManager::fake()->seed(
        LeaveRequestFactory::new(),
        LeaveRequestFactory::new()->approved()->datesLocked(),
    );

    $open = SupportManager::leaveRequests()->find(1);
    $locked = SupportManager::leaveRequests()->find(2);

    expect($open?->leaveType)->toBe('annual_leave')
        ->and($open?->leaveTypeLabel)->toBe('Annual leave')
        ->and($open?->canEditDates)->toBeTrue()
        ->and($open?->canCancel)->toBeTrue()
        ->and($open?->canRequestReapproval)->toBeFalse()
        ->and($locked?->canEditDates)->toBeFalse()
        ->and($locked?->canCancel)->toBeTrue();

    $cancelled = SupportManager::leaveRequests()->cancel(1);

    expect($cancelled?->canEditDates)->toBeFalse()
        ->and($cancelled?->canCancel)->toBeFalse();
});

it('reports status counts that ignore the index filters', function () {
    SupportManager::fake()->seed(
        LeaveRequestFactory::new()->count(2),
        LeaveRequestFactory::new()->approved(),
        LeaveRequestFactory::new()->cancelled(),
    );

    $page = SupportManager::leaveRequests()->query()->filter('status', 'Pending')->perPage(1)->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->total())->toBe(2)
        ->and($page->meta()['status_counts'])->toBe(['Pending' => 2, 'Approved' => 1, 'Rejected' => 0, 'Cancelled' => 1]);
});

it('updates a leave request through the generic route', function () {
    SupportManager::fake()->seed(LeaveRequestFactory::new());

    $updated = SupportManager::leaveRequests()->update(1, ['requester_comments' => 'Back a day early']);

    expect($updated?->requesterComments)->toBe('Back a day early')
        ->and($updated?->canEditDates)->toBeTrue();
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

it('lists the current user\'s own adjustments for a year with totals', function () {
    SupportManager::fake()->seed(
        ManualAdjustmentFactory::new()->count(2),
        ManualAdjustmentFactory::new()->state(['leave_year' => 2025, 'hours_adjustment' => 3.0]),
    );

    $mine = SupportManager::leaveAdjustments()->mine();
    $previous = SupportManager::leaveAdjustments()->mine(['year' => 2025]);

    expect($mine->year)->toBe(2026)
        ->and($mine->adjustments)->toHaveCount(2)
        ->and($mine->adjustments[0])->toBeInstanceOf(ManualAdjustment::class)
        ->and($mine->totalHoursAdjustment)->toBe(10.0)
        ->and($mine->totalSleepsAdjustment)->toBe(-2.0)
        ->and($previous->adjustments)->toHaveCount(1)
        ->and($previous->totalHoursAdjustment)->toBe(3.0);
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

it('serves a distinct balance per leave year for year navigation', function () {
    SupportManager::fake()->seed(
        LeaveBalanceFactory::new()->forYear(2026),
        LeaveBalanceFactory::new()->forYear(2025)->state([
            'hours' => ['entitlement' => 224.0, 'pending' => 0.0, 'approved' => 224.0, 'remaining' => 0.0, 'percentages' => ['used' => 100]],
        ]),
    );

    expect(SupportManager::leaveBalance()->show()?->year)->toBe(2026)
        ->and(SupportManager::leaveBalance()->show(['year' => 2026])?->hours->remaining)->toBe(200.0)
        ->and(SupportManager::leaveBalance()->show(['year' => 2025])?->hours->remaining)->toBe(0.0)
        ->and(SupportManager::leaveBalance()->forUser(3, ['year' => 2025])?->year)->toBe(2025);
});

it('lists the browsable leave years with labels', function () {
    SupportManager::fake()->seed(LeaveBalanceFactory::new());

    $years = SupportManager::leaveBalance()->availableYears();

    expect($years)->toBeInstanceOf(LeaveYears::class)
        ->and($years?->years)->toBe([2026, 2027])
        ->and($years?->minimum)->toBe(2026)
        ->and($years?->maximum)->toBe(2027)
        ->and($years?->current)->toBe(2026)
        ->and($years?->locked)->toBe([])
        ->and($years?->labels)->toBe([2026 => '2026', 2027 => '2027']);
});

it('keeps a locked year browsable and lists it as locked', function () {
    SupportManager::fake()->seed(LeaveBalanceFactory::new()->lockingYear(2026));

    $years = SupportManager::leaveBalance()->availableYears();

    expect($years?->years)->toBe([2026, 2027])
        ->and($years?->locked)->toBe([2026])
        ->and($years?->isLocked(2026))->toBeTrue()
        ->and($years?->current)->toBe(2026);
});

it('adds the entitlement breakdown only when it is included', function () {
    SupportManager::fake()->seed(LeaveBalanceFactory::new());

    $breakdown = SupportManager::leaveBalance()->show(['include' => 'breakdown'])?->breakdown;

    expect(SupportManager::leaveBalance()->show()?->breakdown)->toBeNull()
        ->and($breakdown?->contract->hours)->toBe(224.0)
        ->and($breakdown?->overtime->hasData)->toBeFalse()
        ->and($breakdown?->total->sleeps)->toBe(10.0);
});

// ── Overtime (flat) ─────────────────────────────────────────────────────────

it('serves the overtime log for a leave year with totals', function () {
    SupportManager::fake()->seed(
        OvertimeLogFactory::new()->count(3),
        OvertimeLogFactory::new()->forYear(2025)->state(['id' => 9, 'month_end_date' => '2025-12-31']),
    );

    $log = SupportManager::leaveBalance()->overtime();
    $previous = SupportManager::leaveBalance()->overtime(['year' => 2025]);

    expect($log)->toBeInstanceOf(OvertimeLog::class)
        ->and($log?->year)->toBe(2026)
        ->and($log?->months)->toHaveCount(3)
        ->and($log?->months[0]->monthEndDate)->toBe('2026-01-31')
        ->and($log?->months[1]->monthEndDate)->toBe('2026-02-28')
        ->and($log?->totals->hoursWorked)->toBe(60.0)
        ->and($log?->totals->hoursAccrued)->toBe(6.0)
        ->and($log?->totals->sleepsAccrued)->toBe(1.5)
        ->and($previous?->months)->toHaveCount(1)
        ->and($previous?->months[0]->id)->toBe(9);
});

// ── Not-working notices ─────────────────────────────────────────────────────

it('lists upcoming, past and withdrawn notices', function () {
    SupportManager::fake()->seed(
        NotWorkingNoticeFactory::new()->count(2),
        NotWorkingNoticeFactory::new()->past(),
        NotWorkingNoticeFactory::new()->withdrawn(),
    );

    $upcoming = SupportManager::leaveNotWorkingNotices()->all();
    $past = SupportManager::leaveNotWorkingNotices()->query()->filter('show', 'past')->get();
    $withdrawn = SupportManager::leaveNotWorkingNotices()->query()->filter('show', 'withdrawn')->get();

    expect($upcoming->items())->toHaveCount(2)
        ->and($upcoming->items()[0])->toBeInstanceOf(NotWorkingNotice::class)
        ->and($upcoming->items()[0]->id)->toBe('1')
        ->and($upcoming->items()[0]->lastDayOff)->toBe('2099-11-11')
        ->and($upcoming->items()[0]->endDate)->toBe('2099-11-12T08:00:00+00:00')
        ->and($upcoming->items()[0]->canWithdraw)->toBeTrue()
        ->and($past->items())->toHaveCount(1)
        ->and($past->items()[0]->id)->toBe('3')
        ->and($withdrawn->items())->toHaveCount(1)
        ->and($withdrawn->items()[0]->status)->toBe(LeaveStatus::Cancelled)
        ->and($withdrawn->items()[0]->canWithdraw)->toBeFalse();
});

it('records a whole-day and a custom-timings notice', function () {
    SupportManager::fake()->seed(NotWorkingNoticeFactory::new()->count(0));

    $wholeDays = SupportManager::leaveNotWorkingNotices()->create([
        'duration_type' => 'Whole Days',
        'start_date' => '2099-12-01',
        'end_date' => '2099-12-03',
        'note' => 'Holiday',
    ]);
    $custom = SupportManager::leaveNotWorkingNotices()->create([
        'contractor_id' => 5,
        'duration_type' => 'Custom Timings',
        'start_date' => '2099-12-10',
        'start_time' => '13:00',
        'end_time' => '17:30',
    ]);

    expect($wholeDays?->startDate)->toBe('2099-12-01T08:00:00+00:00')
        ->and($wholeDays?->endDate)->toBe('2099-12-04T08:00:00+00:00')
        ->and($wholeDays?->lastDayOff)->toBe('2099-12-03')
        ->and($wholeDays?->status)->toBe(LeaveStatus::Approved)
        ->and($wholeDays?->note)->toBe('Holiday')
        ->and($custom?->userId)->toBe(5)
        ->and($custom?->durationType)->toBe(LeaveDurationType::CustomTimings)
        ->and($custom?->endDate)->toBe('2099-12-10T17:30:00+00:00')
        ->and(SupportManager::leaveNotWorkingNotices()->all()->total())->toBe(2);
});

it('refuses a notice without a start date', function () {
    SupportManager::fake()->seed(NotWorkingNoticeFactory::new()->count(0));

    expect(fn () => SupportManager::leaveNotWorkingNotices()->create(['duration_type' => 'Whole Days']))
        ->toThrow(ApiValidationException::class);
});

it('withdraws a notice once', function () {
    SupportManager::fake()->seed(NotWorkingNoticeFactory::new());

    $withdrawn = SupportManager::leaveNotWorkingNotices()->withdraw(1);

    expect($withdrawn?->status)->toBe(LeaveStatus::Cancelled)
        ->and($withdrawn?->canWithdraw)->toBeFalse()
        ->and(SupportManager::leaveNotWorkingNotices()->all()->items())->toBe([])
        ->and(fn () => SupportManager::leaveNotWorkingNotices()->withdraw(1))->toThrow(SupportManagerApiException::class)
        ->and(fn () => SupportManager::leaveNotWorkingNotices()->withdraw(999))->toThrow(SupportManagerApiException::class);
});

it('calculates the impact of a prospective request', function () {
    SupportManager::fake()->seed(LeaveBalanceFactory::new());

    $calculation = SupportManager::leaveBalance()->calculate(['start_date' => '2026-07-01', 'type' => 'Whole Days']);

    expect($calculation)->toBeInstanceOf(LeaveCalculation::class)
        ->and($calculation?->year)->toBe(2026)
        ->and($calculation?->requirement->hours)->toBe(8.0)
        ->and($calculation?->currentBalance->hours->remaining)->toBe(200.0)
        ->and($calculation?->balanceAfterRequest->hours)->toBe(192.0)
        ->and($calculation?->balanceAfterRequest->sleeps)->toBe(7.0);
});

it('takes a configured requirement off the seeded balance', function () {
    SupportManager::fake()->seed(LeaveBalanceFactory::new()->calculating(hours: 210.0, sleeps: 2.0));

    $calculation = SupportManager::leaveBalance()->calculate(['start_date' => '2026-07-01', 'end_date' => '2026-08-15', 'type' => 'Whole Days']);

    expect($calculation?->requirement->hours)->toBe(210.0)
        ->and($calculation?->balanceAfterRequest->hours)->toBe(-10.0)
        ->and($calculation?->balanceAfterRequest->sleeps)->toBe(5.0);
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

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\LeaveBalance;
use SupportManager\Sdk\Data\Leave\LeaveCalculation;
use SupportManager\Sdk\Data\Leave\LeaveYears;
use SupportManager\Sdk\Data\Leave\OvertimeLog;
use SupportManager\Sdk\Data\Leave\OvertimeMonth;

function leaveBalanceBody(): array
{
    return [
        'data' => [
            'year' => 2026,
            'hours' => ['entitlement' => 224.0, 'pending' => 8.0, 'approved' => 16.0, 'remaining' => 200.0, 'percentages' => ['approved' => 7.14, 'pending' => 3.57, 'remaining' => 89.29]],
            'sleeps' => ['entitlement' => 10.0, 'pending' => 1.0, 'approved' => 2.0, 'remaining' => 7.0, 'percentages' => ['approved' => 20.0, 'pending' => 10.0, 'remaining' => 70.0]],
        ],
        'meta' => ['team_id' => 42],
    ];
}

it('shows the current user balance', function () {
    fakeApi(['leave/balance' => Http::response(leaveBalanceBody())]);

    $balance = sdk()->leaveBalance()->show();

    expect($balance)->toBeInstanceOf(LeaveBalance::class)
        ->and($balance->year)->toBe(2026)
        ->and($balance->hours->entitlement)->toBe(224.0)
        ->and($balance->hours->remaining)->toBe(200.0)
        ->and($balance->hours->percentages)->toBe(['approved' => 7.14, 'pending' => 3.57, 'remaining' => 89.29])
        ->and($balance->sleeps->remaining)->toBe(7.0)
        ->and($balance->sleeps->percentages)->toEqual(['approved' => 20.0, 'pending' => 10.0, 'remaining' => 70.0]);

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('leaves the breakdown null unless it was included', function () {
    fakeApi(['leave/balance' => Http::response(leaveBalanceBody())]);

    expect(sdk()->leaveBalance()->show()->breakdown)->toBeNull();
});

it('shows the balance with its entitlement breakdown', function () {
    $body = leaveBalanceBody();
    $body['data']['breakdown'] = [
        'contract' => ['hours' => 200.0, 'sleeps' => 8.0],
        'overtime' => ['hours' => 12.0, 'sleeps' => 1.0, 'has_data' => true],
        'toil' => ['hours' => 0.0, 'sleeps' => 0.0, 'has_data' => false],
        'adjustments' => ['hours' => 12.0, 'sleeps' => 1.0, 'has_data' => true],
        'total' => ['hours' => 224.0, 'sleeps' => 10.0],
    ];
    fakeApi(['leave/balance*' => Http::response($body)]);

    $breakdown = sdk()->leaveBalance()->show(['include' => 'breakdown'])->breakdown;

    expect($breakdown->contract->hours)->toBe(200.0)
        ->and($breakdown->contract->hasData)->toBeNull()
        ->and($breakdown->overtime->hours)->toBe(12.0)
        ->and($breakdown->overtime->hasData)->toBeTrue()
        ->and($breakdown->toil->hasData)->toBeFalse()
        ->and($breakdown->adjustments->sleeps)->toBe(1.0)
        ->and($breakdown->total->hours)->toBe(224.0);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'include=breakdown'));
});

it('lists the browsable leave years with locks and labels', function () {
    fakeApi(['leave/balance/years' => [
        'data' => [
            'years' => [2025, 2026],
            'minimum' => 2025,
            'maximum' => 2026,
            'current' => 2026,
            'locked' => [2025],
            'labels' => ['2025' => '2025/26', '2026' => '2026/27'],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $years = sdk()->leaveBalance()->availableYears();

    expect($years)->toBeInstanceOf(LeaveYears::class)
        ->and($years->years)->toBe([2025, 2026])
        ->and($years->minimum)->toBe(2025)
        ->and($years->maximum)->toBe(2026)
        ->and($years->current)->toBe(2026)
        ->and($years->locked)->toBe([2025])
        ->and($years->isLocked(2025))->toBeTrue()
        ->and($years->isLocked(2026))->toBeFalse()
        ->and($years->labels)->toBe([2025 => '2025/26', 2026 => '2026/27']);
});

it('fetches the overtime log for a leave year', function () {
    fakeApi(['leave/overtime*' => [
        'data' => [
            'year' => 2025,
            'months' => [
                ['id' => 3, 'month_end_date' => '2025-04-30', 'hours_worked' => 10.5, 'hours_accrued' => 1.25, 'sleeps_worked' => 2.0, 'sleeps_accrued' => 0.5],
                ['id' => 4, 'month_end_date' => '2025-05-31', 'hours_worked' => 4.0, 'hours_accrued' => 0.5, 'sleeps_worked' => 0.0, 'sleeps_accrued' => 0.0],
            ],
            'totals' => ['hours_worked' => 14.5, 'hours_accrued' => 1.75, 'sleeps_worked' => 2.0, 'sleeps_accrued' => 0.5],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $log = sdk()->leaveBalance()->overtime(['year' => 2025]);

    expect($log)->toBeInstanceOf(OvertimeLog::class)
        ->and($log->year)->toBe(2025)
        ->and($log->months)->toHaveCount(2)
        ->and($log->months[0])->toBeInstanceOf(OvertimeMonth::class)
        ->and($log->months[0]->id)->toBe(3)
        ->and($log->months[0]->monthEndDate)->toBe('2025-04-30')
        ->and($log->months[0]->hoursAccrued)->toBe(1.25)
        ->and($log->totals->hoursWorked)->toBe(14.5)
        ->and($log->totals->sleepsAccrued)->toBe(0.5);

    Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), 'leave/overtime?year=2025') && $r->hasHeader('X-Team-ID', '42'));
});

it('shows another user balance', function () {
    fakeApi(['leave/balance/3' => Http::response(leaveBalanceBody())]);

    expect(sdk()->leaveBalance()->forUser(3)->hours->approved)->toBe(16.0);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'leave/balance/3'));
});

it('calculates the impact of a prospective request', function () {
    fakeApi(['leave/balance/calculate' => [
        'data' => [
            'requirement' => ['hours' => 15.0, 'sleeps' => 1],
            'current_balance' => [
                'hours' => ['entitlement' => 224.0, 'pending' => 8.0, 'approved' => 0.0, 'remaining' => 216.0],
                'sleeps' => ['entitlement' => 10.0, 'pending' => 0.0, 'approved' => 0.0, 'remaining' => 10.0],
            ],
            'balance_after_request' => ['hours' => 201.0, 'sleeps' => 9.0],
            'year' => 2026,
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $calculation = sdk()->leaveBalance()->calculate(['start_date' => '2026-07-01', 'type' => 'Whole Days']);

    expect($calculation)->toBeInstanceOf(LeaveCalculation::class)
        ->and($calculation->year)->toBe(2026)
        ->and($calculation->requirement->hours)->toBe(15.0)
        ->and($calculation->requirement->sleeps)->toBe(1.0)
        ->and($calculation->currentBalance->year)->toBe(2026)
        ->and($calculation->currentBalance->hours->remaining)->toBe(216.0)
        ->and($calculation->currentBalance->sleeps->entitlement)->toBe(10.0)
        ->and($calculation->balanceAfterRequest->hours)->toBe(201.0)
        ->and($calculation->balanceAfterRequest->sleeps)->toBe(9.0);
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['type'] === 'Whole Days');
});

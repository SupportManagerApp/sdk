<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\EstimatedPay;
use SupportManager\Sdk\Data\Payroll\EstimatedPayBreakdownLine;
use SupportManager\Sdk\Resources\Payroll\EstimatedPayResource;

function payrollEstimatedPayBody(): array
{
    return [
        'data' => [
            'year' => 2026,
            'month' => 6,
            'month_label' => 'June 2026',
            'hours' => [
                'total_value' => 150.0,
                'total_cost' => 2250.0,
                'breakdown' => [
                    ['type' => 'Contracted Hours', 'total_payroll_value' => 150.0, 'total_cost' => 2250.0],
                ],
            ],
            'sleeps' => [
                'total_value' => 4.0,
                'total_cost' => 200.0,
                'breakdown' => [
                    ['type' => 'Contracted Sleeping Night', 'total_payroll_value' => 4.0, 'total_cost' => 200.0],
                ],
            ],
            'enhancements' => [
                ['type' => 'Bank Holiday', 'total_value' => 8.0, 'total_cost' => 120.0],
            ],
            'grand_total' => ['total_value' => 162.0, 'total_cost' => 2570.0],
            'gross_pay' => 2570.0,
            'net_pay' => 2100.0,
            'deductions_total' => 470.0,
            'deductions' => [
                'income_tax' => 300.5,
                'national_insurance' => 170.0,
                'pension' => 0.0,
                'student_loan' => 0.0,
                'postgraduate_loan' => 0.0,
                'corrections' => [],
                'earnings_orders' => [],
            ],
        ],
        'meta' => ['team_id' => 42],
    ];
}

it('fetches the estimated-pay projection and passes year/month/user_id', function () {
    fakeApi(['payroll/estimated-pay*' => Http::response(payrollEstimatedPayBody())]);

    $estimate = sdk()->estimatedPay()->show(['year' => 2026, 'month' => 6, 'user_id' => 7]);

    expect($estimate)->toBeInstanceOf(EstimatedPay::class)
        ->and($estimate->year)->toBe(2026)
        ->and($estimate->month)->toBe(6)
        ->and($estimate->monthLabel)->toBe('June 2026')
        ->and($estimate->hours['total_cost'])->toBe(2250.0)
        ->and($estimate->enhancements)->toHaveCount(1)
        ->and($estimate->enhancements[0]['type'])->toBe('Bank Holiday')
        ->and($estimate->grandTotal['total_cost'])->toBe(2570.0)
        ->and($estimate->grossPay)->toBe(2570.0)
        ->and($estimate->netPay)->toBe(2100.0)
        ->and($estimate->deductions?->incomeTax)->toBe(300.5)
        ->and($estimate->deductions?->nationalInsurance)->toBe(170.0);

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'year=2026')
            && str_contains($url, 'month=6')
            && str_contains($url, 'user_id=7')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates the per-type hours and sleeps breakdowns', function () {
    fakeApi(['payroll/estimated-pay*' => Http::response(payrollEstimatedPayBody())]);

    $estimate = sdk()->estimatedPay()->show();

    expect($estimate->hoursBreakdown)->toHaveCount(1)
        ->and($estimate->hoursBreakdown[0])->toBeInstanceOf(EstimatedPayBreakdownLine::class)
        ->and($estimate->hoursBreakdown[0]->type)->toBe('Contracted Hours')
        ->and($estimate->hoursBreakdown[0]->totalValue)->toBe(150.0)
        ->and($estimate->hoursBreakdown[0]->totalCost)->toBe(2250.0)
        ->and($estimate->hoursBreakdown[0]->afterCloseValue)->toBeNull()
        ->and($estimate->hoursBreakdown[0]->currentMonthCost)->toBeNull()
        ->and($estimate->sleepsBreakdown)->toHaveCount(1)
        ->and($estimate->sleepsBreakdown[0]->type)->toBe('Contracted Sleeping Night')
        ->and($estimate->sleepsBreakdown[0]->totalValue)->toBe(4.0)
        // The totals stay {total_value, total_cost}.
        ->and($estimate->hours)->toBe(['total_value' => 150.0, 'total_cost' => 2250.0]);
});

it('reads the after-close split the platform adds to a row worked after last month closed', function () {
    $body = payrollEstimatedPayBody();
    // PayrollReportManager::addTotalRows() adds these four only when part of the row came in late.
    $late = ['after_close_payroll_value' => 10.0, 'after_close_cost' => 150.0, 'current_month_payroll_value' => 140.0, 'current_month_cost' => 2100.0];
    $body['data']['hours']['breakdown'][0] += $late;
    $body['data']['sleeps']['breakdown'][] = ['type' => 'Waking Night', 'total_payroll_value' => 1, 'total_cost' => 90, 'after_close_payroll_value' => 1, 'after_close_cost' => 90, 'current_month_payroll_value' => 0, 'current_month_cost' => 0];
    fakeApi(['payroll/estimated-pay*' => Http::response($body)]);

    $estimate = sdk()->estimatedPay()->show();

    expect($estimate->hoursBreakdown[0]->afterCloseValue)->toBe(10.0)
        ->and($estimate->hoursBreakdown[0]->afterCloseCost)->toBe(150.0)
        ->and($estimate->hoursBreakdown[0]->currentMonthValue)->toBe(140.0)
        ->and($estimate->hoursBreakdown[0]->currentMonthCost)->toBe(2100.0)
        ->and($estimate->sleepsBreakdown)->toHaveCount(2)
        ->and($estimate->sleepsBreakdown[1]->afterCloseValue)->toBe(1.0)
        ->and($estimate->sleepsBreakdown[1]->currentMonthValue)->toBe(0.0);
});

it('leaves the net-pay fields null when the estimate has no net-pay breakdown data', function () {
    // The platform always sends a projection; with no tax code configured the
    // gross/net/deductions fields are null (EstimatedPayResource::toArray()).
    $body = payrollEstimatedPayBody();
    $body['data']['gross_pay'] = null;
    $body['data']['net_pay'] = null;
    $body['data']['deductions_total'] = null;
    $body['data']['deductions'] = null;

    fakeApi(['payroll/estimated-pay*' => Http::response($body)]);

    $estimate = sdk()->estimatedPay()->show();

    expect($estimate)->toBeInstanceOf(EstimatedPay::class)
        ->and($estimate?->grossPay)->toBeNull()
        ->and($estimate?->netPay)->toBeNull()
        ->and($estimate?->deductionsTotal)->toBeNull()
        ->and($estimate?->deductions)->toBeNull();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\EstimatedPay;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Payroll\EstimatedPayResource;

function payrollEstimatedPay(): EstimatedPayResource
{
    return app(EstimatedPayResource::class);
}

function payrollEstimatedPayBody(): array
{
    return [
        'data' => [
            'year' => 2026,
            'month' => 6,
            'month_label' => 'June 2026',
            'hours' => ['total_value' => 150.0, 'total_cost' => 2250.0],
            'sleeps' => ['total_value' => 4.0, 'total_cost' => 200.0],
            'enhancements' => [
                ['type' => 'Bank Holiday', 'total_value' => 8.0, 'total_cost' => 120.0],
            ],
            'grand_total' => ['total_value' => 162.0, 'total_cost' => 2570.0],
            'gross_pay' => 2570.0,
            'net_pay' => 2100.0,
            'deductions_total' => 470.0,
            'deductions' => ['income_tax' => 300.5, 'national_insurance' => 170.0],
        ],
        'meta' => ['team_id' => 42],
    ];
}

it('fetches the estimated-pay projection and passes year/month/user_id', function () {
    Http::fake(['api.test/api/v1/payroll/estimated-pay*' => Http::response(payrollEstimatedPayBody())]);
    bootTenantSession();

    $estimate = payrollEstimatedPay()->show(['year' => 2026, 'month' => 6, 'user_id' => 7]);

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

it('returns null when the estimate has no net-pay breakdown data', function () {
    Http::fake(['api.test/api/v1/payroll/estimated-pay*' => Http::response(['data' => null, 'meta' => ['team_id' => 42]])]);
    bootTenantSession();

    expect(payrollEstimatedPay()->show())->toBeNull();
});

it('maps a server error on the estimated-pay path to an api exception', function () {
    Http::fake(['api.test/api/v1/payroll/estimated-pay*' => Http::response('<html>boom</html>', 500)]);
    bootTenantSession();

    expect(fn () => payrollEstimatedPay()->show())
        ->toThrow(SupportManagerApiException::class);
});

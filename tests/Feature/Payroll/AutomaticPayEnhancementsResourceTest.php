<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\AutomaticPayEnhancementSettings;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Payroll\AutomaticPayEnhancementsResource;

function payrollAutomaticPayEnhancements(): AutomaticPayEnhancementsResource
{
    return app(AutomaticPayEnhancementsResource::class);
}

it('fetches the automatic pay-enhancement settings', function () {
    Http::fake(['api.test/api/v1/payroll/automatic-pay-enhancements' => Http::response([
        'data' => [
            'bank_holiday_enhancement_id' => 3,
            'christmas_enhancement_id' => 4,
            'holiday_cover_enhancement_id' => null,
            'sickness_cover_enhancement_id' => 5,
        ],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $settings = payrollAutomaticPayEnhancements()->show();

    expect($settings)->toBeInstanceOf(AutomaticPayEnhancementSettings::class)
        ->and($settings->bankHolidayEnhancementId)->toBe(3)
        ->and($settings->christmasEnhancementId)->toBe(4)
        ->and($settings->holidayCoverEnhancementId)->toBeNull()
        ->and($settings->sicknessCoverEnhancementId)->toBe(5);

    Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('X-Team-ID', '42'));
});

it('returns null when no settings have been configured', function () {
    Http::fake(['api.test/api/v1/payroll/automatic-pay-enhancements' => Http::response(['data' => null, 'meta' => ['team_id' => 42]])]);
    bootTenantSession();

    expect(payrollAutomaticPayEnhancements()->show())->toBeNull();
});

it('updates the automatic pay-enhancement settings via PUT', function () {
    Http::fake(['api.test/api/v1/payroll/automatic-pay-enhancements' => Http::response([
        'data' => [
            'bank_holiday_enhancement_id' => 9,
            'christmas_enhancement_id' => null,
            'holiday_cover_enhancement_id' => null,
            'sickness_cover_enhancement_id' => null,
        ],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $settings = payrollAutomaticPayEnhancements()->update(['bank_holiday_enhancement_id' => 9]);

    expect($settings)->toBeInstanceOf(AutomaticPayEnhancementSettings::class)
        ->and($settings->bankHolidayEnhancementId)->toBe(9);

    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['bank_holiday_enhancement_id'] === 9);
});

it('maps a 422 on update to a validation exception', function () {
    Http::fake(['api.test/api/v1/payroll/automatic-pay-enhancements' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['bank_holiday_enhancement_id' => ['The selected bank holiday enhancement does not exist or does not belong to this team.']],
    ], 422)]);
    bootTenantSession();

    try {
        payrollAutomaticPayEnhancements()->update(['bank_holiday_enhancement_id' => 999]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('bank_holiday_enhancement_id'))
            ->toBe('The selected bank holiday enhancement does not exist or does not belong to this team.');
    }
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\AutomaticPayEnhancementSettings;

it('fetches the automatic pay-enhancement settings', function () {
    fakeApi(['payroll/automatic-pay-enhancements' => [
        'data' => [
            'bank_holiday_enhancement_id' => 3,
            'christmas_enhancement_id' => 4,
            'holiday_cover_enhancement_id' => null,
            'sickness_cover_enhancement_id' => 5,
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $settings = sdk()->automaticPayEnhancements()->show();

    expect($settings)->toBeInstanceOf(AutomaticPayEnhancementSettings::class)
        ->and($settings->bankHolidayEnhancementId)->toBe(3)
        ->and($settings->christmasEnhancementId)->toBe(4)
        ->and($settings->holidayCoverEnhancementId)->toBeNull()
        ->and($settings->sicknessCoverEnhancementId)->toBe(5);

    Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('X-Team-ID', '42'));
});

it('returns null when no settings have been configured', function () {
    fakeApi(['payroll/automatic-pay-enhancements' => ['data' => null, 'meta' => ['team_id' => 42]]]);

    expect(sdk()->automaticPayEnhancements()->show())->toBeNull();
});

it('updates the automatic pay-enhancement settings via PUT', function () {
    fakeApi(['payroll/automatic-pay-enhancements' => [
        'data' => [
            'bank_holiday_enhancement_id' => 9,
            'christmas_enhancement_id' => null,
            'holiday_cover_enhancement_id' => null,
            'sickness_cover_enhancement_id' => null,
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $settings = sdk()->automaticPayEnhancements()->update(['bank_holiday_enhancement_id' => 9]);

    expect($settings)->toBeInstanceOf(AutomaticPayEnhancementSettings::class)
        ->and($settings->bankHolidayEnhancementId)->toBe(9);

    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['bank_holiday_enhancement_id'] === 9);
});

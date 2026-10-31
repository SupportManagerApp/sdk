<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Data\Rota\SleepoverStartOption;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Rota\ShiftBlocksResource;

function rotaShiftBlocks(): ShiftBlocksResource
{
    return app(ShiftBlocksResource::class);
}

it('reads the flat sleepover start options', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/start-options' => Http::response([
        'data' => [
            ['time' => '20:00', 'label' => '8:00 PM', 'compliant' => true, 'reason' => null],
            ['time' => '21:00', 'label' => '9:00 PM', 'compliant' => false, 'reason' => 'Too little rest.'],
        ],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $options = rotaShiftBlocks()->startOptions(9);

    expect($options)->toHaveCount(2)
        ->and($options[0])->toBeInstanceOf(SleepoverStartOption::class)
        ->and($options[0]->time)->toBe('20:00')
        ->and($options[0]->label)->toBe('8:00 PM')
        ->and($options[0]->compliant)->toBeTrue()
        ->and($options[0]->reason)->toBeNull()
        ->and($options[1]->compliant)->toBeFalse()
        ->and($options[1]->reason)->toBe('Too little rest.');
    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('returns no start options when the sleepover has no evening to trim', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/start-options' => Http::response(['data' => [], 'meta' => ['team_id' => 42]])]);
    bootTenantSession();

    expect(rotaShiftBlocks()->startOptions(9))->toBe([]);
});

it('throws when the caller may not apply for the sleepover (403)', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/start-options' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    expect(fn () => rotaShiftBlocks()->startOptions(9))
        ->toThrow(SupportManagerApiException::class);
});

it('applies for a sleepover with a later start and hydrates one application per segment', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/application' => Http::response([
        'data' => [
            ['id' => '31', 'type' => 'shift_applications', 'attributes' => ['status' => 'Pending', 'start_time' => '20:00']],
            ['id' => '32', 'type' => 'shift_applications', 'attributes' => ['status' => 'Pending']],
            ['id' => '33', 'type' => 'shift_applications', 'attributes' => ['status' => 'Pending']],
        ],
        'message' => 'Your application for the sleepover (3 parts) has been submitted for review.',
        'meta' => ['team_id' => 42],
    ], 201)]);
    bootTenantSession();

    $applications = rotaShiftBlocks()->apply(9, '20:00');

    expect($applications)->toHaveCount(3)
        ->and($applications[0])->toBeInstanceOf(ShiftApplication::class)
        ->and($applications[0]->startTime)->toBe('20:00')
        ->and($applications[2]->id)->toBe('33');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['start_time'] === '20:00'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('sends no start time when applying from the natural start', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/application' => Http::response(['data' => []], 201)]);
    bootTenantSession();

    rotaShiftBlocks()->apply(9);

    Http::assertSent(fn ($request) => $request->method() === 'POST' && ! isset($request['start_time']));
});

it('maps an ineligible sleepover application to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/application' => Http::response([
        'message' => 'You have already applied for this sleepover.',
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaShiftBlocks()->apply(9))
        ->toThrow(ApiValidationException::class);
});

it('withdraws a sleepover application', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/application' => Http::response([
        'message' => 'Your sleepover application has been withdrawn.',
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    rotaShiftBlocks()->cancel(9);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'rota/shift-blocks/9/application'));
});

it('throws when there is nothing pending to withdraw (422)', function () {
    Http::fake(['api.test/api/v1/rota/shift-blocks/9/application' => Http::response([
        'message' => 'You have no pending application for this sleepover.',
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaShiftBlocks()->cancel(9))
        ->toThrow(ApiValidationException::class);
});

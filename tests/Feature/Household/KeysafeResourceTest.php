<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\Keysafe;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Household\KeysafeResource;

function householdKeysafe(): KeysafeResource
{
    return app(KeysafeResource::class);
}

it('shows the keysafe state from a flat data body', function () {
    Http::fake(['api.test/api/v1/household/keysafe' => Http::response([
        'data' => ['has_code' => true, 'can_edit' => false],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $keysafe = householdKeysafe()->show();

    expect($keysafe)->toBeInstanceOf(Keysafe::class)
        ->and($keysafe->hasCode)->toBeTrue()
        ->and($keysafe->canEdit)->toBeFalse()
        ->and($keysafe->code)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('reveals the keysafe code', function () {
    Http::fake(['api.test/api/v1/household/keysafe/reveal' => Http::response([
        'data' => ['code' => '1234'],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    expect(householdKeysafe()->reveal()->code)->toBe('1234');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'keysafe/reveal'));
});

it('returns a null code when none is set', function () {
    Http::fake(['api.test/api/v1/household/keysafe/reveal' => Http::response([
        'data' => ['code' => null],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    expect(householdKeysafe()->reveal()->code)->toBeNull();
});

it('updates the keysafe code', function () {
    Http::fake(['api.test/api/v1/household/keysafe' => Http::response([
        'data' => ['has_code' => true, 'can_edit' => true],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $keysafe = householdKeysafe()->update('5678');

    expect($keysafe->hasCode)->toBeTrue()
        ->and($keysafe->canEdit)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['code'] === '5678');
});

it('maps a 403 on update to an api exception', function () {
    Http::fake(['api.test/api/v1/household/keysafe' => Http::response(['message' => 'Forbidden'], 403)]);
    bootTenantSession();

    expect(fn () => householdKeysafe()->update('5678'))
        ->toThrow(SupportManagerApiException::class);
});

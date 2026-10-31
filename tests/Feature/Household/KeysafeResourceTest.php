<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\Keysafe;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

it('shows the keysafe state from a flat data body', function () {
    fakeApi(['household/keysafe' => [
        'data' => ['has_code' => true, 'can_edit' => false],
        'meta' => ['team_id' => 42],
    ]]);

    $keysafe = sdk()->keysafe()->show();

    expect($keysafe)->toBeInstanceOf(Keysafe::class)
        ->and($keysafe->hasCode)->toBeTrue()
        ->and($keysafe->canEdit)->toBeFalse()
        ->and($keysafe->code)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('reveals the keysafe code', function () {
    fakeApi(['household/keysafe/reveal' => [
        'data' => ['code' => '1234'],
        'meta' => ['team_id' => 42],
    ]]);

    expect(sdk()->keysafe()->reveal())->toBe('1234');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'keysafe/reveal'));
});

it('returns a null code when none is set', function () {
    fakeApi(['household/keysafe/reveal' => [
        'data' => ['code' => null],
        'meta' => ['team_id' => 42],
    ]]);

    expect(sdk()->keysafe()->reveal())->toBeNull();
});

it('throws when the reveal is refused, rather than returning no code', function () {
    fakeApi(['household/keysafe/reveal' => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    sdk()->keysafe()->reveal();
})->throws(SupportManagerApiException::class, 'This action is unauthorized.');

it('updates the keysafe code', function () {
    fakeApi(['household/keysafe' => [
        'data' => ['has_code' => true, 'can_edit' => true],
        'meta' => ['team_id' => 42],
    ]]);

    $keysafe = sdk()->keysafe()->update('5678');

    expect($keysafe->hasCode)->toBeTrue()
        ->and($keysafe->canEdit)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['code'] === '5678');
});

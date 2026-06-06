<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\PushNotifications\DeviceToken;
use SupportManager\Sdk\Data\PushNotifications\PushNotificationStatus;
use SupportManager\Sdk\Enums\PushNotifications\DevicePlatform;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\PushNotifications\DeviceTokensResource;

function deviceTokens(): DeviceTokensResource
{
    return app(DeviceTokensResource::class);
}

function deviceTokenObject(string $id = '1', string $token = 'fcm-token-abc'): array
{
    return [
        'type' => 'device-tokens',
        'id' => $id,
        'attributes' => [
            'token' => $token,
            'platform' => 'ios',
            'last_used_at' => '2026-06-06T08:00:00+00:00',
            'created_at' => '2026-06-01T08:00:00+00:00',
            'updated_at' => '2026-06-06T08:00:00+00:00',
        ],
    ];
}

it('lists the user device tokens, tenant-scoped', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens*' => Http::response([
        'data' => [deviceTokenObject('1'), deviceTokenObject('2', 'fcm-token-def')],
        'meta' => ['count' => 2],
    ])]);
    bootTenantSession();

    $page = deviceTokens()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(DeviceToken::class)
        ->and($page->items()[0]->token)->toBe('fcm-token-abc')
        ->and($page->items()[0]->platform)->toBe(DevicePlatform::Ios);

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('registers a device token from an enum platform', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens' => Http::response([
        'data' => deviceTokenObject('5', 'fcm-new'),
    ], 201)]);
    bootTenantSession();

    $token = deviceTokens()->register('fcm-new', DevicePlatform::Ios);

    expect($token)->toBeInstanceOf(DeviceToken::class)
        ->and($token->id)->toBe('5')
        ->and($token->token)->toBe('fcm-new');

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r['token'] === 'fcm-new'
        && $r['platform'] === 'ios'
        && $r->hasHeader('X-Team-ID', '42'));
});

it('accepts a string platform when registering', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens' => Http::response([
        'data' => deviceTokenObject('6', 'fcm-android'),
    ], 201)]);
    bootTenantSession();

    deviceTokens()->register('fcm-android', 'android');

    Http::assertSent(fn ($r) => $r['platform'] === 'android');
});

it('reads the push-notification status', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens/status' => Http::response([
        'data' => ['has_tokens' => true, 'token_count' => 3, 'prompt_dismissed' => false],
    ])]);
    bootTenantSession();

    $status = deviceTokens()->status();

    expect($status)->toBeInstanceOf(PushNotificationStatus::class)
        ->and($status->hasTokens)->toBeTrue()
        ->and($status->tokenCount)->toBe(3)
        ->and($status->promptDismissed)->toBeFalse();
});

it('forgets a token by value', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens' => Http::response(['success' => true])]);
    bootTenantSession();

    deviceTokens()->forget('fcm-token-abc');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE'
        && $r['token'] === 'fcm-token-abc'
        && str_ends_with($r->url(), '/push-notifications/tokens'));
});

it('deletes a token by id', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens/7' => Http::response(['success' => true])]);
    bootTenantSession();

    deviceTokens()->destroy(7);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'tokens/7'));
});

it('deletes all tokens', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens/all' => Http::response(['success' => true])]);
    bootTenantSession();

    deviceTokens()->destroyAll();

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'tokens/all'));
});

it('dismisses the setup prompt', function () {
    Http::fake(['api.test/api/v1/push-notifications/prompt/dismiss' => Http::response(['success' => true])]);
    bootTenantSession();

    deviceTokens()->dismissPrompt();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), 'prompt/dismiss'));
});

it('throws when deleting a missing token by id (404)', function () {
    Http::fake(['api.test/api/v1/push-notifications/tokens/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    deviceTokens()->destroy(999);
})->throws(SupportManagerApiException::class);

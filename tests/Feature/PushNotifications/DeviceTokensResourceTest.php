<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\PushNotifications\DeviceToken;
use SupportManager\Sdk\Data\PushNotifications\PushNotificationStatus;
use SupportManager\Sdk\Enums\PushNotifications\DevicePlatform;

function deviceTokenObject(string $id = '1', string $token = 'fcm-token-abc'): array
{
    return [
        'type' => 'device_tokens',
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
    fakeApi(['push-notifications/tokens*' => [
        'data' => [deviceTokenObject('1'), deviceTokenObject('2', 'fcm-token-def')],
        'meta' => ['count' => 2],
    ]]);

    $page = sdk()->deviceTokens()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(DeviceToken::class)
        ->and($page->items()[0]->token)->toBe('fcm-token-abc')
        ->and($page->items()[0]->platform)->toBe(DevicePlatform::Ios);

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('registers a device token from an enum platform', function () {
    fakeApi(['push-notifications/tokens' => Http::response([
        'data' => deviceTokenObject('5', 'fcm-new'),
        'success' => true,
    ], 201)]);

    $token = sdk()->deviceTokens()->register('fcm-new', DevicePlatform::Ios);

    expect($token)->toBeInstanceOf(DeviceToken::class)
        ->and($token->id)->toBe('5')
        ->and($token->token)->toBe('fcm-new');

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r['token'] === 'fcm-new'
        && $r['platform'] === 'ios'
        && $r->hasHeader('X-Team-ID', '42'));
});

it('accepts a string platform when registering', function () {
    fakeApi(['push-notifications/tokens' => Http::response([
        'data' => deviceTokenObject('6', 'fcm-android'),
        'success' => true,
    ], 201)]);

    sdk()->deviceTokens()->register('fcm-android', 'android');

    Http::assertSent(fn ($r) => $r['platform'] === 'android');
});

it('reads the push-notification status', function () {
    fakeApi(['push-notifications/tokens/status' => [
        'data' => ['has_tokens' => true, 'token_count' => 3, 'prompt_dismissed' => false],
    ]]);

    $status = sdk()->deviceTokens()->status();

    expect($status)->toBeInstanceOf(PushNotificationStatus::class)
        ->and($status->hasTokens)->toBeTrue()
        ->and($status->tokenCount)->toBe(3)
        ->and($status->promptDismissed)->toBeFalse();
});

it('forgets a token by value', function () {
    fakeApi(['push-notifications/tokens' => ['success' => true]]);

    sdk()->deviceTokens()->forget('fcm-token-abc');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE'
        && $r['token'] === 'fcm-token-abc'
        && str_ends_with($r->url(), '/push-notifications/tokens'));
});

it('deletes a token by id', function () {
    fakeApi(['push-notifications/tokens/7' => ['success' => true]]);

    sdk()->deviceTokens()->destroy(7);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'tokens/7'));
});

it('deletes all tokens', function () {
    fakeApi(['push-notifications/tokens/all' => ['success' => true]]);

    sdk()->deviceTokens()->destroyAll();

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'tokens/all'));
});

it('dismisses the setup prompt', function () {
    fakeApi(['push-notifications/prompt/dismiss' => ['success' => true, 'message' => 'Notification prompt dismissed.']]);

    sdk()->deviceTokens()->dismissPrompt();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), 'prompt/dismiss'));
});

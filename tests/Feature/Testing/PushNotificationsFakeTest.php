<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\PushNotifications\DeviceToken;
use SupportManager\Sdk\Data\PushNotifications\PushNotificationStatus;
use SupportManager\Sdk\Enums\PushNotifications\DevicePlatform;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\DeviceTokenFactory;

// Host-style tests: seed factories, call the SDK, assert on DTOs. No Http::fake().

it('lists seeded device tokens as DTOs (JSON:API)', function () {
    SupportManager::fake()->seed(DeviceTokenFactory::new()->count(2));

    $page = SupportManager::deviceTokens()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(DeviceToken::class)
        ->and($page->items()[0]->token)->toBe('fcm-token-1')
        ->and($page->items()[0]->platform)->toBe(DevicePlatform::Ios);
});

it('registers a device token and reads the flat status', function () {
    SupportManager::fake()->seed(DeviceTokenFactory::new()->count(1));

    $token = SupportManager::deviceTokens()->register('fcm-new', DevicePlatform::Android);
    expect($token)->toBeInstanceOf(DeviceToken::class)
        ->and($token?->token)->toBe('fcm-new');

    $status = SupportManager::deviceTokens()->status();
    expect($status)->toBeInstanceOf(PushNotificationStatus::class)
        ->and($status->hasTokens)->toBeTrue()
        ->and($status->tokenCount)->toBe(2)
        ->and($status->promptDismissed)->toBeFalse();
});

it('reports an empty status when no tokens are seeded', function () {
    SupportManager::fake()->seed(DeviceTokenFactory::new()->count(0));

    $status = SupportManager::deviceTokens()->status();

    expect($status->hasTokens)->toBeFalse()
        ->and($status->tokenCount)->toBe(0);
});

it('forgets a token by value (DELETE on the bare path)', function () {
    SupportManager::fake()->seed(DeviceTokenFactory::new()->count(2));

    SupportManager::deviceTokens()->forget('fcm-token-1');

    expect(SupportManager::deviceTokens()->status()->tokenCount)->toBe(1);
});

it('deletes a token by id and deletes all tokens', function () {
    SupportManager::fake()->seed(DeviceTokenFactory::new()->count(3));

    SupportManager::deviceTokens()->destroy(1);
    expect(SupportManager::deviceTokens()->status()->tokenCount)->toBe(2);

    SupportManager::deviceTokens()->destroyAll();
    expect(SupportManager::deviceTokens()->status()->tokenCount)->toBe(0);
});

it('dismisses the setup prompt without error', function () {
    SupportManager::fake()->seed(DeviceTokenFactory::new()->count(1));

    SupportManager::deviceTokens()->dismissPrompt();
})->throwsNoExceptions();

it('throws when deleting a token id that is not the user\'s', function () {
    SupportManager::fake()->seed(DeviceTokenFactory::new()->count(1));

    expect(fn () => SupportManager::deviceTokens()->destroy(999))
        ->toThrow(SupportManagerApiException::class);
    expect(SupportManager::deviceTokens()->status()->tokenCount)->toBe(1);
});

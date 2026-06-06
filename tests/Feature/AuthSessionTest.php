<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\AuthenticatedUser;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Tests\Support\ArraySecureStore;

function session_store(): array
{
    $store = new ArraySecureStore;

    return [new AuthSession($store), $store];
}

function tokens(array $overrides = []): AuthTokens
{
    return new AuthTokens(
        accessToken: $overrides['accessToken'] ?? 'access-123',
        refreshToken: $overrides['refreshToken'] ?? 'refresh-456',
        tokenType: 'Bearer',
        expiresIn: 3600,
        user: $overrides['user'] ?? new AuthenticatedUser(7, 'Ada Lovelace', 'ada@test.com'),
        deviceToken: $overrides['deviceToken'] ?? null,
    );
}

it('persists tokens and exposes the session', function () {
    [$session] = session_store();

    expect($session->check())->toBeFalse();

    $session->start(tokens());

    expect($session->check())->toBeTrue()
        ->and($session->accessToken())->toBe('access-123')
        ->and($session->refreshToken())->toBe('refresh-456')
        ->and($session->user())->toBeInstanceOf(AuthenticatedUser::class)
        ->and($session->user()->email)->toBe('ada@test.com');
});

it('stores the device token when remembering the device', function () {
    [$session] = session_store();

    $session->start(tokens(['deviceToken' => 'device-xyz']), rememberDevice: true);

    expect($session->deviceToken())->toBe('device-xyz');
});

it('does not store the device token when not remembering the device', function () {
    [$session] = session_store();

    $session->start(tokens(['deviceToken' => 'device-xyz']), rememberDevice: false);

    expect($session->deviceToken())->toBeNull();
});

it('keeps an existing device token when a later login issues none', function () {
    [$session] = session_store();

    $session->start(tokens(['deviceToken' => 'device-xyz']));
    $session->start(tokens(['accessToken' => 'access-999', 'deviceToken' => null]));

    expect($session->accessToken())->toBe('access-999')
        ->and($session->deviceToken())->toBe('device-xyz');
});

it('clears auth on forget but keeps the trusted device token', function () {
    [$session] = session_store();

    $session->start(tokens(['deviceToken' => 'device-xyz']));
    $session->forget();

    expect($session->check())->toBeFalse()
        ->and($session->accessToken())->toBeNull()
        ->and($session->user())->toBeNull()
        ->and($session->deviceToken())->toBe('device-xyz');
});

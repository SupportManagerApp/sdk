<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\AuthenticatedUser;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\RefreshSession;
use SupportManager\Sdk\Tests\Support\ArraySecureStore;

/**
 * @return array{RefreshSession, AuthSession}
 */
function refresh_action(): array
{
    $session = new AuthSession(new ArraySecureStore);
    $client = new SupportManagerClient('https://api.test', 'Test Device');

    return [new RefreshSession($client, $session), $session];
}

function refresh_response(array $overrides = []): array
{
    return ['data' => array_merge([
        'access_token' => 'access-new',
        'refresh_token' => 'refresh-new',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ], $overrides)];
}

it('exchanges the refresh token and persists the new pair', function () {
    Http::fake([apiUrl('auth/refresh') => Http::response(refresh_response())]);

    [$refresh, $session] = refresh_action();
    $session->start(new AuthTokens('access-old', 'refresh-old', 'Bearer', 3600));

    $token = $refresh();

    expect($token)->toBe('access-new')
        ->and($session->accessToken())->toBe('access-new')
        ->and($session->refreshToken())->toBe('refresh-new');

    Http::assertSent(fn ($request) => $request['refresh_token'] === 'refresh-old');
});

it('returns null without calling the API when there is no refresh token', function () {
    Http::fake();

    [$refresh, $session] = refresh_action();

    expect($refresh())->toBeNull();

    Http::assertNothingSent();
});

it('clears the session when the refresh token is rejected', function () {
    Http::fake([apiUrl('auth/refresh') => Http::response(['message' => 'expired'], 401)]);

    [$refresh, $session] = refresh_action();
    $session->start(new AuthTokens('access-old', 'refresh-old', 'Bearer', 3600));

    expect($refresh())->toBeNull()
        ->and($session->check())->toBeFalse()
        ->and($session->refreshToken())->toBeNull();
});

it('keeps the cached user when the refresh response omits one', function () {
    Http::fake([apiUrl('auth/refresh') => Http::response(refresh_response())]);

    [$refresh, $session] = refresh_action();
    $session->start(new AuthTokens(
        'access-old',
        'refresh-old',
        'Bearer',
        3600,
        new AuthenticatedUser(7, 'Ada', 'ada@test.com'),
    ));

    $refresh();

    expect($session->user()?->email)->toBe('ada@test.com');
});

it('refreshes proactively only when the token is expiring within the skew', function () {
    Http::fake([apiUrl('auth/refresh') => Http::response(refresh_response())]);

    [$refresh, $session] = refresh_action();

    // Fresh token well beyond the skew: untouched, no API call.
    $session->start(new AuthTokens('access-old', 'refresh-old', 'Bearer', 3600));
    expect($refresh->ifExpiringWithin(300))->toBe('access-old');
    Http::assertNothingSent();

    // Token inside the skew window: renewed.
    $session->start(new AuthTokens('access-stale', 'refresh-stale', 'Bearer', 60));
    expect($refresh->ifExpiringWithin(300))->toBe('access-new')
        ->and($session->accessToken())->toBe('access-new');
});

it('does nothing proactively when there is no session', function () {
    Http::fake();

    [$refresh] = refresh_action();

    expect($refresh->ifExpiringWithin(300))->toBeNull();

    Http::assertNothingSent();
});

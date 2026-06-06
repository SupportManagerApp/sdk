<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\AuthenticatedApi;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Exceptions\SessionExpiredException;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\RefreshSession;
use SupportManager\Sdk\Tests\Support\ArraySecureStore;

/**
 * @return array{AuthenticatedApi, AuthSession}
 */
function authenticated_api(): array
{
    $session = new AuthSession(new ArraySecureStore);
    $client = new SupportManagerClient('https://api.test', 'Test Device');
    $api = new AuthenticatedApi($client, $session, new RefreshSession($client, $session), app());

    app()->instance(AuthSession::class, $session);
    app()->instance(AuthenticatedApi::class, $api);

    return [$api, $session];
}

$call = fn ($request) => $request->get('payroll/me');

it('returns the response and never refreshes when the call succeeds', function () use ($call) {
    Http::fake(['api.test/api/v1/payroll/me' => Http::response(['ok' => true], 200)]);

    [$api, $session] = authenticated_api();
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));

    $response = $api->send($call);

    expect($response->status())->toBe(200)
        ->and($response->json('ok'))->toBeTrue();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'auth/refresh'));
});

it('refreshes once and retries with the new token on a 401', function () use ($call) {
    Http::fake([
        'api.test/api/v1/payroll/me' => Http::sequence()
            ->push(['message' => 'Unauthenticated'], 401)
            ->push(['ok' => true], 200),
        'api.test/api/v1/auth/refresh' => Http::response(['data' => [
            'access_token' => 'access-2',
            'refresh_token' => 'refresh-2',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]]),
    ]);

    [$api, $session] = authenticated_api();
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));

    $response = $api->send($call);

    expect($response->status())->toBe(200)
        ->and($session->accessToken())->toBe('access-2');

    // The retry must carry the refreshed bearer token.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'payroll/me')
        && $request->hasHeader('Authorization', 'Bearer access-2'));
});

it('throws and clears the session when the refresh is rejected on a 401', function () use ($call) {
    Http::fake([
        'api.test/api/v1/payroll/me' => Http::response(['message' => 'Unauthenticated'], 401),
        'api.test/api/v1/auth/refresh' => Http::response(['message' => 'expired'], 401),
    ]);

    [$api, $session] = authenticated_api();
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));

    expect(fn () => $api->send($call))->toThrow(SessionExpiredException::class);
    expect($session->check())->toBeFalse();
});

it('throws immediately when there is no session', function () use ($call) {
    Http::fake();

    [$api] = authenticated_api();

    expect(fn () => $api->send($call))->toThrow(SessionExpiredException::class);

    Http::assertNothingSent();
});

it('forwards the team id as a header', function () {
    Http::fake(['api.test/api/v1/payroll/me' => Http::response(['ok' => true], 200)]);

    [$api, $session] = authenticated_api();
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));

    $api->send(fn ($request) => $request->get('payroll/me'), teamId: 42);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('resolves the current team for tenant scoped requests', function () {
    Http::fake([
        'api.test/api/v1/user/teams' => Http::response(['data' => [
            [
                'type' => 'teams',
                'id' => '42',
                'attributes' => ['name' => 'Bletchley Park'],
            ],
        ]]),
        'api.test/api/v1/payroll/me' => Http::response(['ok' => true], 200),
    ]);

    [$api, $session] = authenticated_api();
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));

    $response = $api->tenant(fn ($request) => $request->get('payroll/me'));

    expect($response->status())->toBe(200)
        ->and($session->currentTeamId())->toBe(42);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'user/teams')
        && $request->hasHeader('Authorization', 'Bearer access-1')
        && ! $request->hasHeader('X-Team-ID'));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'payroll/me')
        && $request->hasHeader('Authorization', 'Bearer access-1')
        && $request->hasHeader('X-Team-ID', '42'));
});

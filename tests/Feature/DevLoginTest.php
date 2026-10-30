<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Data\DevLoginTeam;
use SupportManager\Sdk\Data\DevLoginUser;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\DevLoginUnavailableException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

beforeEach(function () {
    $this->client = new SupportManagerClient('https://api.test', 'Test Device');
});

it('lists the developer login accounts with their teams', function () {
    Http::fake([
        'api.test/api/v1/auth/dev/users' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Alex Harbourne',
                    'email' => 'alex@example.com',
                    'teams' => [
                        ['id' => 3, 'slug' => 'te-care', 'name' => 'TE Care', 'role' => 'PA', 'status' => 'active'],
                        ['id' => 4, 'slug' => 'other', 'name' => 'Other', 'role' => null, 'status' => null],
                    ],
                ],
                ['id' => 2, 'name' => 'Bea', 'email' => 'bea@example.com', 'teams' => []],
            ],
        ]),
    ]);

    $users = $this->client->devLoginUsers();

    expect($users)->toHaveCount(2)
        ->and($users[0])->toBeInstanceOf(DevLoginUser::class)
        ->and($users[0]->id)->toBe(1)
        ->and($users[0]->email)->toBe('alex@example.com')
        ->and($users[0]->teams)->toHaveCount(2)
        ->and($users[0]->teams[0])->toBeInstanceOf(DevLoginTeam::class)
        ->and($users[0]->teams[0]->id)->toBe(3)
        ->and($users[0]->teams[0]->slug)->toBe('te-care')
        ->and($users[0]->teams[0]->role)->toBe('PA')
        ->and($users[0]->teams[0]->status)->toBe('active')
        ->and($users[0]->teams[1]->role)->toBeNull()
        ->and($users[1]->teams)->toBe([]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://api.test/api/v1/auth/dev/users'
        && ! $request->hasHeader('Authorization'));
});

it('logs in as a chosen account without a password', function () {
    Http::fake([
        'api.test/api/v1/auth/dev/login' => Http::response([
            'data' => [
                'access_token' => 'dev-access',
                'refresh_token' => 'dev-refresh',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'user' => ['id' => 1, 'name' => 'Alex Harbourne', 'email' => 'alex@example.com'],
            ],
        ]),
    ]);

    $tokens = $this->client->devLogin(1);

    expect($tokens)->toBeInstanceOf(AuthTokens::class)
        ->and($tokens->accessToken)->toBe('dev-access')
        ->and($tokens->user->id)->toBe(1);

    // No device_name unless given: the platform's "Developer Login" default
    // keeps these tokens from colliding with a real device's.
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->data() === ['user_id' => 1]);
});

it('sends the device name for developer login only when given', function () {
    Http::fake(['*' => Http::response([
        'data' => ['access_token' => 'a', 'refresh_token' => 'r', 'token_type' => 'Bearer', 'expires_in' => 3600],
    ])]);

    $this->client->devLogin(5, 'Simulator');

    Http::assertSent(fn (Request $request) => $request['user_id'] === 5
        && $request['device_name'] === 'Simulator');
});

it('raises dev login unavailable on a 404 from either endpoint', function (Closure $call) {
    Http::fake(['*' => Http::response(['message' => ''], 404)]);

    try {
        $call($this->client);
        $this->fail('Expected DevLoginUnavailableException');
    } catch (DevLoginUnavailableException $e) {
        expect($e)->toBeInstanceOf(SupportManagerApiException::class)
            ->and($e->status)->toBe(404);
    }
})->with([
    'users' => [fn (SupportManagerClient $client) => $client->devLoginUsers()],
    'login' => [fn (SupportManagerClient $client) => $client->devLogin(1)],
]);

it('maps an unknown dev login user to a validation exception', function () {
    Http::fake(['*' => Http::response([
        'message' => 'The selected user does not exist.',
        'errors' => ['user_id' => ['The selected user does not exist.']],
    ], 422)]);

    try {
        $this->client->devLogin(999);
        $this->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('user_id'))->toBe('The selected user does not exist.');
    }
});

it('still maps other dev login failures normally', function () {
    Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

    try {
        $this->client->devLoginUsers();
        $this->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e)->not->toBeInstanceOf(DevLoginUnavailableException::class)
            ->and($e->status)->toBe(502);
    }
});

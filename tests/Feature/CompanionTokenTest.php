<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\InvalidCredentialsException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

beforeEach(function () {
    $this->client = new SupportManagerClient('https://api.test', 'Test Device');
});

it('issues companion tokens with the caller\'s bearer token', function () {
    Http::fake([
        apiUrl('auth/companion-token') => Http::response([
            'data' => [
                'access_token' => 'companion-access',
                'refresh_token' => 'companion-refresh',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ],
        ]),
    ]);

    $tokens = $this->client->issueCompanionToken('primary-access', "Ada's Apple Watch");

    expect($tokens)->toBeInstanceOf(AuthTokens::class)
        ->and($tokens->accessToken)->toBe('companion-access')
        ->and($tokens->refreshToken)->toBe('companion-refresh')
        ->and($tokens->expiresIn)->toBe(3600)
        ->and($tokens->user)->toBeNull();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.test/api/v1/auth/companion-token'
        && $request->hasHeader('Authorization', 'Bearer primary-access')
        && $request['device_name'] === "Ada's Apple Watch");
});

it('maps a colliding companion device name to a validation exception', function () {
    Http::fake(['*' => Http::response([
        'message' => 'The device name conflicts with an existing device ("Ada\'s iPhone").',
        'errors' => ['device_name' => ['The device name conflicts with an existing device ("Ada\'s iPhone").']],
    ], 422)]);

    try {
        $this->client->issueCompanionToken('primary-access', "Ada's");
        $this->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('device_name'))->toContain('conflicts with an existing device');
    }
});

it('surfaces the 403 when a companion tries to pair another device', function () {
    Http::fake(['*' => Http::response(['message' => 'A companion device cannot pair further devices.'], 403)]);

    try {
        $this->client->issueCompanionToken('companion-access', 'Another Watch');
        $this->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(403)
            ->and($e->getMessage())->toBe('A companion device cannot pair further devices.');
    }
});

it('throws invalid credentials when the caller\'s token is rejected', function () {
    Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $this->client->issueCompanionToken('expired', 'Watch');
})->throws(InvalidCredentialsException::class, 'Unauthenticated.');

it('revokes companion tokens with a DELETE carrying the device name', function () {
    Http::fake([
        apiUrl('auth/companion-token') => Http::response([
            'data' => ['message' => 'Companion device tokens revoked'],
        ]),
    ]);

    $this->client->revokeCompanionToken('primary-access', "Ada's Apple Watch");

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.test/api/v1/auth/companion-token'
        && $request->hasHeader('Authorization', 'Bearer primary-access')
        && $request['device_name'] === "Ada's Apple Watch");
});

it('surfaces a 404 when no companion tokens exist for the device', function () {
    Http::fake(['*' => Http::response(['message' => 'No tokens found for that device.'], 404)]);

    try {
        $this->client->revokeCompanionToken('primary-access', 'Unknown Watch');
        $this->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(404)
            ->and($e->getMessage())->toBe('No tokens found for that device.');
    }
});

it('refuses to revoke the calling device via the companion endpoint', function () {
    Http::fake(['*' => Http::response(['message' => 'Use the logout endpoint to revoke the current device.'], 422)]);

    $this->client->revokeCompanionToken('primary-access', 'Test Device');
})->throws(ApiValidationException::class, 'Use the logout endpoint to revoke the current device.');

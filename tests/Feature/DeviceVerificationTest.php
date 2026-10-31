<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\DeviceVerificationRequiredException;

/*
 * Device verification, social login and logout, against the shapes in the
 * platform's src/Auth/Api controllers.
 */

function verification_client(): SupportManagerClient
{
    return new SupportManagerClient('https://api.test', 'Test Device');
}

/**
 * @return array<string, mixed>
 */
function issued_tokens(): array
{
    return [
        'access_token' => 'access-123',
        'refresh_token' => 'refresh-456',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'user' => ['id' => 7, 'name' => 'Ada', 'email' => 'ada@test.com'],
    ];
}

it('lists the verification methods for a challenge', function () {
    Http::fake([apiUrl('auth/device/methods') => Http::response(['data' => ['available_methods' => [
        ['method' => 'email', 'label' => 'Email', 'description' => 'Receive a verification code via email', 'masked_destination' => 'a***@test.com'],
        ['method' => 'totp', 'label' => 'Authenticator App', 'description' => 'Use your authenticator app'],
    ]]])]);

    $methods = verification_client()->deviceVerificationMethods('verify-789');

    expect($methods)->toHaveCount(2)
        ->and($methods[0]['method'])->toBe('email');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'VerificationSession verify-789'));
});

it('sends a verification code', function () {
    Http::fake([apiUrl('auth/device/send-code') => Http::response(['data' => [
        'message' => 'Verification code sent', 'method' => 'sms', 'expires_in' => 300,
    ]])]);

    verification_client()->sendDeviceCode('verify-789', 'sms');

    Http::assertSent(fn ($request) => $request['method'] === 'sms'
        && $request->hasHeader('Authorization', 'VerificationSession verify-789'));
});

it('verifies the device and keeps the trusted-device token', function () {
    Http::fake([apiUrl('auth/device/verify') => Http::response([
        'data' => issued_tokens(),
        'device_token' => 'dvc_trusted',
    ])]);

    $tokens = verification_client()->verifyDevice('verify-789', '123456', 'email');

    expect($tokens->accessToken)->toBe('access-123')
        ->and($tokens->deviceToken)->toBe('dvc_trusted');

    Http::assertSent(fn ($request) => $request['code'] === '123456'
        && $request['method'] === 'email'
        && $request['device_name'] === 'Test Device');
});

it('maps a wrong verification code to a validation exception', function () {
    Http::fake([apiUrl('auth/device/verify') => Http::response([
        'message' => 'Invalid verification code',
        'errors' => ['code' => ['Invalid verification code']],
    ], 422)]);

    verification_client()->verifyDevice('verify-789', '000000', 'email');
})->throws(ApiValidationException::class);

it('logs in with a social provider token', function () {
    Http::fake([apiUrl('auth/social/google') => Http::response(['data' => issued_tokens()])]);

    $tokens = verification_client()->loginWithProvider('google', 'ya29.token');

    expect($tokens->accessToken)->toBe('access-123');

    Http::assertSent(fn ($request) => $request['token'] === 'ya29.token'
        && $request['device_name'] === 'Test Device');
});

it('raises the device challenge from a social login', function () {
    Http::fake([apiUrl('auth/social/apple') => Http::response([
        'message' => 'Device verification required',
        'error_code' => 'DEVICE_VERIFICATION_REQUIRED',
        'data' => [
            'verification_token' => 'verify-789',
            'verification_token_expires_in' => 600,
            'available_methods' => [
                ['method' => 'email', 'label' => 'Email', 'description' => 'Receive a verification code via email', 'masked_destination' => 'a***@test.com'],
            ],
            'user' => ['id' => 7, 'name' => 'Ada'],
        ],
    ], 403)]);

    verification_client()->loginWithProvider('apple', 'identity-token');
})->throws(DeviceVerificationRequiredException::class);

it('logs out with the access token', function () {
    Http::fake([apiUrl('auth/logout') => Http::response(['data' => ['message' => 'Successfully logged out']])]);

    verification_client()->logout('access-123');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer access-123'));
});

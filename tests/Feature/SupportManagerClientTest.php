<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\DeviceVerificationRequiredException;
use SupportManager\Sdk\Exceptions\InvalidCredentialsException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

function client(): SupportManagerClient
{
    return new SupportManagerClient('https://api.test', 'Test Device');
}

it('logs in and returns tokens', function () {
    Http::fake([
        'api.test/api/v1/auth/login' => Http::response([
            'data' => [
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-456',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'user' => ['id' => 7, 'name' => 'Ada', 'email' => 'ada@test.com'],
            ],
        ]),
    ]);

    $tokens = client()->login('ada@test.com', 'secret');

    expect($tokens)->toBeInstanceOf(AuthTokens::class)
        ->and($tokens->accessToken)->toBe('access-123')
        ->and($tokens->refreshToken)->toBe('refresh-456')
        ->and($tokens->user->email)->toBe('ada@test.com');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.test/api/v1/auth/login'
        && $request['email'] === 'ada@test.com'
        && $request['device_name'] === 'Test Device');
});

it('sends the device token as a header when provided', function () {
    Http::fake([
        'api.test/api/v1/auth/login' => Http::response([
            'data' => [
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-456',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ],
        ]),
    ]);

    client()->login('ada@test.com', 'secret', deviceToken: 'trusted-device-1');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Device-Token', 'trusted-device-1'));
});

it('throws on invalid credentials', function () {
    Http::fake(['*' => Http::response(['message' => 'Invalid credentials'], 401)]);

    client()->login('ada@test.com', 'wrong');
})->throws(InvalidCredentialsException::class, 'Invalid credentials');

it('surfaces the status when the server errors without a message', function () {
    Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

    try {
        client()->login('ada@test.com', 'secret');
        $this->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(502)
            ->and($e->getMessage())->toContain('HTTP 502')
            ->and($e->body)->toContain('Bad Gateway');
    }
});

it('throws a device verification challenge on 403', function () {
    Http::fake(['*' => Http::response([
        'message' => 'Device verification required',
        'error_code' => 'DEVICE_VERIFICATION_REQUIRED',
        'data' => [
            'verification_token' => 'verify-789',
            'verification_token_expires_in' => 300,
            'available_methods' => [
                ['method' => 'email', 'label' => 'Email', 'description' => 'Send via email', 'masked_destination' => 'a***@test.com'],
            ],
            'user' => ['id' => 7, 'name' => 'Ada'],
        ],
    ], 403)]);

    try {
        client()->login('ada@test.com', 'secret');
        $this->fail('Expected DeviceVerificationRequiredException');
    } catch (DeviceVerificationRequiredException $e) {
        expect($e->challenge->verificationToken)->toBe('verify-789')
            ->and($e->challenge->methods)->toHaveCount(1)
            ->and($e->challenge->methods[0]->method)->toBe('email');
    }
});

it('requests a password reset code', function () {
    Http::fake([
        'api.test/api/v1/auth/password-reset-request' => Http::response([
            'data' => ['message' => 'Sent', 'expires_in' => 900, 'delivery_method' => 'email'],
        ]),
    ]);

    $result = client()->requestPasswordReset('ada@test.com');

    expect($result['delivery_method'])->toBe('email');
    Http::assertSent(fn ($request) => $request['email'] === 'ada@test.com');
});

it('verifies an OTP and returns the reset token', function () {
    Http::fake([
        'api.test/api/v1/auth/verify-otp' => Http::response([
            'data' => ['message' => 'OTP verified successfully', 'reset_token' => 42, 'expires_in' => 600],
        ]),
    ]);

    $result = client()->verifyOtp('ada@test.com', '123456');

    expect($result['reset_token'])->toBe(42);
});

it('maps a 422 to a validation exception', function () {
    Http::fake(['*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['otp' => ['The code you entered is incorrect.']],
    ], 422)]);

    try {
        client()->verifyOtp('ada@test.com', '000000');
        $this->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('otp'))->toBe('The code you entered is incorrect.');
    }
});

it('resets the password with the reset token', function () {
    Http::fake([
        'api.test/api/v1/auth/reset-password' => Http::response([
            'data' => ['message' => 'Your password has been reset successfully'],
        ]),
    ]);

    client()->resetPassword(42, 'new-password', 'new-password');

    Http::assertSent(fn ($request) => $request['reset_token'] === 42
        && $request['password'] === 'new-password'
        && $request['password_confirmation'] === 'new-password');
});

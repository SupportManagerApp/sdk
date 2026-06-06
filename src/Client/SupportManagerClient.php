<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Concerns\MapsApiErrors;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\DeviceVerificationRequiredException;
use SupportManager\Sdk\Exceptions\InvalidCredentialsException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

/**
 * HTTP client for the SupportManager backend API (v1).
 *
 * Mirrors the SupportManager\Auth\Api endpoints: token-based auth via Laravel
 * Sanctum, with a device-verification step for untrusted devices. Auth
 * endpoints live on the central domain under /api/v1/auth/*; tenant-scoped
 * module endpoints additionally require the X-Team-ID header (see
 * {@see self::authenticated()}).
 */
final class SupportManagerClient
{
    use MapsApiErrors;

    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $defaultDeviceName,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
        $this->baseUrl = mb_rtrim($baseUrl, '/').'/api/v1';
    }

    // ───────────────────────────── Authentication ─────────────────────────────

    /**
     * Authenticate with email and password.
     *
     * Pass a previously issued $deviceToken (from {@see self::verifyDevice()})
     * to identify this device as trusted and skip the verification step.
     *
     * @throws InvalidCredentialsException on bad credentials (401)
     * @throws DeviceVerificationRequiredException when the device is untrusted (403)
     * @throws ApiValidationException on invalid input (422)
     * @throws SupportManagerApiException on any other failure
     */
    public function login(string $email, string $password, ?string $deviceName = null, ?string $deviceToken = null): AuthTokens
    {
        $data = $this->postJson('auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => $deviceName ?? $this->defaultDeviceName,
        ], $deviceToken !== null ? ['X-Device-Token' => $deviceToken] : []);

        return AuthTokens::fromArray($data['data']);
    }

    /**
     * Authenticate via a social provider's access token (Google, Microsoft, Apple).
     *
     * @throws DeviceVerificationRequiredException when the device is untrusted (403)
     * @throws ApiValidationException when the provider/token is invalid (422)
     * @throws SupportManagerApiException on any other failure
     */
    public function loginWithProvider(string $provider, string $providerToken, ?string $deviceName = null): AuthTokens
    {
        $data = $this->postJson("auth/social/{$provider}", [
            'token' => $providerToken,
            'device_name' => $deviceName ?? $this->defaultDeviceName,
        ]);

        return AuthTokens::fromArray($data['data']);
    }

    /**
     * Exchange a refresh token for a fresh access/refresh pair.
     *
     * @throws InvalidCredentialsException when the refresh token is invalid/expired (401)
     * @throws SupportManagerApiException on any other failure
     */
    public function refresh(string $refreshToken): AuthTokens
    {
        $data = $this->postJson('auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        return AuthTokens::fromArray($data['data']);
    }

    /**
     * Revoke the current device's tokens.
     *
     * @throws SupportManagerApiException on failure
     */
    public function logout(string $accessToken): void
    {
        $this->postJson('auth/logout', [], ['Authorization' => "Bearer {$accessToken}"]);
    }

    // ──────────────────────────── Device verification ────────────────────────────

    /**
     * List the verification methods available for an in-progress challenge.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deviceVerificationMethods(string $verificationToken): array
    {
        $data = $this->getJson('auth/device/methods', $this->verificationHeaders($verificationToken));

        return $data['data']['available_methods'] ?? [];
    }

    /**
     * Send a verification code via 'sms' or 'email'.
     */
    public function sendDeviceCode(string $verificationToken, string $method): void
    {
        $this->postJson('auth/device/send-code', ['method' => $method], $this->verificationHeaders($verificationToken));
    }

    /**
     * Verify a device code and complete login, returning the issued tokens.
     *
     * @throws ApiValidationException when the code is wrong/expired (422)
     * @throws SupportManagerApiException on any other failure
     */
    public function verifyDevice(string $verificationToken, string $code, string $method, ?string $deviceName = null): AuthTokens
    {
        $data = $this->postJson('auth/device/verify', [
            'code' => $code,
            'method' => $method,
            'device_name' => $deviceName ?? $this->defaultDeviceName,
        ], $this->verificationHeaders($verificationToken));

        return AuthTokens::fromArray($data['data'], deviceToken: $data['device_token'] ?? null);
    }

    // ───────────────────────────── Password reset ─────────────────────────────

    /**
     * Request a password reset code. Always succeeds (the API does not reveal
     * whether the email exists), returning the delivery method and expiry.
     *
     * @return array{message: string, expires_in: int, delivery_method: string}
     */
    public function requestPasswordReset(string $email, ?string $deliveryMethod = null): array
    {
        $payload = ['email' => $email];

        if ($deliveryMethod !== null) {
            $payload['delivery_method'] = $deliveryMethod;
        }

        return $this->postJson('auth/password-reset-request', $payload)['data'];
    }

    /**
     * Verify a password reset OTP, returning the reset token payload used to
     * complete the reset.
     *
     * @return array<string, mixed>
     *
     * @throws ApiValidationException when the OTP is wrong/expired (422)
     */
    public function verifyOtp(string $email, string $otp): array
    {
        return $this->postJson('auth/verify-otp', [
            'email' => $email,
            'otp' => $otp,
        ])['data'];
    }

    /**
     * Complete a password reset using the token from {@see self::verifyOtp()}.
     *
     * @throws ApiValidationException when the token is invalid/used (422)
     */
    public function resetPassword(int|string $resetToken, string $password, string $passwordConfirmation): void
    {
        $this->postJson('auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]);
    }

    // ─────────────────────── Authenticated module requests ───────────────────────

    /**
     * A pre-configured request for tenant-scoped module endpoints, e.g.
     * `$client->authenticated($token, $teamId)->get('payroll/...')`. The path
     * is relative to /api/v1.
     */
    public function authenticated(string $accessToken, ?int $teamId = null): PendingRequest
    {
        $request = $this->pending()->withToken($accessToken);

        if ($teamId !== null) {
            $request->withHeaders(['X-Team-ID' => (string) $teamId]);
        }

        return $request;
    }

    // ───────────────────────────────── Internals ─────────────────────────────────

    private function pending(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    /**
     * @return array<string, string>
     */
    private function verificationHeaders(string $verificationToken): array
    {
        return ['Authorization' => "VerificationSession {$verificationToken}"];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function postJson(string $uri, array $data = [], array $headers = []): array
    {
        return $this->handle($this->pending()->withHeaders($headers), 'post', $uri, $data);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function getJson(string $uri, array $headers = []): array
    {
        return $this->handle($this->pending()->withHeaders($headers), 'get', $uri);
    }

    /**
     * Execute the request, normalising connection failures and error
     * responses into typed exceptions, and return the decoded JSON body.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handle(PendingRequest $request, string $method, string $uri, array $data = []): array
    {
        try {
            $response = $method === 'get'
                ? $request->get($uri)
                : $request->post($uri, $data);
        } catch (ConnectionException $e) {
            throw $this->throwForConnectionFailure($e, $this->baseUrl.'/'.$uri);
        }

        $this->throwForErrorResponse($response, $this->baseUrl.'/'.$uri);

        return $response->json() ?? [];
    }
}

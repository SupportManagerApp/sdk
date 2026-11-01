<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\TransportSharing;
use GuzzleHttp\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use SupportManager\Sdk\Concerns\MapsApiErrors;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Data\DevLoginUser;
use SupportManager\Sdk\Enums\Security\SocialProvider;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\DeviceVerificationRequiredException;
use SupportManager\Sdk\Exceptions\DevLoginUnavailableException;
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

    /**
     * The Guzzle handler every request shares; see {@see self::handler()}.
     *
     * @var (callable(RequestInterface, array<string, mixed>): \GuzzleHttp\Promise\PromiseInterface)|null
     */
    private $handler = null;

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
     * As with {@see self::login()}, pass a previously issued $deviceToken (from
     * {@see self::verifyDevice()}) to identify this device as trusted and skip
     * the verification step. It goes out as the `X-Device-Token` header, which
     * the platform's social login checks just as the password login does.
     *
     * @throws DeviceVerificationRequiredException when the device is untrusted (403)
     * @throws ApiValidationException when the provider/token is invalid (422)
     * @throws SupportManagerApiException on any other failure
     */
    public function loginWithProvider(SocialProvider|string $provider, string $providerToken, ?string $deviceName = null, ?string $deviceToken = null): AuthTokens
    {
        $provider = $provider instanceof SocialProvider ? $provider->value : $provider;

        $data = $this->postJson("auth/social/{$provider}", [
            'token' => $providerToken,
            'device_name' => $deviceName ?? $this->defaultDeviceName,
        ], $deviceToken !== null ? ['X-Device-Token' => $deviceToken] : []);

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
        $this->postJson('auth/logout', [], $this->bearerHeaders($accessToken));
    }

    // ──────────────────────────── Companion devices ────────────────────────────

    /**
     * Issue a separate token pair for a companion device paired with the
     * calling device (e.g. an Apple Watch paired with the iPhone holding
     * $accessToken), so it can reach the API on its own.
     *
     * The companion inherits the caller's device trust — no verification step
     * runs. Its tokens carry the `companion` ability and a 7-day refresh
     * lifetime, which {@see self::refresh()} preserves. The returned tokens
     * carry no user.
     *
     * $deviceName must not be a prefix of, nor be prefixed by, any existing
     * device name on the account: tokens are revoked by name prefix, so a
     * colliding name would sign the other device out.
     *
     * @throws InvalidCredentialsException when $accessToken is missing/invalid (401)
     * @throws ApiValidationException when $deviceName collides with an existing device (422)
     * @throws SupportManagerApiException on any other failure, including a 403 when
     *                                    the caller is itself a companion device or
     *                                    the token lacks the `access` ability
     */
    public function issueCompanionToken(string $accessToken, string $deviceName): AuthTokens
    {
        $data = $this->postJson('auth/companion-token', [
            'device_name' => $deviceName,
        ], $this->bearerHeaders($accessToken));

        return AuthTokens::fromArray($data['data']);
    }

    /**
     * Revoke a companion device's tokens without disturbing the calling
     * device's session — e.g. when the watch is lost. $deviceName must match
     * the name the companion tokens were issued under exactly. To revoke the
     * calling device itself use {@see self::logout()}.
     *
     * @throws InvalidCredentialsException when $accessToken is missing/invalid (401)
     * @throws ApiValidationException when $deviceName is the calling device's own name (422)
     * @throws SupportManagerApiException on any other failure, including a 404 when
     *                                    no tokens exist for $deviceName
     */
    public function revokeCompanionToken(string $accessToken, string $deviceName): void
    {
        $this->deleteJson('auth/companion-token', [
            'device_name' => $deviceName,
        ], $this->bearerHeaders($accessToken));
    }

    // ───────────────────────────── Developer login ─────────────────────────────

    /**
     * List the accounts available for password-free developer login, with
     * their team memberships, for a "sign in as…" picker.
     *
     * NON-PRODUCTION ONLY. The platform registers these routes solely on
     * environments with developer login enabled (local, develop, development,
     * feature, testing — never production or a production host); everywhere
     * else they do not exist. Requires no authentication.
     *
     * @return list<DevLoginUser>
     *
     * @throws DevLoginUnavailableException when the environment has no developer login (404)
     * @throws SupportManagerApiException on any other failure
     */
    public function devLoginUsers(): array
    {
        $data = $this->handleDevLogin('get', 'auth/dev/users');

        return array_values(array_map(
            static fn (array $user): DevLoginUser => DevLoginUser::fromArray($user),
            array_filter(is_array($data['data'] ?? null) ? $data['data'] : [], is_array(...)),
        ));
    }

    /**
     * Issue a token pair for the chosen account ({@see self::devLoginUsers()})
     * with no password and no device verification. The tokens behave exactly
     * like a real login's, including {@see self::refresh()}.
     *
     * NON-PRODUCTION ONLY — see {@see self::devLoginUsers()}. Never ship a
     * build that relies on this.
     *
     * Unlike {@see self::login()}, $deviceName does not fall back to the
     * configured device name: when omitted the platform issues the tokens
     * under "Developer Login", deliberately distinct from any real device so
     * its prefix-based token revocation cannot sign a genuine device out.
     *
     * @throws DevLoginUnavailableException when the environment has no developer login (404)
     * @throws ApiValidationException when the user id is unknown (422)
     * @throws SupportManagerApiException on any other failure
     */
    public function devLogin(int $userId, ?string $deviceName = null): AuthTokens
    {
        $payload = ['user_id' => $userId];

        if ($deviceName !== null) {
            $payload['device_name'] = $deviceName;
        }

        $data = $this->handleDevLogin('post', 'auth/dev/login', $payload);

        return AuthTokens::fromArray($data['data']);
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
     * `$client->authenticated($token, $teamId)->get('payroll/payslips')`. The path
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

    /**
     * Give a multipart body its `multipart/form-data; boundary=…` Content-Type.
     *
     * {@see self::pending()} pins `Content-Type: application/json`, and neither
     * `attach()` nor Guzzle replaces an explicit header — so without this an
     * uploaded file goes out labelled as JSON and the server sees no file.
     */
    private static function multipartContentType(RequestInterface $request): RequestInterface
    {
        $body = $request->getBody();

        return $body instanceof MultipartStream
            ? $request->withHeader('Content-Type', 'multipart/form-data; boundary='.$body->getBoundary())
            : $request;
    }

    // ───────────────────────────────── Internals ─────────────────────────────────

    private function pending(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->setHandler($this->handler())
            ->acceptJson()
            ->asJson()
            ->withRequestMiddleware(self::multipartContentType(...))
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    /**
     * One Guzzle handler for the client's lifetime, so requests reuse open
     * connections, TLS sessions and DNS lookups instead of each building a
     * fresh curl handle. Laravel still pushes its own middleware (including
     * `Http::fake()` stubs and recording) on top for every request, so faking
     * is unaffected. Concurrent slots rely on it too: their transfers share
     * this handler's curl multi handle, which is what lets them overlap.
     *
     * Where Guzzle supports it (7.11+), the handler asks for persistent
     * transport sharing, which on PHP 8.5+ keeps connections and TLS sessions
     * across PHP requests in the same process, and falls back to sharing
     * within this handler elsewhere. Only connections, TLS sessions and DNS
     * are shared, never cookies or credentials.
     *
     * @return callable(RequestInterface, array<string, mixed>): \GuzzleHttp\Promise\PromiseInterface
     */
    private function handler(): callable
    {
        return $this->handler ??= Utils::chooseHandler(
            class_exists(TransportSharing::class) ? ['transport_sharing' => TransportSharing::PERSISTENT_PREFER] : [],
        );
    }

    /**
     * @return array<string, string>
     */
    private function verificationHeaders(string $verificationToken): array
    {
        return ['Authorization' => "VerificationSession {$verificationToken}"];
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(string $accessToken): array
    {
        return ['Authorization' => "Bearer {$accessToken}"];
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
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function deleteJson(string $uri, array $data = [], array $headers = []): array
    {
        return $this->handle($this->pending()->withHeaders($headers), 'delete', $uri, $data);
    }

    /**
     * Like {@see self::handle()}, but a 404 means the environment has no
     * developer login (the routes are unregistered) rather than a broken API
     * URL, so it is raised as {@see DevLoginUnavailableException} and not
     * logged as an API error.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleDevLogin(string $method, string $uri, array $data = []): array
    {
        $response = $this->send($this->pending(), $method, $uri, $data);

        if ($response->status() === 404) {
            throw new DevLoginUnavailableException(body: $response->body());
        }

        $this->throwForErrorResponse($response, $this->baseUrl.'/'.$uri);

        return $response->json() ?? [];
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
        $response = $this->send($request, $method, $uri, $data);

        $this->throwForErrorResponse($response, $this->baseUrl.'/'.$uri);

        return $response->json() ?? [];
    }

    /**
     * Execute the request, normalising connection failures into a
     * {@see SupportManagerApiException}, and return the raw response.
     *
     * @param  array<string, mixed>  $data
     */
    private function send(PendingRequest $request, string $method, string $uri, array $data = []): Response
    {
        try {
            return match ($method) {
                'get' => $request->get($uri),
                'delete' => $request->delete($uri, $data),
                default => $request->post($uri, $data),
            };
        } catch (ConnectionException $e) {
            throw $this->throwForConnectionFailure($e, $this->baseUrl.'/'.$uri);
        }
    }
}

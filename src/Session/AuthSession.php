<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Session;

use Closure;
use SupportManager\Sdk\Contracts\SecureStore;
use SupportManager\Sdk\Data\AuthenticatedUser;
use SupportManager\Sdk\Data\AuthTokens;

/**
 * The device's authenticated session, persisted in secure storage.
 *
 * Holds the access/refresh tokens, the cached user, and a long-lived
 * "device token" that marks this device as trusted with the backend (sent as
 * the X-Device-Token header on subsequent logins to skip re-verification).
 * Signing out clears the session but deliberately keeps the
 * device token, so a returning user is not forced through device verification
 * again.
 */
final class AuthSession
{
    private const ACCESS_TOKEN = 'auth.access_token';

    private const REFRESH_TOKEN = 'auth.refresh_token';

    private const DEVICE_TOKEN = 'auth.device_token';

    private const USER = 'auth.user';

    private const CURRENT_TEAM_ID = 'auth.current_team_id';

    private const EXPIRES_AT = 'auth.expires_at';

    private const BIOMETRIC_ENABLED = 'auth.biometric_enabled';

    /**
     * Session facts kept in memory for this process; see {@see self::remember()}.
     *
     * @var array<string, mixed>
     */
    private array $remembered = [];

    public function __construct(private readonly SecureStore $store) {}

    /**
     * Persist a freshly issued token pair.
     *
     * A null device token leaves any previously trusted device token intact;
     * an issued one is only stored when $rememberDevice is true.
     */
    public function start(AuthTokens $tokens, bool $rememberDevice = true): void
    {
        $this->store->put(self::ACCESS_TOKEN, $tokens->accessToken);
        $this->store->put(self::REFRESH_TOKEN, $tokens->refreshToken);
        $this->store->put(self::EXPIRES_AT, (string) (time() + $tokens->expiresIn));

        if ($tokens->user !== null && $tokens->user->id !== $this->user()?->id) {
            $this->forgetRemembered();
        }

        if ($tokens->user !== null) {
            $this->store->put(self::USER, json_encode([
                'id' => $tokens->user->id,
                'name' => $tokens->user->name,
                'email' => $tokens->user->email,
            ], JSON_THROW_ON_ERROR));
        }

        if ($rememberDevice && $tokens->deviceToken !== null) {
            $this->store->put(self::DEVICE_TOKEN, $tokens->deviceToken);
        }
    }

    /**
     * Whether a usable session (an access token) is currently stored.
     */
    public function check(): bool
    {
        return $this->accessToken() !== null;
    }

    public function accessToken(): ?string
    {
        return $this->store->get(self::ACCESS_TOKEN);
    }

    public function refreshToken(): ?string
    {
        return $this->store->get(self::REFRESH_TOKEN);
    }

    /**
     * Unix timestamp at which the stored access token expires, or null when no
     * expiry has been recorded.
     */
    public function expiresAt(): ?int
    {
        $expiresAt = $this->store->get(self::EXPIRES_AT);

        return $expiresAt === null ? null : (int) $expiresAt;
    }

    /**
     * Whether the access token has expired (or is missing an expiry). Used by
     * the proactive refresh to avoid spending a refresh token while the current
     * one is still good.
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt === null || $expiresAt <= time();
    }

    /**
     * Whether the access token expires within the next $seconds. Lets the
     * proactive (background) refresh renew shortly before expiry rather than
     * waiting for the first reactive 401.
     */
    public function expiresWithin(int $seconds): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt === null || $expiresAt <= time() + $seconds;
    }

    public function deviceToken(): ?string
    {
        return $this->store->get(self::DEVICE_TOKEN);
    }

    /**
     * The team currently acting as this session's tenant, sent as X-Team-ID on
     * tenant-scoped module requests. Resolved lazily by {@see CurrentTeam} from
     * the user's teams and cached here for the life of the session.
     */
    public function currentTeamId(): ?int
    {
        $teamId = $this->store->get(self::CURRENT_TEAM_ID);

        return $teamId === null ? null : (int) $teamId;
    }

    public function setCurrentTeamId(int $teamId): void
    {
        if ($teamId !== $this->currentTeamId()) {
            $this->forgetRemembered();
        }

        $this->store->put(self::CURRENT_TEAM_ID, (string) $teamId);
    }

    /**
     * Whether the user has opted into biometric (or equivalent local) unlock on
     * this device.
     */
    public function biometricEnabled(): bool
    {
        return $this->store->get(self::BIOMETRIC_ENABLED) === '1';
    }

    /**
     * Whether the user has been asked about biometric unlock yet. Lets the
     * enrolment prompt show once rather than on every sign-in.
     */
    public function biometricChoiceMade(): bool
    {
        return $this->store->get(self::BIOMETRIC_ENABLED) !== null;
    }

    public function setBiometricEnabled(bool $enabled): void
    {
        $this->store->put(self::BIOMETRIC_ENABLED, $enabled ? '1' : '0');
    }

    public function user(): ?AuthenticatedUser
    {
        $raw = $this->store->get(self::USER);

        if ($raw === null) {
            return null;
        }

        /** @var array{id: int, name: string, email: string}|null $data */
        $data = json_decode($raw, true);

        return is_array($data) ? AuthenticatedUser::fromArray($data) : null;
    }

    /**
     * Clear the session on sign-out. The trusted device token and the
     * biometric-unlock preference are kept, so the next sign-in skips both
     * device verification and the enrolment prompt.
     */
    public function forget(): void
    {
        $this->store->forget(self::ACCESS_TOKEN);
        $this->store->forget(self::REFRESH_TOKEN);
        $this->store->forget(self::USER);
        $this->store->forget(self::CURRENT_TEAM_ID);
        $this->store->forget(self::EXPIRES_AT);
        $this->forgetRemembered();
    }

    /**
     * A small, rarely changing fact about this user in the current team (such
     * as their capabilities), computed once and then kept in memory for the
     * rest of this process. It is never written to secure storage.
     *
     * Remembered facts are dropped on sign-out, when the current team
     * changes, and when a different user signs in; a token refresh keeps
     * them. Call {@see self::forgetRemembered()} to drop them sooner, e.g.
     * on pull-to-refresh.
     *
     * @template V
     *
     * @param  Closure(): V  $compute
     * @return V
     */
    public function remember(string $key, Closure $compute): mixed
    {
        if (array_key_exists($key, $this->remembered)) {
            return $this->remembered[$key];
        }

        $value = $compute();
        $this->remembered[$key] = $value;

        return $value;
    }

    /**
     * Drop every fact kept by {@see self::remember()}, or only $key.
     */
    public function forgetRemembered(?string $key = null): void
    {
        if ($key === null) {
            $this->remembered = [];

            return;
        }

        unset($this->remembered[$key]);
    }
}

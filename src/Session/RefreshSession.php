<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Session;

use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Exceptions\InvalidCredentialsException;

/**
 * Exchange the stored refresh token for a fresh access/refresh pair and persist
 * it, reusing {@see AuthSession::start()} so the renewed pair lands on the same
 * secure-storage path as a first sign-in (token rotation included).
 *
 * Shared by both refresh paths: the reactive {@see \SupportManager\Sdk\Client\AuthenticatedApi}
 * (on a mid-session 401) and any proactive/background refresh a host app runs.
 */
final readonly class RefreshSession
{
    public function __construct(
        private SupportManagerClient $client,
        private AuthSession $session,
    ) {}

    /**
     * Returns the new access token on success, or null when there is nothing to
     * refresh (no stored refresh token) or the refresh token was rejected. In
     * the rejected case the session is cleared, since it can never recover.
     */
    public function __invoke(): ?string
    {
        $refreshToken = $this->session->refreshToken();

        if ($refreshToken === null) {
            return null;
        }

        try {
            $tokens = $this->client->refresh($refreshToken);
        } catch (InvalidCredentialsException) {
            $this->session->forget();

            return null;
        }

        $this->session->start($tokens);

        return $tokens->accessToken;
    }

    /**
     * Renew the access token only when it expires within $skewSeconds, leaving a
     * still-fresh token untouched. Returns the (possibly unchanged) access
     * token, or null when there is no session or the refresh was rejected.
     *
     * Lets a host app's background scheduler renew shortly before expiry without
     * spending a refresh token on every run. The reactive 401 path in
     * {@see \SupportManager\Sdk\Client\AuthenticatedApi} remains the guarantee.
     */
    public function ifExpiringWithin(int $skewSeconds): ?string
    {
        if (! $this->session->check()) {
            return null;
        }

        if (! $this->session->expiresWithin($skewSeconds)) {
            return $this->session->accessToken();
        }

        return ($this)();
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Exceptions\SessionExpiredException;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;
use SupportManager\Sdk\Session\RefreshSession;

/**
 * Reactive entry point for tenant-scoped module requests.
 *
 * Wraps {@see SupportManagerClient::authenticated()} with transparent token
 * renewal: on a mid-session 401 it refreshes the access token once, then
 * retries the same request. If the refresh fails the session is already
 * cleared, so it raises {@see SessionExpiredException} for the UI to redirect.
 *
 * Feature pages should issue every authenticated call through this service so
 * they all inherit the renewal behaviour rather than handling 401s themselves.
 */
final readonly class AuthenticatedApi
{
    public function __construct(
        private SupportManagerClient $client,
        private AuthSession $session,
        private RefreshSession $refresh,
        private Container $container,
    ) {}

    /**
     * Run an authenticated request, refreshing and retrying once on a 401.
     *
     * The callback receives a {@see PendingRequest} already carrying the access
     * token (and X-Team-ID when $teamId is given) and must return the
     * {@see Response}, e.g. `fn ($request) => $request->get('payroll/me')`.
     *
     * @param  Closure(PendingRequest): Response  $callback
     *
     * @throws SessionExpiredException when there is no session, or the refresh
     *                                 token is rejected on the retry path.
     */
    public function send(Closure $callback, ?int $teamId = null): Response
    {
        $accessToken = $this->session->accessToken();

        if ($accessToken === null) {
            throw SessionExpiredException::unrecoverable();
        }

        $response = $callback($this->client->authenticated($accessToken, $teamId));

        if ($response->status() !== 401) {
            return $response;
        }

        $refreshedToken = ($this->refresh)();

        if ($refreshedToken === null) {
            throw SessionExpiredException::unrecoverable();
        }

        return $callback($this->client->authenticated($refreshedToken, $teamId));
    }

    /**
     * Run a request against a tenant-scoped endpoint, resolving and forwarding
     * the current team automatically.
     *
     * @param  Closure(PendingRequest): Response  $callback
     *
     * @throws SessionExpiredException when there is no session, or the session
     *                                 cannot be renewed while resolving the team.
     */
    public function tenant(Closure $callback): Response
    {
        $teamId = $this->container->make(CurrentTeam::class)->id();

        return $this->send($callback, $teamId);
    }
}

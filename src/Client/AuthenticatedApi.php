<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Contracts\ResponseCache;
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
     * {@see Response}, e.g. `fn ($request) => $request->get('payroll/payslips')`.
     *
     * @param  Closure(PendingRequest): Response  $callback
     *
     * @throws SessionExpiredException when there is no session, or the refresh
     *                                 token is rejected on the retry path.
     * @throws \Illuminate\Http\Client\ConnectionException when the API is unreachable.
     */
    public function send(Closure $callback, ?int $teamId = null): Response
    {
        $accessToken = $this->session->accessToken();

        if ($accessToken === null) {
            throw SessionExpiredException::unrecoverable();
        }

        $response = $this->dispatch($callback, $accessToken, $teamId);

        if ($response->status() !== 401) {
            return $response;
        }

        $renewedToken = $this->renew($accessToken);

        if ($renewedToken === null) {
            throw SessionExpiredException::unrecoverable();
        }

        return $this->dispatch($callback, $renewedToken, $teamId);
    }

    /**
     * The session these requests authenticate with.
     */
    public function session(): AuthSession
    {
        return $this->session;
    }

    /**
     * Run a request against a tenant-scoped endpoint, resolving and forwarding
     * the current team automatically.
     *
     * @param  Closure(PendingRequest): Response  $callback
     *
     * @throws SessionExpiredException when there is no session, or the session
     *                                 cannot be renewed while resolving the team.
     * @throws \Illuminate\Http\Client\ConnectionException when the API is unreachable.
     */
    public function tenant(Closure $callback): Response
    {
        $teamId = $this->container->make(CurrentTeam::class)->id();

        return $this->send($callback, $teamId);
    }

    /**
     * A working access token after $rejected drew a 401, or null when the
     * session cannot be renewed.
     *
     * When the stored token has already moved on from $rejected, another call
     * renewed the session while this one was in flight (a sibling in
     * {@see \SupportManager\Sdk\SupportManager::concurrently()} whose 401
     * came back first), so its token is reused. Refreshing again would spend a
     * refresh token the platform has already rotated out, and that rejection
     * clears the session, signing the user out.
     */
    private function renew(string $rejected): ?string
    {
        $current = $this->session->accessToken();

        if ($current !== null && $current !== $rejected) {
            return $current;
        }

        return ($this->refresh)();
    }

    /**
     * Make one attempt at the request. Inside a concurrent slot the request
     * joins the batch instead of blocking on its own ({@see Concurrency}).
     *
     * @param  Closure(PendingRequest): Response  $callback
     */
    private function dispatch(Closure $callback, string $accessToken, ?int $teamId): Response
    {
        $request = $this->client->authenticated($accessToken, $teamId);
        $userId = $this->session->user()?->id;

        if ($userId !== null && $this->container->bound(ResponseCache::class)) {
            $request->withMiddleware(ConditionalRequests::middleware($this->container->make(ResponseCache::class), (string) $userId));
        }

        if (Concurrency::inSlot()) {
            $request->withMiddleware(Concurrency::middleware());
        }

        return $callback($request);
    }
}

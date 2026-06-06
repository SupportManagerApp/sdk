<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Session;

use SupportManager\Sdk\Client\AuthenticatedApi;
use SupportManager\Sdk\Exceptions\SessionExpiredException;

/**
 * Resolves the team that acts as the session's tenant for tenant-scoped module
 * requests (sent as the X-Team-ID header).
 *
 * The backend has no "default team" endpoint, so the first time a team is
 * needed we fetch the user's teams (an auth-only, non-tenant-scoped call) and
 * adopt the first one as the default, caching its id in {@see AuthSession} for
 * the life of the session. Sign-out clears it along with the rest of the
 * session, so the next user resolves their own default afresh.
 */
final readonly class CurrentTeam
{
    public function __construct(
        private AuthenticatedApi $api,
        private AuthSession $session,
    ) {}

    /**
     * The current team id, or null when the user belongs to no teams.
     *
     * @throws SessionExpiredException when the session cannot be renewed while
     *                                 fetching the user's teams.
     */
    public function id(): ?int
    {
        $cached = $this->session->currentTeamId();

        if ($cached !== null) {
            return $cached;
        }

        $teams = $this->api->send(fn ($request) => $request->get('user/teams'))->json('data', []);

        $teamId = $teams[0]['id'] ?? null;

        if ($teamId === null) {
            return null;
        }

        $this->session->setCurrentTeamId((int) $teamId);

        return (int) $teamId;
    }
}

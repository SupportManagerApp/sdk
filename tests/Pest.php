<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Boot a real, container-wired session with a cached current team so tenant
 * requests skip the user/teams lookup and carry X-Team-ID straight away.
 *
 * Shared by every module's feature tests — do not redefine it per file (Pest
 * loads all test files together, and a duplicate global function would fatal).
 */
function bootTenantSession(int $teamId = 42): void
{
    $session = app(AuthSession::class);
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));
    $session->setCurrentTeamId($teamId);
}

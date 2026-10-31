<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Teams;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Teams\Team;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The teams the authenticated user belongs to.
 *
 * These endpoints are authenticated but NOT tenant-scoped (no `X-Team-ID`), so
 * like {@see \SupportManager\Sdk\Resources\Profile\ProfileResource} they go
 * through {@see \SupportManager\Sdk\Client\AuthenticatedApi::send()} directly
 * rather than the tenant-scoped base helpers — listing teams is itself how the
 * tenant is chosen. To read or change the *active* team, use
 * {@see \SupportManager\Sdk\Session\CurrentTeam} via `SupportManager::team()`.
 *
 * ```php
 * SupportManager::teams()->all()->items();
 * SupportManager::teams()->count();
 * ```
 *
 * @extends Resource<Team>
 */
final class TeamsResource extends Resource
{
    /**
     * Every active team the user belongs to. The collection is returned whole
     * (the endpoint is not paginated), wrapped in {@see Paginated} for
     * consistency with the other resources.
     *
     * @return Paginated<Team>
     */
    public function all(): Paginated
    {
        return $this->paginated(
            $this->sendUntenanted(fn (PendingRequest $request): Response => $request->get($this->path())),
        );
    }

    /**
     * The number of active teams the user belongs to.
     */
    public function count(): int
    {
        $data = $this->raw($this->sendUntenanted(
            fn (PendingRequest $request): Response => $request->get("{$this->path()}/count"),
        ));

        return (int) ($data['count'] ?? 0);
    }

    protected function path(): string
    {
        return 'user/teams';
    }

    protected function hydrate(ResourceObject $object): Team
    {
        return Team::fromArray($object->flat());
    }

    /**
     * Issue an authenticated but NON-tenant request (no X-Team-ID), with the same
     * 401-refresh-and-retry behaviour the tenant helpers get.
     *
     * @param  Closure(PendingRequest): Response  $callback
     */
    private function sendUntenanted(Closure $callback): Response
    {
        return $this->api->send($callback);
    }
}

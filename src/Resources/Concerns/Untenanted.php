<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Client\AuthenticatedApi;
use SupportManager\Sdk\Resources\Resource;

/**
 * For a resource over authenticated but user-scoped endpoints, which need no
 * `X-Team-ID` (the platform's `user/trusted-devices`, `user/social-accounts`
 * and `user/two-factor`, registered under `auth:sanctum` only).
 *
 * It swaps the base {@see Resource} request helpers for ones that go through
 * {@see AuthenticatedApi::send()} without a team, so they keep the
 * 401-refresh-and-retry behaviour but never resolve or send the current team.
 * A user with no team can still call them.
 *
 * @phpstan-require-extends Resource
 */
trait Untenanted
{
    /**
     * @param  array<string, mixed>  $query
     */
    protected function get(string $uri, array $query = []): Response
    {
        return $this->api->send(fn (PendingRequest $request): Response => $request->get($uri, $query));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function post(string $uri, array $data = []): Response
    {
        return $this->api->send(fn (PendingRequest $request): Response => $request->post($uri, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function patch(string $uri, array $data = []): Response
    {
        return $this->api->send(fn (PendingRequest $request): Response => $request->patch($uri, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function put(string $uri, array $data = []): Response
    {
        return $this->api->send(fn (PendingRequest $request): Response => $request->put($uri, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function delete(string $uri, array $data = []): Response
    {
        return $this->api->send(fn (PendingRequest $request): Response => $request->delete($uri, $data));
    }
}

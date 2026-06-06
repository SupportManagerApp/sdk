<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources;

use Closure;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Client\AuthenticatedApi;

/**
 * Base class for a tenant-scoped domain resource (car journeys, rota,
 * household, dashboard widgets, …).
 *
 * Each domain extends this and exposes typed methods that return DTOs, issuing
 * every call through {@see AuthenticatedApi::tenant()} so they all inherit the
 * current-team scoping and transparent token renewal. This is the seam through
 * which the SDK grows beyond authentication: a new module is one resource class
 * plus its DTOs, shared by every client app rather than duplicated per app.
 *
 * Example:
 *
 * ```php
 * final class JourneysResource extends Resource
 * {
 *     public function upcoming(): array
 *     {
 *         $data = $this->tenant(fn ($request) => $request->get('journeys/upcoming'))->json('data', []);
 *
 *         return array_map(Journey::fromArray(...), $data);
 *     }
 * }
 * ```
 */
abstract class Resource
{
    public function __construct(protected readonly AuthenticatedApi $api) {}

    /**
     * Issue a tenant-scoped request through the renewal-aware client.
     *
     * @param  Closure(\Illuminate\Http\Client\PendingRequest): Response  $callback
     */
    protected function tenant(Closure $callback): Response
    {
        return $this->api->tenant($callback);
    }
}

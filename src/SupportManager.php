<?php

declare(strict_types=1);

namespace SupportManager\Sdk;

use BadMethodCallException;
use Illuminate\Contracts\Container\Container;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;

/**
 * The single fluent entry point to the SDK, surfaced as the `SupportManager`
 * facade.
 *
 * It gathers the moving parts a host app would otherwise wire by hand —
 * authentication, the session, the current team, and tenant-scoped domain
 * resources — behind one object:
 *
 * ```php
 * SupportManager::auth()->login($email, $password);
 * SupportManager::team()->id();
 * SupportManager::journeys()->query()->filter('ongoing', true)->get();
 * ```
 *
 * Domain resources are registered by name (typically from a module's service
 * provider via {@see self::register()}) and then resolved fluently through
 * `__call`, so the core SDK stays free of domain code while host apps still get
 * the `SupportManager::journeys()` ergonomics.
 */
final class SupportManager
{
    /** @var array<string, class-string<Resource<*>>> */
    private array $resources = [];

    public function __construct(
        private readonly SupportManagerClient $client,
        private readonly AuthSession $session,
        private readonly CurrentTeam $team,
        private readonly Container $container,
    ) {}

    /**
     * Resolve a registered resource by its fluent name.
     *
     * @param  array<int, mixed>  $arguments
     * @return Resource<*>
     */
    public function __call(string $name, array $arguments): Resource
    {
        if (! isset($this->resources[$name])) {
            throw new BadMethodCallException(
                "No SupportManager resource is registered as [{$name}]. Register one with SupportManager::register('{$name}', …)."
            );
        }

        return $this->resource($this->resources[$name]);
    }

    /**
     * The authentication client: login, social login, refresh, logout, device
     * verification, and password reset.
     */
    public function auth(): SupportManagerClient
    {
        return $this->client;
    }

    /**
     * The persisted device session (tokens, cached user, current team).
     */
    public function session(): AuthSession
    {
        return $this->session;
    }

    /**
     * The current tenant team resolver.
     */
    public function team(): CurrentTeam
    {
        return $this->team;
    }

    /**
     * Register a domain resource under a fluent name, e.g.
     * `register('journeys', CarJourneysResource::class)` enables
     * `SupportManager::journeys()`.
     *
     * @param  class-string<Resource<*>>  $resourceClass
     */
    public function register(string $name, string $resourceClass): void
    {
        $this->resources[$name] = $resourceClass;
    }

    /**
     * Resolve a domain resource by its class, fully wired through the container.
     *
     * @template TResource of Resource<*>
     *
     * @param  class-string<TResource>  $resourceClass
     * @return TResource
     */
    public function resource(string $resourceClass): Resource
    {
        /** @var TResource $resource */
        $resource = $this->container->make($resourceClass);

        return $resource;
    }
}

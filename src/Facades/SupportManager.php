<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Facades;

use Illuminate\Support\Facades\Facade;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;

/**
 * Facade for the {@see \SupportManager\Sdk\SupportManager} entry point.
 *
 * Auto-aliased as `SupportManager` via package discovery, so host apps can write
 * `SupportManager::auth()->login(...)` straight away.
 *
 * @method static SupportManagerClient auth()
 * @method static AuthSession session()
 * @method static CurrentTeam team()
 * @method static void register(string $name, class-string<Resource<*>> $resourceClass)
 * @method static T resource<T of Resource>(class-string<T> $resourceClass)
 *
 * @see \SupportManager\Sdk\SupportManager
 */
final class SupportManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SupportManager\Sdk\SupportManager::class;
    }
}

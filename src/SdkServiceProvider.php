<?php

declare(strict_types=1);

namespace SupportManager\Sdk;

use Illuminate\Support\ServiceProvider;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Session\AuthSession;

/**
 * Registers the SupportManager client SDK into a host Laravel application.
 *
 * Binds the HTTP client and the persistent {@see AuthSession} (the session
 * leans on a {@see Contracts\SecureStore}, which each host
 * app binds to its own backing store — the device keychain on mobile, the OS
 * secret store on desktop). Everything else (AuthenticatedApi, CurrentTeam,
 * RefreshSession, CachedAvatar) auto-wires through the container.
 */
final class SdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/supportmanager.php', 'supportmanager');

        $this->app->singleton(SupportManagerClient::class, fn (): SupportManagerClient => new SupportManagerClient(
            baseUrl: (string) config('supportmanager.base_url'),
            defaultDeviceName: (string) config('supportmanager.device_name'),
            timeout: (int) config('supportmanager.timeout'),
            connectTimeout: (int) config('supportmanager.connect_timeout'),
        ));

        $this->app->singleton(AuthSession::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/supportmanager.php' => $this->app->configPath('supportmanager.php'),
        ], 'supportmanager-config');
    }
}

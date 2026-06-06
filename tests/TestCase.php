<?php

namespace SupportManager\Sdk\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use SupportManager\Sdk\Contracts\SecureStore;
use SupportManager\Sdk\SdkServiceProvider;
use SupportManager\Sdk\Tests\Support\ArraySecureStore;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SdkServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Stand in for the device keychain so any container-resolved session
        // has a backing store; tests that need to inspect it construct their own.
        $app->singleton(SecureStore::class, ArraySecureStore::class);

        config()->set('supportmanager.base_url', 'https://api.test');
        config()->set('supportmanager.device_name', 'Test Device');
    }
}

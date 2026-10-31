<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests;

use Illuminate\Http\Client\Factory;
use Orchestra\Testbench\TestCase as BaseTestCase;
use SupportManager\Sdk\Contracts\SecureStore;
use SupportManager\Sdk\SdkServiceProvider;
use SupportManager\Sdk\Tests\Contract\Support\ContractValidator;
use SupportManager\Sdk\Tests\Contract\Support\MethodCoverage;
use SupportManager\Sdk\Tests\Contract\Support\TestRun;
use SupportManager\Sdk\Tests\Support\ArraySecureStore;

abstract class TestCase extends BaseTestCase
{
    /**
     * Forget any resource calls a previous, failed test noted but never
     * credited.
     */
    protected function setUp(): void
    {
        MethodCoverage::reset();

        parent::setUp();
    }

    /**
     * Contract check: every request the SDK sent and every response it got
     * back in this test, whether from Http::fake() or SupportManager::fake(),
     * must match the vendored OpenAPI spec. Laravel records each faked
     * exchange after all client middleware has run, so this sees exactly what
     * went over the (faked) wire. See tests/Contract and the README.
     *
     * The exchanges that pass also credit the public resource methods that
     * sent them, for the method-coverage check (see MethodCoverage).
     */
    protected function assertPostConditions(): void
    {
        parent::assertPostConditions();

        TestRun::ran(static::class);

        if (! $this->app?->resolved(Factory::class)) {
            return;
        }

        $violations = [];
        $documented = [];

        foreach ($this->app->make(Factory::class)->recorded() as [$request, $response]) {
            $psr = $request->toPsrRequest();
            $found = ContractValidator::instance()->check($psr, $response?->toPsrResponse());

            foreach ($found as $violation) {
                $violations[$violation] = true;
            }

            if ($found === [] && ContractValidator::instance()->operationFor($psr) !== null) {
                $documented[] = $psr;
            }
        }

        MethodCoverage::credit($documented);

        if ($violations !== []) {
            $this->fail("The faked API traffic does not match the platform's OpenAPI spec (tests/Contract/openapi.yaml):\n  - "
                .implode("\n  - ", array_keys($violations))
                ."\nFix the fixture to match the platform, or add a reasoned entry to tests/Contract/spec-exceptions.php or known-sdk-bugs.php.");
        }
    }

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

        // Method coverage: credit the public resource method behind every
        // request the HTTP client sends, faked or not. The factory is a
        // singleton, so this registers once per test, before any request.
        $app->afterResolving(Factory::class, static function (Factory $http): void {
            $http->globalRequestMiddleware(MethodCoverage::middleware());
        });

        config()->set('supportmanager.base_url', 'https://api.test');
        config()->set('supportmanager.device_name', 'Test Device');
    }
}

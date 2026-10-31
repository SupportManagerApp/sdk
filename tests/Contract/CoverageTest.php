<?php

declare(strict_types=1);

use SupportManager\Sdk\Tests\Arch\Support\Codebase;
use SupportManager\Sdk\Tests\Contract\Support\Allowlist;
use SupportManager\Sdk\Tests\Contract\Support\AllowlistEntry;
use SupportManager\Sdk\Tests\Contract\Support\ContractValidator;
use SupportManager\Sdk\Tests\Contract\Support\MethodCoverage;
use SupportManager\Sdk\Tests\Contract\Support\OpenApiSpec;
use SupportManager\Sdk\Tests\Contract\Support\TestRun;

/*
 * Which spec operations the SDK calls, judged by the traffic the Unit and
 * Feature suites actually sent (so string-built paths count). phpunit.xml runs
 * this suite last; on a partial run (a filter, one file, --parallel) these
 * checks skip rather than report false gaps.
 *
 * The method-coverage check closes the other gap: contract validation only
 * judges the requests tests send, so a public resource method no test calls
 * is never checked against the spec. Every public resource method must have
 * sent a request that passed contract validation in some test (see
 * MethodCoverage), so each one is shown to reach a documented route.
 *
 * `composer coverage:api` runs the whole suite and prints the full report.
 */

function require_complete_run(): void
{
    if (! TestRun::complete()) {
        $missing = TestRun::missingFiles();

        test()->markTestSkipped('API coverage needs the full suite in one process'
            .($missing === [] ? ' (not --parallel).' : '; '.count($missing).' test files did not run, e.g. '.$missing[0].'.'));
    }
}

it('calls every operation in the spec, or allowlists it with a reason', function () {
    require_complete_run();

    $spec = OpenApiSpec::instance();
    $hits = ContractValidator::instance()->hits();

    /** @var array<string, string> $unwrapped */
    $unwrapped = require __DIR__.'/unwrapped-operations.php';

    $unknown = array_values(array_diff(array_keys($unwrapped), $spec->operationKeys()));
    $nowCalled = array_values(array_intersect(array_keys($unwrapped), array_keys($hits)));
    $uncovered = array_values(array_diff($spec->operationKeys(), array_keys($hits), array_keys($unwrapped)));

    if (getenv('API_COVERAGE_REPORT')) {
        $lines = [
            sprintf('API coverage against platform commit %s: %d of %d operations called by the SDK.',
                OpenApiSpec::platformCommit(), count(array_intersect($spec->operationKeys(), array_keys($hits))), count($spec->operationKeys())),
            '',
            'Deliberately not wrapped (tests/Contract/unwrapped-operations.php):',
            ...array_map(static fn (string $op, string $reason): string => "  {$op} — {$reason}", array_keys($unwrapped), $unwrapped),
            '',
            'Not wrapped and not allowlisted:',
            ...($uncovered === [] ? ['  (none)'] : array_map(static fn (string $op): string => "  {$op}", $uncovered)),
        ];

        fwrite(STDOUT, "\n".implode("\n", $lines)."\n");
    }

    expect($unknown)->toBe([], 'unwrapped-operations.php lists operations the spec no longer has: '.implode(', ', $unknown))
        ->and($nowCalled)->toBe([], 'unwrapped-operations.php lists operations the SDK now calls; remove them: '.implode(', ', $nowCalled))
        ->and($uncovered)->toBe([], "No SDK resource calls these spec operations (or no test exercises them).\n"
            ."Wrap and test them, or add each to tests/Contract/unwrapped-operations.php with a reason:\n  "
            .implode("\n  ", $uncovered)."\n");
});

it('has no allowlist entry that the suite no longer needs', function () {
    require_complete_run();

    $stale = [
        ...array_map(
            static fn (AllowlistEntry $entry): string => "{$entry->id}: {$entry->describe()}",
            ContractValidator::instance()->staleEntries(),
        ),
        ...Allowlist::instance()->unusedMissing(),
    ];

    expect($stale)->toBe([], "These allowlist entries no longer match any failing exchange (the spec, fixture or SDK was fixed, or nothing calls it). Remove them:\n  "
        .implode("\n  ", $stale)."\n");
});

it('sends a documented request from every public resource method, or lists it as request-free', function () {
    require_complete_run();

    /** @var array<string, string> $requestFree */
    $requestFree = require __DIR__.'/request-free-methods.php';

    $exercised = MethodCoverage::exercised();
    $untested = [];
    $exercisedButListed = [];
    $usedEntries = [];

    // A method may be listed under its own key or, from a trait, the trait's.
    foreach (MethodCoverage::publicMethods(Codebase::resources()) as $method => $keys) {
        $entry = array_values(array_intersect($keys, array_keys($requestFree)))[0] ?? null;

        if ($entry !== null) {
            $usedEntries[$entry] = true;
        }

        if (isset($exercised[$method]) && $entry !== null) {
            $exercisedButListed[] = $method;
        } elseif (! isset($exercised[$method]) && $entry === null) {
            $untested[] = $method;
        }
    }

    $unknown = array_values(array_diff(array_keys($requestFree), array_keys($usedEntries)));

    expect($unknown)->toBe([], 'request-free-methods.php lists methods no resource has any more; remove them: '.implode(', ', $unknown))
        ->and($exercisedButListed)->toBe([], "request-free-methods.php lists methods that send a request in the suite; remove them:\n  ".implode("\n  ", $exercisedButListed)."\n")
        ->and($untested)->toBe([], "No test calls these public resource methods through to a contract-validated request, so nothing checks that their route exists.\n"
            ."Test each one in its module's resource test (fakeApi() + assertSentTo()). If the platform has no such route, fix or remove the method.\n"
            ."Only a method that sends nothing belongs in tests/Contract/request-free-methods.php:\n  "
            .implode("\n  ", $untested)."\n");
});

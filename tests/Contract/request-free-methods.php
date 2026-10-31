<?php

declare(strict_types=1);

/*
 * Public resource methods that never send a request themselves, so no test
 * can exercise them over the faked wire, each with its reason. CoverageTest
 * fails on any other public resource method that no test calls through to an
 * HTTP request, and on an entry here that the suite does exercise.
 *
 * A key is `Class::method`, or `Trait::method` for a method every resource
 * using the trait gets. Builders only: a method that sends something belongs
 * in a test, not here.
 *
 *   'SupportManager\Sdk\Resources\Module\NameResource::method' => 'why it sends nothing',
 */

use SupportManager\Sdk\Resources\Concerns\Listable;

return [
    Listable::class.'::query' => 'Builds a ResourceQuery and sends nothing. Its terminals read only GET {path}, which tests/Arch/CollectionReadsTest.php checks against the spec for every resource with query(), and Listable::all() exercises per resource.',
];

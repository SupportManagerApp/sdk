<?php

declare(strict_types=1);

/*
 * Contract violations caused by real SDK bugs. The SDK (src/Resources,
 * src/Data) sends something the platform does not accept, or reads a shape
 * the platform never sends, and a fixture still mirrors the SDK's wrong shape
 * so its test stays green until the SDK is fixed.
 *
 * Same format as spec-exceptions.php. An entry with `'missing' => true`
 * names an endpoint the SDK calls that the platform does not have at all.
 * Fix the SDK, correct the fixture or test, then drop the entry; the suite
 * reports entries that are no longer needed.
 */

return [];

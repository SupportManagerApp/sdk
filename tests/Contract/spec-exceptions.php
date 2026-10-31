<?php

declare(strict_types=1);

/*
 * Places where the vendored OpenAPI spec is wrong and the platform code is
 * right. Each entry patches one operation's schema at one JSON pointer, so it
 * cannot hide drift elsewhere. See the README's "Contract tests" section.
 *
 *   operation  'METHOD /template', exactly as in the spec
 *   in         'request', or the response status code
 *   pointer    JSON pointer into the body; `*` means every array item
 *   schema     the real schema at that pointer (adds a missing property), and/or
 *   nullable   true: the value may be null
 *   optional   true: the value may be absent
 *   reason     one line: what the platform really does, and where
 *
 * Fix the platform's spec and drop the entry once it is synced; the suite
 * reports entries that are no longer needed.
 */

return [
];

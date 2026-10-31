<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Hydration\Fixtures;

use SupportManager\Sdk\Data\Hydration\Caster;

/**
 * Upper-cases the value, prefixed with the payload's `prefix` key, to prove a
 * caster sees both its own value and the whole input.
 */
final readonly class Upper implements Caster
{
    public function cast(mixed $value, array $data): string
    {
        return ((string) ($data['prefix'] ?? '')).mb_strtoupper((string) $value);
    }
}

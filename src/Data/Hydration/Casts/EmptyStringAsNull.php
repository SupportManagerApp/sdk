<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Casts;

use SupportManager\Sdk\Data\Hydration\Caster;

/**
 * A nullable string that treats `''` as absent, for payloads (such as the
 * address lookup) that send unused fields as empty strings.
 */
final readonly class EmptyStringAsNull implements Caster
{
    public function cast(mixed $value, array $data): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}

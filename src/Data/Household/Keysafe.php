<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The team's keysafe state, from the platform's `KeysafeController` show and
 * update: whether a code is set and whether the current user may edit it.
 *
 * `code` is always null here, because neither body carries the code: read it
 * with `SupportManager::keysafe()->reveal()`, which returns it as a string.
 * The parameter stays so positional constructors keep working.
 */
final readonly class Keysafe
{
    public function __construct(
        public bool $hasCode,
        public bool $canEdit,
        public ?string $code,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The KeysafeController `data` payload.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

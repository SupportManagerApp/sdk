<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The team's keysafe state. Mirrors the platform's `KeysafeController` payload:
 * `show()`/`update()` return whether a code is set and whether the current user
 * may edit it, while `reveal()` returns the actual `code` (only the latter ever
 * populates `code`).
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

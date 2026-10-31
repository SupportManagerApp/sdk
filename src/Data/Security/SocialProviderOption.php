<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Security;

use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Security\SocialProvider;

/**
 * A provider configured on the platform's environment, and whether the user
 * has linked it: one row of `meta.available_providers` on
 * `GET user/social-accounts`. `provider` is null for a provider this SDK does
 * not know yet, which a client should skip.
 */
final readonly class SocialProviderOption
{
    public function __construct(
        public ?SocialProvider $provider,
        public string $label,
        public bool $linked,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One `meta.available_providers` row.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

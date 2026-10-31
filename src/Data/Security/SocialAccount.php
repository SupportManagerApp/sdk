<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Security;

use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Security\SocialProvider;

/**
 * A provider account linked to the user for social sign-in. Mirrors the
 * platform's `SocialAccountResource`. `provider` is null for a provider this
 * SDK does not know yet; `providerLabel` still names it.
 */
final readonly class SocialAccount
{
    public function __construct(
        public string $id,
        public ?SocialProvider $provider,
        public string $providerLabel,
        public ?string $providerEmail,
        public ?string $providerName,
        public ?string $linkedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

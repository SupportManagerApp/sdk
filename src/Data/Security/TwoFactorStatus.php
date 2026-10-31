<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Security;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The user's second factors. Mirrors the platform's `TwoFactorStatusResource`.
 *
 * `hasSecondFactor` is true when the authenticator app (TOTP) is enabled or the
 * user has a passkey. `secondFactorRequiredBy` is the deadline after which the
 * web insists on a second factor, null once one is set up or when none is
 * required.
 */
final readonly class TwoFactorStatus
{
    public function __construct(
        public bool $hasSecondFactor,
        public bool $totpEnabled,
        public ?string $totpEnabledAt,
        public int $backupCodesRemaining,
        public ?string $backupCodesGeneratedAt,
        public ?string $secondFactorRequiredBy,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The plain `data` object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

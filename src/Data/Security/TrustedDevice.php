<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Security;

use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Security\TrustedDeviceType;

/**
 * A device the user has trusted, which can sign in without device verification
 * until `expiresAt`. Mirrors the platform's `TrustedDeviceResource`.
 *
 * `isCurrentDevice` marks the device the request's access token was issued to.
 * It is false for every device while the tokens predate the platform linking
 * tokens to devices, until the next sign-in.
 */
final readonly class TrustedDevice
{
    public function __construct(
        public string $id,
        public string $deviceName,
        public ?TrustedDeviceType $deviceType,
        public string $maskedIpAddress,
        public bool $isCurrentDevice,
        public ?string $verifiedAt,
        public ?string $lastUsedAt,
        public ?string $expiresAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

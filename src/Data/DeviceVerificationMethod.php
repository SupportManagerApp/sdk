<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A verification method (sms, email, passkey) offered during the device
 * verification step of login.
 */
final readonly class DeviceVerificationMethod
{
    public function __construct(
        public string $method,
        public string $label,
        public string $description,
        public ?string $maskedDestination = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

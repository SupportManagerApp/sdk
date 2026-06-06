<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

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
     * @param  array{method: string, label: string, description: string, masked_destination?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: (string) $data['method'],
            label: (string) $data['label'],
            description: (string) $data['description'],
            maskedDestination: $data['masked_destination'] ?? null,
        );
    }
}

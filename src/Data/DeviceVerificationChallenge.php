<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The challenge returned (HTTP 403) when login succeeds but the device is
 * not yet trusted. The verification token authorises the follow-up
 * device-verification requests.
 */
final readonly class DeviceVerificationChallenge
{
    /**
     * @param  list<DeviceVerificationMethod>  $methods
     */
    public function __construct(
        public string $verificationToken,
        #[From('verification_token_expires_in')]
        public int $expiresIn,
        #[From('available_methods')]
        #[ListOf(DeviceVerificationMethod::class)]
        public array $methods,
        #[From('user.id')]
        public ?int $userId = null,
        #[From('user.name')]
        public ?string $userName = null,
    ) {}

    /**
     * Build from the API's "data" payload of a 403 response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

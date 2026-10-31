<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\PushNotifications;

use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\PushNotifications\DevicePlatform;

/**
 * A registered push-notification (FCM) device token for the authenticated user
 * in the current tenant.
 *
 * Mirrors the platform's `DeviceTokenResource`.
 */
final readonly class DeviceToken
{
    public function __construct(
        public string $id,
        public string $token,
        #[Defaults(DevicePlatform::Ios)]
        public DevicePlatform $platform,
        public ?string $lastUsedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

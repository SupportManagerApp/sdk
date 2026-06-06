<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\PushNotifications;

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
        return new self(
            id: (string) ($data['id'] ?? ''),
            token: (string) ($data['token'] ?? ''),
            platform: DevicePlatform::from((string) ($data['platform'] ?? 'ios')),
            lastUsedAt: isset($data['last_used_at']) ? (string) $data['last_used_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

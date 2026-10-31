<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Notifications;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A single database notification for the authenticated user.
 *
 * Mirrors the platform's `NotificationResource`: `type` is the notification's
 * PHP class, `data` its serialised payload, and `readAt` the moment it was read
 * (null while unread). {@see self::isRead()} is the convenience the apps reach
 * for most.
 */
final readonly class Notification
{
    /**
     * @param  array<string, mixed>  $data  The notification's serialised payload.
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
        public ?string $readAt,
        public ?string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }

    /**
     * Whether the notification has been read.
     */
    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * Whether the notification is still unread.
     */
    public function isUnread(): bool
    {
        return $this->readAt === null;
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Notifications;

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
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            data: is_array($data['data'] ?? null) ? $data['data'] : [],
            readAt: isset($data['read_at']) ? (string) $data['read_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
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

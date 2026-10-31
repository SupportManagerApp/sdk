<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\CarJourneys;

/**
 * A single trip record — start/end times, driver, and the user it belongs to.
 *
 * Mirrors the platform's `CarJourneyResource` attributes. `isOngoing` is the
 * server's view of whether the trip is still open (no `trip_end`).
 * `canUpdate`/`canDelete` are the server's `meta.can` flags for the
 * authenticated user: admins and managers may change any journey, anyone else
 * only their own while it is ongoing or for 12 hours after it ended.
 */
final readonly class CarJourney
{
    public function __construct(
        public string $id,
        public string $driverName,
        public string $tripStart,
        public ?string $tripEnd,
        public bool $isOngoing,
        public ?int $durationMinutes,
        public ?string $comments,
        public ?int $userId,
        public ?string $userName,
        public ?string $createdAt,
        public ?string $updatedAt,
        public bool $canUpdate = false,
        public bool $canDelete = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        /** @var array{update?: mixed, delete?: mixed} $can */
        $can = is_array($data['meta']['can'] ?? null) ? $data['meta']['can'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            driverName: (string) ($data['driver_name'] ?? ''),
            tripStart: (string) ($data['trip_start'] ?? ''),
            tripEnd: isset($data['trip_end']) ? (string) $data['trip_end'] : null,
            isOngoing: (bool) ($data['is_ongoing'] ?? false),
            durationMinutes: isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            comments: isset($data['comments']) ? (string) $data['comments'] : null,
            userId: isset($user['id']) ? (int) $user['id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            canUpdate: (bool) ($can['update'] ?? false),
            canDelete: (bool) ($can['delete'] ?? false),
        );
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\CarJourneys;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[From('user.id')]
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        public ?string $createdAt,
        public ?string $updatedAt,
        #[Can('update')]
        public bool $canUpdate = false,
        #[Can('delete')]
        public bool $canDelete = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A one-off calendar appointment, separate from regular shifts.
 *
 * Mirrors the platform's `AppointmentResource` attributes. `viewerIds` is the
 * users a private appointment is shared with, and is null unless the platform
 * loaded that relationship. `canUpdate` and `canDelete` come from the
 * platform's `meta.can` flags — whether `update()` and `destroy()` are open to
 * the caller — and read false when the response carried no meta.
 */
final readonly class Appointment
{
    /**
     * @param  list<int>|null  $viewerIds
     */
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?string $title,
        public ?string $location,
        public ?string $notes,
        public bool $isVirtual,
        public bool $isAllDay,
        public bool $isPrivate,
        public ?int $createdBy,
        #[ListOf('int')]
        public ?array $viewerIds,
        public ?string $start,
        public ?string $end,
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

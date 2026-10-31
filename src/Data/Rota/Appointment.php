<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

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
        public ?array $viewerIds,
        public ?string $start,
        public ?string $end,
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
        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        /** @var array<string, mixed> $can */
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            isVirtual: (bool) ($data['is_virtual'] ?? false),
            isAllDay: (bool) ($data['is_all_day'] ?? false),
            isPrivate: (bool) ($data['is_private'] ?? false),
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            viewerIds: is_array($data['viewer_ids'] ?? null)
                ? array_values(array_map(static fn (mixed $id): int => (int) $id, $data['viewer_ids']))
                : null,
            start: isset($data['start']) ? (string) $data['start'] : null,
            end: isset($data['end']) ? (string) $data['end'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            canUpdate: (bool) ($can['update'] ?? false),
            canDelete: (bool) ($can['delete'] ?? false),
        );
    }
}

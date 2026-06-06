<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * A one-off calendar appointment, separate from regular shifts.
 *
 * Mirrors the platform's `AppointmentResource` attributes.
 */
final readonly class Appointment
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?string $title,
        public ?string $location,
        public bool $isVirtual,
        public bool $isAllDay,
        public ?string $start,
        public ?string $end,
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
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            isVirtual: (bool) ($data['is_virtual'] ?? false),
            isAllDay: (bool) ($data['is_all_day'] ?? false),
            start: isset($data['start']) ? (string) $data['start'] : null,
            end: isset($data['end']) ? (string) $data['end'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

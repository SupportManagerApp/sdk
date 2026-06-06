<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Enums\Rota\EntryType;

/**
 * A single rota entry — a scheduled shift, leave or absence block.
 *
 * Mirrors the platform's `RotaEntryResource` attributes. `type` is the typed
 * {@see EntryType}; `typeLabel` is the platform's display label for it. The
 * assigned `user` is summarised as flat `userId`/`userName`/`userAvatar`
 * fields when the relationship is loaded.
 */
final readonly class Shift
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?string $userAvatar,
        public ?string $start,
        public ?string $end,
        public ?EntryType $type,
        public ?string $typeLabel,
        public float $duration,
        public bool $isPublished,
        public bool $isAvailable,
        public bool $isSleepingNight,
        public bool $isOvertime,
        public ?string $notes,
        public ?int $shiftApplicationsCount,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed, avatar?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : (isset($user['id']) ? (int) $user['id'] : null),
            userName: isset($user['name']) ? (string) $user['name'] : null,
            userAvatar: isset($user['avatar']) ? (string) $user['avatar'] : null,
            start: isset($data['start']) ? (string) $data['start'] : null,
            end: isset($data['end']) ? (string) $data['end'] : null,
            type: isset($data['type']) ? EntryType::tryFrom((string) $data['type']) : null,
            typeLabel: isset($data['type_label']) ? (string) $data['type_label'] : null,
            duration: (float) ($data['duration'] ?? 0),
            isPublished: (bool) ($data['is_published'] ?? false),
            isAvailable: (bool) ($data['is_available'] ?? false),
            isSleepingNight: (bool) ($data['is_sleeping_night'] ?? false),
            isOvertime: (bool) ($data['is_overtime'] ?? false),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            shiftApplicationsCount: isset($data['shift_applications_count']) ? (int) $data['shift_applications_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

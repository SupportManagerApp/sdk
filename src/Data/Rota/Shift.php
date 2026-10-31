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
 *
 * A non-null `shiftBlockId` marks a segment of a sleepover, which is applied
 * for and withdrawn as a whole through {@see \SupportManager\Sdk\Resources\Rota\ShiftBlocksResource}
 * rather than per shift. `ownApplicationStatus` is the caller's own application
 * status (null when they have not applied). `canApply`, `canRequestAmendment`
 * and `cannotApplyReason` come from the platform's `meta.can` flags, so a
 * client can show or hide the matching buttons; they read false/null when the
 * response carried no meta.
 */
final readonly class Shift
{
    /**
     * @param  list<string>  $tags
     */
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
        public bool $isForToil = false,
        public bool $isPrivate = false,
        public ?int $shiftBlockId = null,
        public array $tags = [],
        public ?string $ownApplicationStatus = null,
        public bool $canApply = false,
        public bool $canRequestAmendment = false,
        public ?string $cannotApplyReason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed, avatar?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        /** @var array<string, mixed> $can */
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        $tags = [];

        foreach (is_array($data['tags'] ?? null) ? $data['tags'] : [] as $tag) {
            if (is_string($tag)) {
                $tags[] = $tag;
            }
        }

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
            isForToil: (bool) ($data['is_for_toil'] ?? false),
            isPrivate: (bool) ($data['is_private'] ?? false),
            shiftBlockId: isset($data['shift_block_id']) ? (int) $data['shift_block_id'] : null,
            tags: $tags,
            ownApplicationStatus: isset($data['own_application_status']) ? (string) $data['own_application_status'] : null,
            canApply: (bool) ($can['apply'] ?? false),
            canRequestAmendment: (bool) ($can['request_amendment'] ?? false),
            cannotApplyReason: isset($meta['cannot_apply_reason']) ? (string) $meta['cannot_apply_reason'] : null,
        );
    }
}

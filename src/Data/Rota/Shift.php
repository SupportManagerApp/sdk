<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Attributes\Meta;
use SupportManager\Sdk\Data\Hydration\Hydrator;
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
        #[From('user_id', 'user.id')]
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        #[From('user.avatar')]
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
        #[ListOf('string')]
        public array $tags = [],
        public ?string $ownApplicationStatus = null,
        #[Can('apply')]
        public bool $canApply = false,
        #[Can('request_amendment')]
        public bool $canRequestAmendment = false,
        #[Meta('cannot_apply_reason')]
        public ?string $cannotApplyReason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

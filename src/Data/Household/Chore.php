<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Household\ChoreFrequency;

/**
 * A household chore — its recurrence settings plus the server's view of when it
 * was last done and whether it is now due.
 *
 * Mirrors the platform's `ChoreResource` attributes. `frequency` is the typed
 * enum; `completedBy` and `snoozedBy` are only populated when the relationship
 * is loaded, and `category*` is null when the chore has no category.
 * `normalizedName` (the name trimmed and lower-cased) is the group key the bulk
 * {@see \SupportManager\Sdk\Resources\Household\ChoresResource::completeGroup()}
 * and `markGroupNotNeeded()` select by. `canUpdate`/`canDelete` are the
 * server's `meta.can` flags for the authenticated user (only admins and
 * managers may edit or delete chores).
 */
final readonly class Chore
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ChoreFrequency $frequency,
        #[From('frequency_label', 'frequency')]
        #[Defaults(ChoreFrequency::Daily->value)]
        public string $frequencyLabel,
        public ?int $customFrequencyDays,
        public ?string $lastCompleted,
        public ?string $snoozedUntil,
        public bool $isSnoozed,
        #[From('completed_by.id')]
        public ?int $completedById,
        #[From('completed_by.name')]
        public ?string $completedByName,
        public ?int $daysSinceCompletion,
        public bool $isOverdue,
        public bool $shouldHighlight,
        public ?int $teamId,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $normalizedName = null,
        #[From('category.id')]
        public ?int $categoryId = null,
        #[From('category.name')]
        public ?string $categoryName = null,
        #[From('category.color')]
        public ?string $categoryColor = null,
        #[From('category.icon')]
        public ?string $categoryIcon = null,
        public ?string $snoozedAt = null,
        #[From('snoozed_by.id')]
        public ?int $snoozedById = null,
        #[From('snoozed_by.name')]
        public ?string $snoozedByName = null,
        public ?string $plannedFor = null,
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
        // An unknown frequency reads as daily rather than failing the whole chore.
        return Hydrator::hydrate(self::class, $data, [
            'frequency' => ChoreFrequency::tryFrom((string) ($data['frequency'] ?? '')) ?? ChoreFrequency::Daily,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

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
        public string $frequencyLabel,
        public ?int $customFrequencyDays,
        public ?string $lastCompleted,
        public ?string $snoozedUntil,
        public bool $isSnoozed,
        public ?int $completedById,
        public ?string $completedByName,
        public ?int $daysSinceCompletion,
        public bool $isOverdue,
        public bool $shouldHighlight,
        public ?int $teamId,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $normalizedName = null,
        public ?int $categoryId = null,
        public ?string $categoryName = null,
        public ?string $categoryColor = null,
        public ?string $categoryIcon = null,
        public ?string $snoozedAt = null,
        public ?int $snoozedById = null,
        public ?string $snoozedByName = null,
        public ?string $plannedFor = null,
        public bool $canUpdate = false,
        public bool $canDelete = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $completedBy */
        $completedBy = is_array($data['completed_by'] ?? null) ? $data['completed_by'] : [];

        /** @var array{id?: mixed, name?: mixed} $snoozedBy */
        $snoozedBy = is_array($data['snoozed_by'] ?? null) ? $data['snoozed_by'] : [];

        /** @var array{id?: mixed, name?: mixed, color?: mixed, icon?: mixed} $category */
        $category = is_array($data['category'] ?? null) ? $data['category'] : [];

        /** @var array{update?: mixed, delete?: mixed} $can */
        $can = is_array($data['meta']['can'] ?? null) ? $data['meta']['can'] : [];

        $frequency = (string) ($data['frequency'] ?? ChoreFrequency::Daily->value);

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            frequency: ChoreFrequency::tryFrom($frequency) ?? ChoreFrequency::Daily,
            frequencyLabel: (string) ($data['frequency_label'] ?? $frequency),
            customFrequencyDays: isset($data['custom_frequency_days']) ? (int) $data['custom_frequency_days'] : null,
            lastCompleted: isset($data['last_completed']) ? (string) $data['last_completed'] : null,
            snoozedUntil: isset($data['snoozed_until']) ? (string) $data['snoozed_until'] : null,
            isSnoozed: (bool) ($data['is_snoozed'] ?? false),
            completedById: isset($completedBy['id']) ? (int) $completedBy['id'] : null,
            completedByName: isset($completedBy['name']) ? (string) $completedBy['name'] : null,
            daysSinceCompletion: isset($data['days_since_completion']) ? (int) $data['days_since_completion'] : null,
            isOverdue: (bool) ($data['is_overdue'] ?? false),
            shouldHighlight: (bool) ($data['should_highlight'] ?? false),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            normalizedName: isset($data['normalized_name']) ? (string) $data['normalized_name'] : null,
            categoryId: isset($category['id']) ? (int) $category['id'] : null,
            categoryName: isset($category['name']) ? (string) $category['name'] : null,
            categoryColor: isset($category['color']) ? (string) $category['color'] : null,
            categoryIcon: isset($category['icon']) ? (string) $category['icon'] : null,
            snoozedAt: isset($data['snoozed_at']) ? (string) $data['snoozed_at'] : null,
            snoozedById: isset($snoozedBy['id']) ? (int) $snoozedBy['id'] : null,
            snoozedByName: isset($snoozedBy['name']) ? (string) $snoozedBy['name'] : null,
            plannedFor: isset($data['planned_for']) ? (string) $data['planned_for'] : null,
            canUpdate: (bool) ($can['update'] ?? false),
            canDelete: (bool) ($can['delete'] ?? false),
        );
    }
}

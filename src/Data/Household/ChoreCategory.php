<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A category used to organise chores. Mirrors the platform's
 * `ChoreCategoryResource`; `choresCount` is only present when the controller
 * counts the relationship (it always does on index). `dueChoresCount` counts
 * the category's chores that are due now and not snoozed — the
 * `filter[due]=true` chore list — as the web app's category tiles do.
 */
final readonly class ChoreCategory
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $color,
        public ?string $icon,
        public ?int $choresCount,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?int $dueChoresCount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

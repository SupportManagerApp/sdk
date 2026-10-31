<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The team's weekly meal plan — one meal per day. Mirrors the platform's
 * `MealPlanResource` attributes, which nest the seven days under a `days` key.
 * A null day means no meal is planned for it.
 */
final readonly class MealPlan
{
    public function __construct(
        public ?int $teamId,
        #[From('days.monday')]
        public ?string $monday,
        #[From('days.tuesday')]
        public ?string $tuesday,
        #[From('days.wednesday')]
        public ?string $wednesday,
        #[From('days.thursday')]
        public ?string $thursday,
        #[From('days.friday')]
        public ?string $friday,
        #[From('days.saturday')]
        public ?string $saturday,
        #[From('days.sunday')]
        public ?string $sunday,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

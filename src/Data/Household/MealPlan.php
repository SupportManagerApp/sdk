<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

/**
 * The team's weekly meal plan — one meal per day. Mirrors the platform's
 * `MealPlanResource` attributes, which nest the seven days under a `days` key.
 * A null day means no meal is planned for it.
 */
final readonly class MealPlan
{
    public function __construct(
        public ?int $teamId,
        public ?string $monday,
        public ?string $tuesday,
        public ?string $wednesday,
        public ?string $thursday,
        public ?string $friday,
        public ?string $saturday,
        public ?string $sunday,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $days */
        $days = is_array($data['days'] ?? null) ? $data['days'] : [];

        $day = static fn (string $name): ?string => isset($days[$name]) ? (string) $days[$name] : null;

        return new self(
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            monday: $day('monday'),
            tuesday: $day('tuesday'),
            wednesday: $day('wednesday'),
            thursday: $day('thursday'),
            friday: $day('friday'),
            saturday: $day('saturday'),
            sunday: $day('sunday'),
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

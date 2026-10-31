<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Dashboard\OptionalModule;
use SupportManager\Sdk\Enums\Dashboard\PlanFeature;

/**
 * What the current user can do in the current team: their role, whether the
 * team is subscribed, which plan features and optional modules the team has,
 * and the web navigation the user gets.
 *
 * Mirrors the platform's `GET capabilities` payload. `role` is the platform's
 * role label (`Admin`, `Manager`, `PA`, `Family Member`, `Contractor`,
 * `Former PA`), or null. `planFeatures` and `modules` map every known key to a
 * boolean; both may grow, so they keep string keys and the
 * {@see self::hasPlanFeature()} / {@see self::usesModule()} lookups treat an
 * unknown key as false. When `subscribed` is false every other tenant endpoint
 * answers 403.
 */
final readonly class Capabilities
{
    /**
     * @param  array<string, bool>  $planFeatures
     * @param  array<string, bool>  $modules
     * @param  list<NavigationArea>  $navigation
     */
    public function __construct(
        public ?string $role,
        public bool $isPrivileged,
        public bool $isEmployee,
        public bool $isFormerPa,
        public bool $subscribed,
        public array $planFeatures,
        public array $modules,
        #[ListOf(NavigationArea::class)]
        public array $navigation,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data, [
            'planFeatures' => self::flags($data['plan_features'] ?? null),
            'modules' => self::flags($data['modules'] ?? null),
        ]);
    }

    /**
     * Whether the team's plan includes a feature.
     */
    public function hasPlanFeature(PlanFeature|string $feature): bool
    {
        $key = $feature instanceof PlanFeature ? $feature->value : $feature;

        return $this->planFeatures[$key] ?? false;
    }

    /**
     * Whether the team uses an optional module (its plan allows it and the team
     * has switched it on).
     */
    public function usesModule(OptionalModule|string $module): bool
    {
        $key = $module instanceof OptionalModule ? $module->value : $module;

        return $this->modules[$key] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    private static function flags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $flags = [];

        foreach ($value as $key => $enabled) {
            $flags[(string) $key] = (bool) $enabled;
        }

        return $flags;
    }
}

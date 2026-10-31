<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Enums\Dashboard\OptionalModule;
use SupportManager\Sdk\Enums\Dashboard\PlanFeature;
use SupportManager\Sdk\Testing\Factory;

/**
 * Fake user capabilities — a flat singleton (`{data: {role, …}}`) at
 * `capabilities`, so `type()` stays null and the generic singleton read
 * serves it.
 *
 * Defaults to a subscribed personal assistant on a plan with every feature,
 * with the household and car journeys modules switched on.
 *
 * ```php
 * SupportManager::fake()->seed(CapabilitiesFactory::new()->manager()->withModules(OptionalModule::Accidents));
 * SupportManager::dashboard()->capabilities()->usesModule(OptionalModule::Accidents); // true
 * ```
 */
final class CapabilitiesFactory extends Factory
{
    public function path(): string
    {
        return 'capabilities';
    }

    public function definition(int $index): array
    {
        return [
            'role' => 'PA',
            'is_privileged' => false,
            'is_employee' => true,
            'is_former_pa' => false,
            'subscribed' => true,
            'plan_features' => array_fill_keys(array_map(static fn (PlanFeature $feature): string => $feature->value, PlanFeature::cases()), true),
            'modules' => self::modules(OptionalModule::Household, OptionalModule::CarJourneys),
            'navigation' => [
                [
                    'key' => 'pay',
                    'label' => 'Pay',
                    'icon' => 'banknotes',
                    'items' => [
                        ['label' => 'Payslips', 'icon' => 'banknotes', 'route_name' => 'payroll.index'],
                        ['label' => 'P60s', 'icon' => 'document-text', 'route_name' => 'payroll.p60s'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Act as a manager (privileged and an employee).
     */
    public function manager(): static
    {
        return $this->state(['role' => 'Manager', 'is_privileged' => true, 'is_employee' => true]);
    }

    /**
     * Act as a former PA, who can only reach their own leave, pay and documents.
     */
    public function formerPa(): static
    {
        return $this->state(['role' => 'Former PA', 'is_privileged' => false, 'is_employee' => false, 'is_former_pa' => true]);
    }

    /**
     * A team without an active subscription.
     */
    public function unsubscribed(): static
    {
        return $this->state(['subscribed' => false]);
    }

    /**
     * Switch exactly these optional modules on, and every other one off.
     */
    public function withModules(OptionalModule ...$enabled): static
    {
        return $this->state(['modules' => self::modules(...$enabled)]);
    }

    /**
     * Every optional module, mapped to whether it is one of those enabled.
     *
     * @return array<string, bool>
     */
    private static function modules(OptionalModule ...$enabled): array
    {
        $modules = [];

        foreach (OptionalModule::cases() as $module) {
            $modules[$module->value] = in_array($module, $enabled, true);
        }

        return $modules;
    }
}

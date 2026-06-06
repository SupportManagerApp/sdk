<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake automatic pay-enhancement settings — a flat singleton (`type()` is null,
 * so responses are a bare `{data: {...}}`, not a JSON:API document).
 *
 * The resource's `show()`/`update()` read `response.json('data')` directly; the
 * generic flat-singleton GET and PUT serve them, so no custom routes are needed.
 *
 * ```php
 * SupportManager::fake()->seed(AutomaticPayEnhancementSettingsFactory::new());
 * SupportManager::automaticPayEnhancements()->update(['bank_holiday_enhancement_id' => 9]);
 * ```
 */
final class AutomaticPayEnhancementSettingsFactory extends Factory
{
    public function path(): string
    {
        return 'payroll/automatic-pay-enhancements';
    }

    public function definition(int $index): array
    {
        return [
            'bank_holiday_enhancement_id' => 3,
            'christmas_enhancement_id' => 4,
            'holiday_cover_enhancement_id' => null,
            'sickness_cover_enhancement_id' => 5,
        ];
    }

    /**
     * Seed with no scenario mapped to any enhancement.
     */
    public function unconfigured(): static
    {
        return $this->state([
            'bank_holiday_enhancement_id' => null,
            'christmas_enhancement_id' => null,
            'holiday_cover_enhancement_id' => null,
            'sickness_cover_enhancement_id' => null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake pay enhancements — named rate multipliers.
 *
 * A plain JSON:API collection (type `pay-enhancements`) with full CRUD, all of
 * which the generic REST machinery serves; no custom routes needed.
 *
 * ```php
 * SupportManager::fake()->seed(PayEnhancementFactory::new()->count(3));
 * SupportManager::payEnhancements()->create(['name' => 'Sunday', 'enhancement_factor' => '1.00']);
 * ```
 */
final class PayEnhancementFactory extends Factory
{
    public function path(): string
    {
        return 'payroll/pay-enhancements';
    }

    public function type(): string
    {
        return 'pay-enhancements';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Enhancement {$number}",
            'description' => 'Weekend rate',
            'enhancement_factor' => '0.50',
            'enhancement_factor_label' => 'Time and a half',
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }
}

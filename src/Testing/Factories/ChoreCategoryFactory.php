<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake chore categories — a read-only resource, so only the generic index
 * (`query()`/`all()`) is exercised; generic GET against the path serves it.
 *
 * ```php
 * SupportManager::fake()->seed(ChoreCategoryFactory::new()->count(2));
 * SupportManager::choreCategories()->all();
 * ```
 */
final class ChoreCategoryFactory extends Factory
{
    public function path(): string
    {
        return 'household/chore-categories';
    }

    public function type(): string
    {
        return 'chore_categories';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Category {$number}",
            'color' => '#ff0000',
            'icon' => 'broom',
            'chores_count' => 4,
            'due_chores_count' => 1,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }
}

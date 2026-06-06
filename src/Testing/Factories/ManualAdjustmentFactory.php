<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake manual adjustments to a user's leave balance.
 *
 * A plain JSON:API collection — list, create, update and delete all come for
 * free from the resource path, so no custom routes are needed.
 *
 * ```php
 * SupportManager::fake()->seed(ManualAdjustmentFactory::new()->count(2));
 * SupportManager::leaveAdjustments()->query()->filter('user_id', 1)->get();
 * ```
 */
final class ManualAdjustmentFactory extends Factory
{
    public function path(): string
    {
        return 'leave/adjustments';
    }

    public function type(): string
    {
        return 'manual-adjustments';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "User {$number}"],
            'leave_year' => 2026,
            'hours_adjustment' => 5.0,
            'sleeps_adjustment' => -1.0,
            'reason' => "Reason {$number}",
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }
}

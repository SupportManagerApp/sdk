<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake annual leave entitlements.
 *
 * The resource is read-only, but a JSON:API collection still serves its
 * `query()`/`all()` reads generically from the resource path — no custom
 * routes required.
 *
 * ```php
 * SupportManager::fake()->seed(LeaveEntitlementFactory::new()->count(2));
 * SupportManager::leaveEntitlements()->all();
 * ```
 */
final class LeaveEntitlementFactory extends Factory
{
    public function path(): string
    {
        return 'leave/entitlements';
    }

    public function type(): string
    {
        return 'leave_entitlements';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "User {$number}"],
            'working_pattern_id' => 2,
            'working_pattern' => ['id' => 2, 'name' => 'Full Time'],
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'hours_holiday' => 224.0,
            'sleeping_nights_holiday' => 0.0,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake shift applications.
 *
 * An index-only JSON:API collection (`query()`, `all()`) plus a `DELETE /{id}`
 * for withdrawing one — both served by generic REST, so no custom routes are
 * needed.
 *
 * ```php
 * SupportManager::fake()->seed(ShiftApplicationFactory::new()->count(2));
 * SupportManager::shiftApplications()->query()->filter('status', 'Pending')->get();
 * SupportManager::shiftApplications()->cancel(1);
 * ```
 */
final class ShiftApplicationFactory extends Factory
{
    public function path(): string
    {
        return 'rota/shift-applications';
    }

    public function type(): string
    {
        return 'shift-applications';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'status' => 'Pending',
            'start_time' => '2026-06-06T09:00:00Z',
            'end_time' => '2026-06-06T17:00:00Z',
            'user' => ['id' => 7, 'name' => "Applicant {$number}"],
            'rota_entry' => [
                'id' => (string) $number,
                'attributes' => ['type' => 'Contracted Hours', 'duration' => 8.0],
            ],
            'reviewed_by' => null,
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
        ];
    }

    /**
     * Mark the application as approved.
     */
    public function approved(): static
    {
        return $this->state(['status' => 'Approved', 'reviewed_by' => 2]);
    }

    /**
     * Mark the application as rejected.
     */
    public function rejected(): static
    {
        return $this->state(['status' => 'Rejected', 'reviewed_by' => 2]);
    }
}

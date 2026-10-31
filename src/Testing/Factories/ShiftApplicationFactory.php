<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Data\Rota\NestedResource;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake shift applications.
 *
 * An index-only JSON:API collection (`query()`, `all()`) plus a `DELETE /{id}`
 * for withdrawing one, both served by generic REST. Like the platform, the
 * withdrawal 422s an application that is no longer pending, or one for a
 * sleepover segment (withdrawn as a whole through {@see ShiftBlockFactory}).
 *
 * ```php
 * SupportManager::fake()->seed(ShiftApplicationFactory::new()->count(2));
 * SupportManager::shiftApplications()->query()->filter('status', 'Pending')->get();
 * SupportManager::shiftApplications()->cancel(1);
 * ```
 */
final class ShiftApplicationFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/shift-applications';
    }

    public function type(): string
    {
        return 'shift_applications';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => $this->teamId(),
            'status' => 'Pending',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'user' => ['id' => 7, 'name' => "Applicant {$number}"],
            'rota_entry' => Json::nested('rota_entries', ['id' => (string) $number, 'type' => 'Contracted Hours', 'duration' => 8.0]),
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

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::delete('rota/shift-applications/{id}', static function (RecordStore $store, array $matches): mixed {
                $application = $store->find('rota/shift-applications', $matches[1]);

                if ($application === null) {
                    return null;
                }

                if (($application['status'] ?? null) !== 'Pending') {
                    return Json::message('Only pending applications can be retracted.', 422);
                }

                /** @var array<string, mixed> $entry */
                $entry = is_array($application['rota_entry'] ?? null) ? $application['rota_entry'] : [];
                $shift = NestedResource::flatten($entry);

                if (($shift['shift_block_id'] ?? null) !== null) {
                    return Json::message(ShiftFactory::SLEEPOVER_SEGMENT_MESSAGE, 422);
                }

                // Fall through to the generic delete.
                return null;
            }),
        ];
    }
}

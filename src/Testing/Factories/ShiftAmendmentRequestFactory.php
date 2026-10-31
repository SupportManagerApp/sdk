<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake shift amendment requests.
 *
 * An index-only JSON:API collection (`query()`, `all()`) served by generic
 * REST, so `filter[status]` and `filter[rota_entry_id]` work as equality
 * filters. New requests are made against a shift, so they are recorded by
 * {@see ShiftFactory}'s `requestAmendment()` route; seed this factory (even
 * with `count(0)`) to list them.
 *
 * ```php
 * SupportManager::fake()->seed(ShiftAmendmentRequestFactory::new()->count(2));
 * SupportManager::shiftAmendmentRequests()->query()->filter('status', 'Pending')->get();
 * ```
 */
final class ShiftAmendmentRequestFactory extends Factory
{
    public function path(): string
    {
        return 'rota/shift-amendment-requests';
    }

    public function type(): string
    {
        return 'shift_amendment_requests';
    }

    public function definition(int $index): array
    {
        return [
            'team_id' => $this->teamId(),
            'user_id' => 7,
            'rota_entry_id' => $index + 1,
            'proposed_start' => '2026-06-06T15:00:00+01:00',
            'proposed_end' => '2026-06-06T23:00:00+01:00',
            'requested_pay_enhancement_id' => null,
            'requested_tags' => [],
            'reason' => 'Stayed on to cover the handover',
            'status' => 'Pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
        ];
    }

    /**
     * Mark the request as approved.
     */
    public function approved(): static
    {
        return $this->state(['status' => 'Approved', 'reviewed_by' => 2, 'reviewed_at' => '2026-06-07T09:00:00Z']);
    }

    /**
     * Mark the request as rejected.
     */
    public function rejected(): static
    {
        return $this->state(['status' => 'Rejected', 'reviewed_by' => 2, 'reviewed_at' => '2026-06-07T09:00:00Z']);
    }
}

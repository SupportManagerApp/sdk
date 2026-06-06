<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake leave (holiday) requests.
 *
 * Generic CRUD (list, find, create, update, delete) comes for free from the
 * resource path; this factory adds the approval-workflow transitions —
 * {@see approve()}, {@see reject()}, {@see cancel()} — as `{id}/{action}`
 * routes that move a request's status and merge any approver comments/reason.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     LeaveRequestFactory::new()->count(3),
 *     LeaveRequestFactory::new()->approved(),
 * );
 * SupportManager::leaveRequests()->approve(1);
 * ```
 */
final class LeaveRequestFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'leave/requests';
    }

    public function type(): string
    {
        return 'leave-requests';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "User {$number}"],
            'start_date' => '2026-07-01T08:00:00Z',
            'end_date' => '2026-07-05T08:00:00Z',
            'type' => 'Whole Days',
            'type_label' => 'Whole Days',
            'status' => 'Pending',
            'hours' => 30.0,
            'sleeps' => 0.0,
            'requester_comments' => null,
            'approver_comments' => null,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }

    /**
     * Mark the request as approved.
     */
    public function approved(): static
    {
        return $this->state(['status' => 'Approved']);
    }

    /**
     * Mark the request as rejected, optionally with approver comments.
     */
    public function rejected(?string $comments = null): static
    {
        return $this->state(['status' => 'Rejected', 'approver_comments' => $comments]);
    }

    /**
     * Mark the request as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(['status' => 'Cancelled']);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::patch('leave/requests/{id}/approve', static function (RecordStore $store, array $matches): mixed {
                $updated = $store->update('leave/requests', $matches[1], ['status' => 'Approved']);

                return $updated === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('leave-requests', $updated));
            }),

            FakeRoute::patch('leave/requests/{id}/reject', static function (RecordStore $store, array $matches, Request $request): mixed {
                $comments = $request->data()['approver_comments'] ?? null;
                $updated = $store->update('leave/requests', $matches[1], [
                    'status' => 'Rejected',
                    'approver_comments' => is_string($comments) ? $comments : null,
                ]);

                return $updated === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('leave-requests', $updated));
            }),

            FakeRoute::patch('leave/requests/{id}/cancel', static function (RecordStore $store, array $matches): mixed {
                $updated = $store->update('leave/requests', $matches[1], ['status' => 'Cancelled']);

                return $updated === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('leave-requests', $updated));
            }),
        ];
    }
}

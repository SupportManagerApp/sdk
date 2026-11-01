<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake leave (holiday) requests.
 *
 * Generic CRUD (find, create, update, delete) comes for free from the resource
 * path. The index is a custom route so its `meta` carries the platform's
 * `status_counts` (every seeded request, ignoring filters and paging), with the
 * usual exact-match `filter[key]` equality. This factory also adds the
 * approval-workflow transitions —
 * {@see approve()}, {@see reject()}, {@see cancel()} — as `{id}/{action}`
 * routes that move a request's status and merge any approver comments/reason.
 * Each record carries the viewer's `meta.can` permissions, which a cancelled
 * request loses.
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
        return 'leave_requests';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => $this->teamId(),
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "User {$number}"],
            'start_date' => '2026-07-01T08:00:00Z',
            'end_date' => '2026-07-05T08:00:00Z',
            'type' => 'Whole Days',
            'type_label' => 'Whole Days',
            'leave_type' => 'annual_leave',
            'leave_type_label' => 'Annual leave',
            'status' => 'Pending',
            'hours' => 30.0,
            'sleeps' => 0.0,
            'requester_comments' => null,
            'approver_comments' => null,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
            'meta' => ['can' => self::can(editDates: true, cancel: true)],
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
        return $this->state(['status' => 'Cancelled', 'meta' => ['can' => self::can(editDates: false, cancel: false)]]);
    }

    /**
     * Make the request's dates no longer editable (approved or rejected and
     * starting within 28 days), as `meta.can.edit_dates` reports it.
     */
    public function datesLocked(): static
    {
        return $this->state(['meta' => ['can' => self::can(editDates: false, cancel: true)]]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('leave/requests', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $records = $store->all('leave/requests');
                $counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Cancelled' => 0];

                foreach ($records as $record) {
                    $status = (string) ($record['status'] ?? '');

                    if (isset($counts[$status])) {
                        $counts[$status]++;
                    }
                }

                return $fake->paginate('leave/requests', 'leave_requests', $fake->filter($records, $request), $request, ['status_counts' => $counts]);
            }),

            // The platform files a new request under the signed-in person, as Pending, in the current team.
            FakeRoute::post('leave/requests', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $user = $fake->signedInUser();
                $leave = [
                    'team_id' => $fake->teamId(),
                    ...($user === null ? [] : ['user_id' => $user->id, 'user' => ['id' => $user->id, 'name' => $user->name]]),
                    'status' => 'Pending',
                    ...$request->data(),
                    'id' => $request->data()['id'] ?? $store->nextId('leave/requests'),
                ];

                return Http::response(Json::item('leave_requests', $store->append('leave/requests', $leave)), 201);
            }),

            FakeRoute::action('PATCH', 'leave/requests/{id}/approve', static fn (): array => ['status' => 'Approved']),

            FakeRoute::action('PATCH', 'leave/requests/{id}/reject', static function (array $leave, Request $request): array {
                $comments = $request->data()['approver_comments'] ?? null;

                return ['status' => 'Rejected', 'approver_comments' => is_string($comments) ? $comments : null];
            }),

            FakeRoute::action('PATCH', 'leave/requests/{id}/cancel', static fn (): array => [
                'status' => 'Cancelled',
                'meta' => ['can' => self::can(editDates: false, cancel: false)],
            ]),
        ];
    }

    /**
     * The viewer's `meta.can` block for a request.
     *
     * @return array{edit_dates: bool, cancel: bool, request_reapproval: bool}
     */
    private static function can(bool $editDates, bool $cancel): array
    {
        return ['edit_dates' => $editDates, 'cancel' => $cancel, 'request_reapproval' => false];
    }
}

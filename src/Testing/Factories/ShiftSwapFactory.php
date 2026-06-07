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
 * Fake shift swap requests and their approval workflow.
 *
 * Generic CRUD (list, find) plus `create()` come from the resource path; this
 * factory adds the non-RESTful workflow transitions — `cancel`, `accept`,
 * `decline`, `approve`, `reject` (each `POST .../{id}/{action}`, returning the
 * updated request) — and the `POST .../{id}/offers` action that creates a new
 * {@see ShiftSwapOffer} against an open swap.
 *
 * Each seeded swap also carries the `scopes` it belongs to (the platform's
 * `filter[scope]` values: `incoming` for directed-at-me, `available` for
 * open-for-offers, `mine` for ones I requested). A scope-aware list route
 * narrows the collection to `filter[scope]=…` so a feature test can distinguish
 * the widget's directed and open lists; an unfiltered list returns every swap.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     ShiftSwapFactory::new(),                 // incoming (directed)
 *     ShiftSwapFactory::new()->openForOffers(), // available (open)
 * );
 * SupportManager::shiftSwaps()->query()->filter('scope', 'available')->get();
 * SupportManager::shiftSwaps()->accept(1, ['respondent_shift_ids' => [12]]);
 * SupportManager::shiftSwaps()->offer(1, ['offered_shift_ids' => [34]]);
 * ```
 */
final class ShiftSwapFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/shift-swaps';
    }

    public function type(): string
    {
        return 'shift-swap-requests';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'type' => 'Directed',
            'status' => 'Pending Response',
            'status_label' => 'Pending Response',
            'expires_at' => '2026-06-13T09:00:00Z',
            'is_expired' => false,
            'notes' => null,
            'requester' => ['id' => 1, 'name' => "Requester {$number}"],
            'target_user' => ['id' => 2, 'name' => 'Grace'],
            'reviewer' => null,
            'reviewed_at' => null,
            'requester_entries' => [
                ['id' => '10', 'attributes' => ['side' => 'requester', 'effective_duration' => 8.0]],
            ],
            'respondent_entries' => [],
            'requester_total_hours' => 8.0,
            'respondent_total_hours' => 8.0,
            'hours_are_equal' => true,
            'requires_manager_approval' => false,
            'offers' => [],
            'offers_count' => 0,
            'scopes' => ['incoming'],
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
        ];
    }

    /**
     * An open-for-offers swap (no directed target) — in the `available` scope.
     */
    public function openForOffers(): static
    {
        return $this->state([
            'type' => 'Open',
            'status' => 'Open for Offers',
            'status_label' => 'Open for Offers',
            'target_user' => null,
            'scopes' => ['available'],
        ]);
    }

    /**
     * A swap the current user raised — in the `mine` scope.
     */
    public function mine(): static
    {
        return $this->state(['scopes' => ['mine']]);
    }

    /**
     * A swap pending manager approval.
     */
    public function pendingApproval(): static
    {
        return $this->state([
            'status' => 'Pending Manager Approval',
            'status_label' => 'Pending Manager Approval',
            'requires_manager_approval' => true,
        ]);
    }

    public function fakeRoutes(): array
    {
        $transition = static function (string $status): FakeRoute {
            return FakeRoute::post("rota/shift-swaps/{id}/{$status}", static function (RecordStore $store, array $matches) use ($status): mixed {
                $updated = $store->update('rota/shift-swaps', $matches[1], self::transitionAttributes($status));

                return $updated === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('shift-swap-requests', $updated));
            });
        };

        return [
            // Scope-aware list: when filter[scope] is present, narrow to the
            // swaps in that scope (else fall through to the generic list).
            FakeRoute::get('rota/shift-swaps', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $filters = $fake->query($request)['filter'] ?? null;
                $scope = is_array($filters) ? ($filters['scope'] ?? null) : null;

                if ($scope === null) {
                    return null;
                }

                $records = array_values(array_filter(
                    $store->all('rota/shift-swaps'),
                    static fn (array $record): bool => in_array(
                        (string) $scope,
                        array_map('strval', (array) ($record['scopes'] ?? [])),
                        true,
                    ),
                ));

                return $fake->paginate('rota/shift-swaps', 'shift-swap-requests', $records, $request);
            }),

            $transition('cancel'),
            $transition('accept'),
            $transition('decline'),
            $transition('approve'),
            $transition('reject'),

            FakeRoute::post('rota/shift-swaps/{id}/offers', static function (RecordStore $store, array $matches, Request $request): mixed {
                $swap = $store->find('rota/shift-swaps', $matches[1]);

                if ($swap === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $offer = [
                    'id' => $store->nextId('rota/shift-swap-offers'),
                    'status' => 'Pending Response',
                    'notes' => null,
                    'user' => ['id' => 3, 'name' => 'Offerer'],
                    'entries' => [],
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                ];

                $store->append('rota/shift-swap-offers', $offer);

                return Http::response(Json::item('shift-swap-offers', $offer), 201);
            }),
        ];
    }

    /**
     * The status mutation each workflow transition applies.
     *
     * @return array<string, mixed>
     */
    private static function transitionAttributes(string $action): array
    {
        $status = match ($action) {
            'cancel' => 'Cancelled',
            'accept' => 'Both Agreed',
            'decline' => 'Declined',
            'approve' => 'Approved',
            'reject' => 'Rejected',
            default => 'Pending Response',
        };

        return ['status' => $status, 'status_label' => $status];
    }
}

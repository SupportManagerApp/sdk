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
 * Fake the privileged admin leave surface (`leave/admin/*`).
 *
 * One factory standing in for the whole admin grouping, since it has no single
 * DTO. Every record carries a `_kind` discriminator so the various endpoints —
 * the flat pending queue and bulk approve/reject, the flat team balances, the
 * JSON:API adjustments/entitlements registers (entitlements writable here), and
 * the flat dashboard summary — are all served from the one seeded bag.
 *
 * By default {@see definition()} seeds a representative record of each kind;
 * narrow with the state helpers (e.g. {@see onlyPending()}) when a test needs a
 * single dataset.
 *
 * ```php
 * SupportManager::fake()->seed(AdminLeaveFactory::new());
 * SupportManager::leaveAdmin()->pendingRequests();
 * SupportManager::leaveAdmin()->bulkApprove([7]);
 * ```
 */
final class AdminLeaveFactory extends Factory implements ProvidesFakeRoutes
{
    private const PATH = 'leave/admin';

    public function path(): string
    {
        return self::PATH;
    }

    /**
     * A representative record of every admin kind. `type()` is null, so these
     * are stored verbatim (no JSON:API id assignment); ids are baked in here.
     */
    public function definition(int $index): array
    {
        return [
            '_kind' => 'seed',
            'pending' => [
                [
                    'id' => 7,
                    'team_id' => 42,
                    'user_id' => 3,
                    'user' => ['id' => 3, 'name' => 'Ada'],
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-07-05',
                    'type' => 'Whole Days',
                    'type_label' => 'Whole Days',
                    'status' => 'Pending',
                    'hours' => 30.0,
                    'sleeps' => 0.0,
                    'requester_comments' => 'Trip',
                    'approver_comments' => null,
                ],
            ],
            'balances' => [
                [
                    'user' => ['id' => 3, 'name' => 'Ada'],
                    'leave_year' => 2026,
                    'remaining' => ['hours' => 200.0, 'sleeps' => 5.0],
                ],
            ],
            'adjustments' => [
                [
                    'id' => '5',
                    'team_id' => 42,
                    'user_id' => 3,
                    'user' => ['id' => 3, 'name' => 'Ada'],
                    'leave_year' => 2026,
                    'hours_adjustment' => 4.0,
                    'sleeps_adjustment' => 0.0,
                    'reason' => 'Comp',
                ],
            ],
            'entitlements' => [
                [
                    'id' => '6',
                    'team_id' => 42,
                    'user_id' => 3,
                    'user' => ['id' => 3, 'name' => 'Ada'],
                    'hours_holiday' => 224.0,
                    'sleeping_nights_holiday' => 0.0,
                ],
            ],
            'summary' => [
                'pending_requests_count' => 3,
                'approved_this_month' => 5,
                'team_members_on_leave_today' => 1,
                'upcoming_leave_next_7_days' => [],
                'low_balance_warnings' => [],
            ],
        ];
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('leave/admin/requests/pending', static function (RecordStore $store): mixed {
                /** @var list<array<string, mixed>> $pending */
                $pending = self::bag($store)['pending'] ?? [];

                return Http::response(['data' => array_values($pending), 'meta' => ['current_page' => 1, 'total' => count($pending)]]);
            }),

            FakeRoute::post('leave/admin/requests/bulk-approve', static function (RecordStore $store, array $matches, Request $request): mixed {
                $ids = $request->data()['request_ids'] ?? [];

                return Http::response(['message' => count((array) $ids).' request(s) approved successfully', 'approved_count' => count((array) $ids)]);
            }),

            FakeRoute::post('leave/admin/requests/bulk-reject', static function (RecordStore $store, array $matches, Request $request): mixed {
                $ids = $request->data()['request_ids'] ?? [];

                return Http::response(['message' => count((array) $ids).' request(s) rejected successfully', 'rejected_count' => count((array) $ids)]);
            }),

            FakeRoute::get('leave/admin/balances/{id}', static function (RecordStore $store, array $matches): mixed {
                /** @var list<array<string, mixed>> $balances */
                $balances = self::bag($store)['balances'] ?? [];

                foreach ($balances as $balance) {
                    $user = is_array($balance['user'] ?? null) ? $balance['user'] : [];

                    if ((string) ($user['id'] ?? '') === $matches[1]) {
                        return Http::response(['data' => $balance]);
                    }
                }

                return Http::response(['data' => $balances[0] ?? null]);
            }),

            FakeRoute::get('leave/admin/balances', static function (RecordStore $store): mixed {
                /** @var list<array<string, mixed>> $balances */
                $balances = self::bag($store)['balances'] ?? [];

                return Http::response(['data' => array_values($balances), 'meta' => ['current_page' => 1, 'total' => count($balances)]]);
            }),

            FakeRoute::get('leave/admin/adjustments', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                /** @var list<array<string, mixed>> $adjustments */
                $adjustments = self::bag($store)['adjustments'] ?? [];

                return $fake->paginate('leave/admin/adjustments', 'manual-adjustments', array_values($adjustments), $request);
            }),

            FakeRoute::get('leave/admin/entitlements', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                /** @var list<array<string, mixed>> $entitlements */
                $entitlements = self::bag($store)['entitlements'] ?? [];

                return $fake->paginate('leave/admin/entitlements', 'leave-entitlements', array_values($entitlements), $request);
            }),

            FakeRoute::post('leave/admin/entitlements', static function (RecordStore $store, array $matches, Request $request): mixed {
                $record = $request->data();
                $record['id'] = (string) ($record['id'] ?? '99');

                self::appendKind($store, 'entitlements', $record);

                return Http::response(Json::item('leave-entitlements', $record), 201);
            }),

            FakeRoute::put('leave/admin/entitlements/{id}', static function (RecordStore $store, array $matches, Request $request): mixed {
                $record = [...$request->data(), 'id' => $matches[1]];

                return Http::response(Json::item('leave-entitlements', $record));
            }),

            FakeRoute::delete('leave/admin/entitlements/{id}', static fn (): mixed => Http::response(['message' => 'Deleted'])),

            FakeRoute::get('leave/admin/summary', static function (RecordStore $store): mixed {
                /** @var array<string, mixed> $summary */
                $summary = self::bag($store)['summary'] ?? [];

                return Http::response(['data' => $summary]);
            }),
        ];
    }

    /**
     * The single seeded admin record (the discriminated bag of every kind).
     *
     * @return array<string, mixed>
     */
    private static function bag(RecordStore $store): array
    {
        return $store->all(self::PATH)[0] ?? [];
    }

    /**
     * Append a record to one kind's list within the seeded bag.
     *
     * @param  array<string, mixed>  $record
     */
    private static function appendKind(RecordStore $store, string $kind, array $record): void
    {
        $bag = self::bag($store);
        /** @var list<array<string, mixed>> $list */
        $list = is_array($bag[$kind] ?? null) ? $bag[$kind] : [];
        $bag[$kind] = [...$list, $record];

        $store->put(self::PATH, [$bag]);
    }
}

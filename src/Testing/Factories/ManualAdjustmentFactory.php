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
 * Fake manual adjustments to a user's leave balance.
 *
 * A plain JSON:API collection — list, create, update and delete all come for
 * free from the resource path. This factory adds `mine`, the current user's own
 * adjustments for a year with their totals. The fake has no notion of the
 * current user, so `mine` lists every seeded adjustment in the requested
 * `?year=` (or the first seeded adjustment's year), oldest first.
 *
 * ```php
 * SupportManager::fake()->seed(ManualAdjustmentFactory::new()->count(2));
 * SupportManager::leaveAdjustments()->query()->filter('user_id', 1)->get();
 * SupportManager::leaveAdjustments()->mine(['year' => 2026]);
 * ```
 */
final class ManualAdjustmentFactory extends Factory implements ProvidesFakeRoutes
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

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('leave/adjustments/mine', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $all = $store->all('leave/adjustments');
                $year = (int) ($fake->query($request)['year'] ?? $all[0]['leave_year'] ?? 2026);

                $adjustments = array_values(array_filter(
                    $all,
                    static fn (array $adjustment): bool => (int) ($adjustment['leave_year'] ?? 0) === $year,
                ));
                usort($adjustments, static fn (array $a, array $b): int => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));

                return Http::response(Json::collection('manual-adjustments', $adjustments, [
                    'team_id' => 42,
                    'year' => $year,
                    'totals' => [
                        'hours_adjustment' => (float) array_sum(array_column($adjustments, 'hours_adjustment')),
                        'sleeps_adjustment' => (float) array_sum(array_column($adjustments, 'sleeps_adjustment')),
                    ],
                ]));
            }),
        ];
    }
}

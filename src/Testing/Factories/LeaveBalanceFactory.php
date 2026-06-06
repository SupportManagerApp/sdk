<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake leave balance — a flat endpoint (`type()` is null, so responses are a
 * bare `{data: {year, hours, sleeps}}`, not a JSON:API document).
 *
 * The single seeded record is the current user's balance, served at
 * `leave/balance`; the same record answers another user's balance
 * (`leave/balance/{userId}`) and the prospective-request {@see calculate()}
 * preview (`leave/balance/calculate`).
 *
 * ```php
 * SupportManager::fake()->seed(LeaveBalanceFactory::new());
 * SupportManager::leaveBalance()->show()->hours->remaining;
 * ```
 */
final class LeaveBalanceFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'leave/balance';
    }

    public function definition(int $index): array
    {
        return [
            'year' => 2026,
            'hours' => [
                'entitlement' => 224.0,
                'pending' => 8.0,
                'approved' => 16.0,
                'remaining' => 200.0,
                'percentages' => ['used' => 7],
            ],
            'sleeps' => [
                'entitlement' => 10.0,
                'pending' => 1.0,
                'approved' => 2.0,
                'remaining' => 7.0,
                'percentages' => null,
            ],
        ];
    }

    public function fakeRoutes(): array
    {
        return [
            // Another user's balance: serve the one seeded record.
            FakeRoute::get('leave/balance/{id}', static function (RecordStore $store): mixed {
                $record = $store->all('leave/balance')[0] ?? null;

                return $record === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item(null, $record));
            }),

            // Prospective-request preview: echo the seeded balance back.
            FakeRoute::post('leave/balance/calculate', static function (RecordStore $store): mixed {
                $record = $store->all('leave/balance')[0] ?? null;

                return $record === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item(null, $record));
            }),
        ];
    }
}

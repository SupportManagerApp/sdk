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
 * Fake car journeys.
 *
 * Generic CRUD (list, find, create, update, delete) comes for free from the
 * resource path; this factory adds the module's non-RESTful actions — the single
 * {@see ongoing()} trip, {@see start()}ing one (which auto-closes any open trip,
 * as the server does), and {@see finish()}ing one by id.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     CarJourneyFactory::new()->count(5),
 *     CarJourneyFactory::new()->ongoing()->state(['driver_name' => 'Ada']),
 * );
 * ```
 */
final class CarJourneyFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'car-journeys';
    }

    public function type(): string
    {
        return 'car-journeys';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'driver_name' => "Driver {$number}",
            'trip_start' => '2026-01-01T09:00:00Z',
            'trip_end' => '2026-01-01T10:00:00Z',
            'is_ongoing' => false,
            'duration_minutes' => 60,
            'comments' => null,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * Mark the journey as still open (no end time).
     */
    public function ongoing(): static
    {
        return $this->state([
            'is_ongoing' => true,
            'trip_end' => null,
            'duration_minutes' => null,
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('car-journeys/ongoing', static function (RecordStore $store): mixed {
                foreach ($store->all('car-journeys') as $record) {
                    if ($record['is_ongoing'] ?? false) {
                        return Http::response(Json::item('car-journeys', $record));
                    }
                }

                return Http::response(['message' => 'No ongoing journey found.'], 404);
            }),

            FakeRoute::post('car-journeys/start', static function (RecordStore $store, array $matches, Request $request): mixed {
                foreach ($store->all('car-journeys') as $record) {
                    if ($record['is_ongoing'] ?? false) {
                        $store->update('car-journeys', (string) $record['id'], [
                            'is_ongoing' => false,
                            'trip_end' => '2026-01-01T10:00:00Z',
                        ]);
                    }
                }

                $body = $request->data();
                $started = $store->append('car-journeys', [
                    'id' => $store->nextId('car-journeys'),
                    'driver_name' => $body['driver_name'] ?? '',
                    'comments' => $body['comments'] ?? null,
                    'trip_start' => '2026-01-01T09:00:00Z',
                    'trip_end' => null,
                    'is_ongoing' => true,
                    'duration_minutes' => null,
                ]);

                return Http::response(Json::item('car-journeys', $started), 201);
            }),

            FakeRoute::patch('car-journeys/{id}/finish', static function (RecordStore $store, array $matches): mixed {
                $finished = $store->update('car-journeys', $matches[1], [
                    'is_ongoing' => false,
                    'trip_end' => '2026-01-01T10:00:00Z',
                    'duration_minutes' => 60,
                ]);

                return $finished === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('car-journeys', $finished));
            }),
        ];
    }
}

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
 * {@see ongoing()} trip, {@see start()}ing one (refused with a 422 while another
 * is open; the driver is {@see startedBy()}'s name, standing in for the
 * platform's signed-in user), and {@see finish()}ing one by id — and, like the
 * platform, logging a journey directly (`create()`) ends any journey still
 * ongoing. Seeded journeys are
 * editable by default; {@see locked()} clears their `meta.can` flags.
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
    private string $driverName = 'Fake User';

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
            'meta' => ['can' => ['update' => true, 'delete' => true]],
        ];
    }

    /**
     * Seed the journey as one the authenticated user may no longer change
     * (someone else's, or past the 12-hour edit window).
     */
    public function locked(): static
    {
        return $this->state(['meta' => ['can' => ['update' => false, 'delete' => false]]]);
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

    /**
     * The driver a started journey records. The platform uses the signed-in
     * user's name; the fake session has no user, so it is set here.
     */
    public function startedBy(string $name): static
    {
        $clone = clone $this;
        $clone->driverName = $name;

        return $clone;
    }

    public function fakeRoutes(): array
    {
        $driverName = $this->driverName;

        return [
            // Logging a journey ends any ongoing one. Like the platform's
            // StoreCarJourneyRequest, the body carries the trip as separate
            // date and time fields, stored as the resource's `trip_start` /
            // `trip_end` timestamps.
            FakeRoute::post('car-journeys', static function (RecordStore $store, array $matches, Request $request): mixed {
                foreach ($store->all('car-journeys') as $record) {
                    if ($record['is_ongoing'] ?? false) {
                        $store->update('car-journeys', (string) $record['id'], [
                            'is_ongoing' => false,
                            'trip_end' => '2026-01-01T10:00:00Z',
                        ]);
                    }
                }

                $created = $store->append('car-journeys', [
                    'id' => $store->nextId('car-journeys'),
                    'comments' => null,
                    'created_at' => '2026-01-01T09:00:00Z',
                    'updated_at' => '2026-01-01T09:00:00Z',
                    'meta' => ['can' => ['update' => true, 'delete' => true]],
                    ...self::journeyAttributes($request->data()),
                ]);

                return Http::response(Json::item('car-journeys', $created), 201);
            }),

            // Like the platform's UpdateCarJourneyRequest, an edit resends the
            // whole trip as date and time fields.
            FakeRoute::patch('car-journeys/{id}', static function (RecordStore $store, array $matches, Request $request): mixed {
                $updated = $store->update('car-journeys', $matches[1], self::journeyAttributes($request->data()));

                return $updated === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('car-journeys', $updated));
            }),

            FakeRoute::get('car-journeys/ongoing', static function (RecordStore $store): mixed {
                foreach ($store->all('car-journeys') as $record) {
                    if ($record['is_ongoing'] ?? false) {
                        return Http::response(Json::item('car-journeys', $record));
                    }
                }

                return Http::response(['message' => 'No ongoing journey found.'], 404);
            }),

            // Like the platform, starting refuses while another journey is open.
            FakeRoute::post('car-journeys/start', static function (RecordStore $store) use ($driverName): mixed {
                foreach ($store->all('car-journeys') as $record) {
                    if ($record['is_ongoing'] ?? false) {
                        return Http::response(['message' => 'A journey is already in progress.', 'meta' => ['team_id' => 42]], 422);
                    }
                }

                $started = $store->append('car-journeys', [
                    'id' => $store->nextId('car-journeys'),
                    'driver_name' => $driverName,
                    'comments' => null,
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

    /**
     * The stored attributes for a create/update body of separate trip date and
     * time fields, as the platform's form requests take them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function journeyAttributes(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(['driver_name', 'comments']));

        if (isset($data['trip_start_date'], $data['trip_start_time'])) {
            $attributes['trip_start'] = "{$data['trip_start_date']}T{$data['trip_start_time']}:00Z";
        }

        if (array_key_exists('trip_end_date', $data)) {
            $tripEnd = isset($data['trip_end_date'], $data['trip_end_time'])
                ? "{$data['trip_end_date']}T{$data['trip_end_time']}:00Z"
                : null;
            $attributes['trip_end'] = $tripEnd;
            $attributes['is_ongoing'] = $tripEnd === null;
            $attributes['duration_minutes'] = $tripEnd !== null && isset($attributes['trip_start'])
                ? intdiv((int) strtotime($tripEnd) - (int) strtotime((string) $attributes['trip_start']), 60)
                : null;
        }

        return $attributes;
    }
}

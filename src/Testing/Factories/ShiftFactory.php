<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake rota shifts (the platform's `rota-entries`).
 *
 * Generic CRUD (list, find, update, delete) comes for free from the resource
 * path; this factory adds the module's read feeds — {@see today()}'s shifts,
 * the {@see upcoming()} window and the unassigned {@see available()} shifts —
 * plus the `apply()` action, which returns the created
 * {@see ShiftApplicationFactory} shape.
 *
 * `create()` is answered by a custom route rather than generic REST, because
 * the platform takes quick-add fields (`assignee_id`, `date`, `start_time`, …)
 * and stores a rota entry: it validates the required fields (422), defaults a
 * missing end to an hour, rolls an end at or before the start overnight, and
 * records published overtime (a sleeping night when `is_sleeping_night`).
 *
 * ```php
 * SupportManager::fake()->seed(
 *     ShiftFactory::new()->count(3),
 *     ShiftFactory::new()->available(),
 *     ShiftFactory::new()->upcoming(),
 * );
 * SupportManager::shifts()->today();
 * SupportManager::shifts()->upcoming(['limit' => 5]);
 * SupportManager::shifts()->create(['assignee_id' => 7, 'date' => '2026-08-17', 'start_time' => '22:00', 'end_time' => '07:00']);
 * SupportManager::shifts()->apply($id);
 * ```
 */
final class ShiftFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/shifts';
    }

    public function type(): string
    {
        return 'rota-entries';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'type' => 'Contracted Hours',
            'type_label' => 'Contracted Hours',
            'start' => '2026-06-06T09:00:00Z',
            'end' => '2026-06-06T17:00:00Z',
            'duration' => 8.0,
            'is_published' => true,
            'is_available' => false,
            'is_sleeping_night' => false,
            'is_overtime' => false,
            'notes' => null,
            'shift_applications_count' => 0,
            'user' => ['id' => 7, 'name' => "User {$number}", 'avatar' => 'https://example.test/avatar.png'],
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
        ];
    }

    /**
     * Mark the shift as today's date so {@see today()} returns it.
     */
    public function today(): static
    {
        return $this->state(['is_today' => true]);
    }

    /**
     * Mark the shift as still to come so {@see upcoming()} returns it.
     */
    public function upcoming(): static
    {
        return $this->state(['is_upcoming' => true]);
    }

    /**
     * Mark the shift as unassigned and open for application.
     */
    public function available(): static
    {
        return $this->state([
            'is_available' => true,
            'user' => null,
            'shift_applications_count' => 0,
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('rota/shifts', static function (RecordStore $store, array $matches, Request $request): mixed {
                $input = $request->data();
                $missing = array_values(array_filter(
                    ['assignee_id', 'date', 'start_time'],
                    static fn (string $field): bool => ! isset($input[$field]) || $input[$field] === '',
                ));

                if ($missing !== []) {
                    return Http::response([
                        'message' => 'The information provided was invalid.',
                        'errors' => array_fill_keys($missing, ['This field is required.']),
                    ], 422);
                }

                $start = self::wallClock((string) $input['date'], (string) $input['start_time']);
                $end = isset($input['end_time'])
                    ? self::wallClock((string) $input['date'], (string) $input['end_time'])
                    : $start->modify('+1 hour');

                // "10pm to 7am" is an overnight shift, not a negative one.
                if ($end <= $start) {
                    $end = $end->modify('+1 day');
                }

                $sleepingNight = (bool) ($input['is_sleeping_night'] ?? false);
                $type = $sleepingNight ? 'Overtime Sleeping Night' : 'Overtime Hours';

                $shift = $store->append('rota/shifts', [
                    'id' => $store->nextId('rota/shifts'),
                    'team_id' => 42,
                    'user_id' => (int) $input['assignee_id'],
                    'type' => $type,
                    'type_label' => $type,
                    'start' => $start->format(DATE_ATOM),
                    'end' => $end->format(DATE_ATOM),
                    'duration' => ($end->getTimestamp() - $start->getTimestamp()) / 3600,
                    'is_published' => true,
                    'is_available' => false,
                    'is_sleeping_night' => $sleepingNight,
                    'is_overtime' => true,
                    'is_private' => (bool) ($input['is_private'] ?? false),
                    'notes' => $input['notes'] ?? null,
                    'tags' => $input['tags'] ?? [],
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                ]);

                return Http::response(Json::item('rota-entries', $shift), 201);
            }),

            FakeRoute::get('rota/shifts/upcoming', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $query = $fake->query($request);
                $limit = max(1, min((int) ($query['limit'] ?? 10), 50));

                $records = array_slice(array_values(array_filter(
                    $store->all('rota/shifts'),
                    static fn (array $r): bool => (bool) ($r['is_upcoming'] ?? false),
                )), 0, $limit);

                // Never paginated: the whole answer is the one body.
                return Http::response(['data' => Json::collection('rota-entries', $records)['data'], 'meta' => [
                    'team_id' => 42,
                    'mode' => in_array($query['mode'] ?? null, ['scheduled', 'team', 'available'], true) ? $query['mode'] : 'scheduled',
                    'days' => max(1, min((int) ($query['days'] ?? 2), 30)),
                    'limit' => $limit,
                ]]);
            }),

            FakeRoute::get('rota/shifts/today', static function (RecordStore $store): mixed {
                $records = array_values(array_filter(
                    $store->all('rota/shifts'),
                    static fn (array $r): bool => (bool) ($r['is_today'] ?? false),
                ));

                return Http::response(Json::collection('rota-entries', $records));
            }),

            FakeRoute::get('rota/shifts/available', static function (RecordStore $store): mixed {
                $records = array_values(array_filter(
                    $store->all('rota/shifts'),
                    static fn (array $r): bool => (bool) ($r['is_available'] ?? false),
                ));

                return Http::response(Json::collection('rota-entries', $records));
            }),

            FakeRoute::post('rota/shifts/{id}/apply', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $shift = $store->find('rota/shifts', $matches[1]);

                if ($shift === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $application = [
                    'id' => $store->nextId('rota/shift-applications'),
                    'team_id' => 42,
                    'status' => 'Pending',
                    'start_time' => $shift['start'] ?? null,
                    'end_time' => $shift['end'] ?? null,
                    'user' => ['id' => 7, 'name' => 'Applicant'],
                    'rota_entry' => ['id' => (string) $shift['id'], 'attributes' => $shift],
                    'reviewed_by' => null,
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                ];

                $store->append('rota/shift-applications', $application);

                return Http::response(Json::collection('shift-applications', [$application]), 201);
            }),
        ];
    }

    /**
     * A `Y-m-d` date and `H:i` time as one wall-clock instant.
     */
    private static function wallClock(string $date, string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("{$date} {$time}", new DateTimeZone('UTC'));
    }
}

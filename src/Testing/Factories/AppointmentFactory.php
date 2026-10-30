<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake one-off appointments.
 *
 * Generic CRUD (list, find, update, delete) comes from the resource path; this
 * factory adds the `upcoming()` feed mirroring the dashboard widget.
 *
 * `create()` is answered by a custom route rather than generic REST, because
 * the platform takes quick-add fields (`date`, `start_time`, …) and stores
 * `start`/`end`: it validates the required fields (422), runs a timed
 * appointment without an end for `duration_minutes` (or an hour), rolls an end
 * at or before the start past midnight, and spans an all-day one from the
 * start of `date` to the end of `end_date`.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     AppointmentFactory::new()->count(2),
 *     AppointmentFactory::new()->upcoming(),
 * );
 * SupportManager::appointments()->upcoming();
 * SupportManager::appointments()->create(['title' => 'Dentist', 'date' => '2026-08-18', 'start_time' => '15:30']);
 * ```
 */
final class AppointmentFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/appointments';
    }

    public function type(): string
    {
        return 'appointments';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'title' => "Appointment {$number}",
            'location' => 'HQ',
            'notes' => null,
            'is_virtual' => false,
            'is_all_day' => false,
            'is_private' => false,
            'created_by' => 7,
            'start' => '2026-06-07T10:00:00Z',
            'end' => '2026-06-07T11:00:00Z',
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
        ];
    }

    /**
     * A virtual (online) appointment.
     */
    public function virtual(): static
    {
        return $this->state(['is_virtual' => true, 'location' => null]);
    }

    /**
     * Mark the appointment as appearing in the upcoming feed.
     */
    public function upcoming(): static
    {
        return $this->state(['is_upcoming' => true]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('rota/appointments', static function (RecordStore $store, array $matches, Request $request): mixed {
                $input = $request->data();
                $isAllDay = (bool) ($input['is_all_day'] ?? false);
                $required = $isAllDay ? ['title', 'date'] : ['title', 'date', 'start_time'];
                $missing = array_values(array_filter(
                    $required,
                    static fn (string $field): bool => ! isset($input[$field]) || $input[$field] === '',
                ));

                if ($missing !== []) {
                    return Http::response([
                        'message' => 'The information provided was invalid.',
                        'errors' => array_fill_keys($missing, ['This field is required.']),
                    ], 422);
                }

                $utc = new DateTimeZone('UTC');

                if ($isAllDay) {
                    $start = new DateTimeImmutable("{$input['date']} 00:00:00", $utc);
                    $end = new DateTimeImmutable(($input['end_date'] ?? $input['date']).' 23:59:59', $utc);
                } else {
                    $start = new DateTimeImmutable("{$input['date']} {$input['start_time']}", $utc);
                    $end = isset($input['end_time'])
                        ? new DateTimeImmutable("{$input['date']} {$input['end_time']}", $utc)
                        : $start->modify('+'.(int) ($input['duration_minutes'] ?? 60).' minutes');

                    // An end before the start reads as an overnight event.
                    if ($end <= $start) {
                        $end = $end->modify('+1 day');
                    }
                }

                $appointment = $store->append('rota/appointments', [
                    'id' => $store->nextId('rota/appointments'),
                    'team_id' => 42,
                    'title' => (string) $input['title'],
                    'location' => $input['location'] ?? null,
                    'notes' => $input['notes'] ?? null,
                    'is_virtual' => (bool) ($input['is_virtual'] ?? false),
                    'is_all_day' => $isAllDay,
                    'is_private' => (bool) ($input['is_private'] ?? false),
                    'created_by' => 7,
                    'start' => $start->format(DATE_ATOM),
                    'end' => $end->format(DATE_ATOM),
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                ]);

                return Http::response(Json::item('appointments', $appointment), 201);
            }),

            FakeRoute::get('rota/appointments/upcoming', static function (RecordStore $store): mixed {
                $records = array_values(array_filter(
                    $store->all('rota/appointments'),
                    static fn (array $r): bool => (bool) ($r['is_upcoming'] ?? false),
                ));

                return Http::response(Json::collection('appointments', $records));
            }),
        ];
    }
}

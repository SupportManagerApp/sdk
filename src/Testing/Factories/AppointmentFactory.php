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
 * Generic CRUD (list, find, delete) comes from the resource path; this factory
 * adds the `upcoming()` feed mirroring the dashboard widget.
 *
 * `create()` is answered by a custom route rather than generic REST, because
 * the platform takes quick-add fields (`date`, `start_time`, …) and stores
 * `start`/`end`: it validates the required fields (422), runs a timed
 * appointment without an end for `duration_minutes` (or an hour), rolls an end
 * at or before the start past midnight, and spans an all-day one from the
 * start of `date` to the end of `end_date`. `update()` is custom for the same
 * reason: only the fields sent change, a move needs `date` with the
 * when-fields (422 otherwise) and is re-spanned exactly as on create, and
 * making an appointment public forgets its viewers.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     AppointmentFactory::new()->count(2),
 *     AppointmentFactory::new()->upcoming(),
 * );
 * SupportManager::appointments()->upcoming();
 * SupportManager::appointments()->create(['title' => 'Dentist', 'date' => '2026-08-18', 'start_time' => '15:30']);
 * SupportManager::appointments()->update(1, ['date' => '2026-08-25', 'start_time' => '10:00']);
 * ```
 */
final class AppointmentFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * The fields that say when an appointment happens; sending any requires `date`.
     */
    private const array WHEN_FIELDS = ['start_time', 'end_time', 'end_date', 'duration_minutes', 'is_all_day'];

    /**
     * The caller's permissions on a fake appointment.
     */
    private const array META = ['can' => ['update' => true, 'delete' => true]];

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
            'viewer_ids' => [],
            'start' => '2026-06-07T10:00:00Z',
            'end' => '2026-06-07T11:00:00Z',
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
            'meta' => self::META,
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

                [$start, $end] = self::span($input, $isAllDay);
                $isPrivate = (bool) ($input['is_private'] ?? false);

                $appointment = $store->append('rota/appointments', [
                    'id' => $store->nextId('rota/appointments'),
                    'team_id' => 42,
                    'title' => (string) $input['title'],
                    'location' => $input['location'] ?? null,
                    'notes' => $input['notes'] ?? null,
                    'is_virtual' => (bool) ($input['is_virtual'] ?? false),
                    'is_all_day' => $isAllDay,
                    'is_private' => $isPrivate,
                    'created_by' => 7,
                    'viewer_ids' => $isPrivate ? array_values((array) ($input['viewer_ids'] ?? [])) : [],
                    'start' => $start->format(DATE_ATOM),
                    'end' => $end->format(DATE_ATOM),
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                    'meta' => self::META,
                ]);

                return Http::response(Json::item('appointments', $appointment), 201);
            }),

            FakeRoute::patch('rota/appointments/{id}', static function (RecordStore $store, array $matches, Request $request): mixed {
                $appointment = $store->find('rota/appointments', $matches[1]);

                if ($appointment === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $input = $request->data();
                $hasDate = isset($input['date']) && $input['date'] !== '';
                $isAllDay = array_key_exists('is_all_day', $input)
                    ? (bool) $input['is_all_day']
                    : (bool) ($appointment['is_all_day'] ?? false);

                $errors = [];

                if (! $hasDate && array_intersect(self::WHEN_FIELDS, array_keys($input)) !== []) {
                    $errors['date'] = ['A date is required when changing when the appointment happens.'];
                }

                if ($hasDate && ! $isAllDay && (! isset($input['start_time']) || $input['start_time'] === '')) {
                    $errors['start_time'] = ['A start time is required unless the appointment is all day.'];
                }

                if ($errors !== []) {
                    return Http::response(['message' => 'The information provided was invalid.', 'errors' => $errors], 422);
                }

                $changes = array_intersect_key($input, array_flip(['title', 'location', 'notes', 'viewer_ids', 'is_virtual', 'is_private']));

                if ($hasDate) {
                    [$start, $end] = self::span($input, $isAllDay);
                    $changes = [...$changes, 'is_all_day' => $isAllDay, 'start' => $start->format(DATE_ATOM), 'end' => $end->format(DATE_ATOM)];
                }

                // Making an appointment public forgets its named viewers.
                if (! (bool) ($changes['is_private'] ?? $appointment['is_private'] ?? false)) {
                    $changes['viewer_ids'] = [];
                }

                return Http::response(Json::item('appointments', $store->update('rota/appointments', $matches[1], $changes) ?? $appointment));
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

    /**
     * When an appointment runs, from create-style fields: an all-day one spans
     * the start of `date` to the end of `end_date`; a timed one without an end
     * runs for `duration_minutes` (or an hour), and an end at or before the
     * start rolls past midnight.
     *
     * @param  array<string, mixed>  $input
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private static function span(array $input, bool $isAllDay): array
    {
        $utc = new DateTimeZone('UTC');
        $date = (string) $input['date'];

        if ($isAllDay) {
            return [
                new DateTimeImmutable("{$date} 00:00:00", $utc),
                new DateTimeImmutable(((string) ($input['end_date'] ?? $date)).' 23:59:59', $utc),
            ];
        }

        $start = new DateTimeImmutable("{$date} {$input['start_time']}", $utc);
        $end = isset($input['end_time'])
            ? new DateTimeImmutable("{$date} {$input['end_time']}", $utc)
            : $start->modify('+'.(int) ($input['duration_minutes'] ?? 60).' minutes');

        // An end before the start reads as an overnight event.
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        return [$start, $end];
    }
}

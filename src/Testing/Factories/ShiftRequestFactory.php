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
 * Fake shift requests — a user's asks for extra hours.
 *
 * The index (`query()`, `all()`) comes from generic REST. `create()` is a
 * custom route because the platform takes form fields (`date`, `start_time`,
 * `end_time`, `type`) and stores `start`/`end`: it validates the required
 * fields and the overtime `type` (422), pins a sleeping night to 23:00–07:00,
 * and rolls an end at or before the start overnight. `cancel()` is custom too,
 * so that, as for a worker on the platform, only a pending request can be
 * withdrawn (403 otherwise).
 *
 * ```php
 * SupportManager::fake()->seed(
 *     ShiftRequestFactory::new()->count(2),
 *     ShiftRequestFactory::new()->approved(),
 * );
 * SupportManager::shiftRequests()->create(['date' => '2026-11-20', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'Overtime Hours']);
 * SupportManager::shiftRequests()->cancel(1);
 * ```
 */
final class ShiftRequestFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * The overtime types a shift request may ask for.
     */
    private const array TYPES = ['Overtime Hours', 'Overtime Sleeping Night'];

    public function path(): string
    {
        return 'rota/shift-requests';
    }

    public function type(): string
    {
        return 'shift_requests';
    }

    public function definition(int $index): array
    {
        return [
            'team_id' => 42,
            'user_id' => 7,
            'start' => '2026-11-20T09:00:00+00:00',
            'end' => '2026-11-20T17:00:00+00:00',
            'type' => 'Overtime Hours',
            'type_label' => 'Overtime Hours',
            'reason' => null,
            'requested_pay_enhancement_id' => null,
            'status' => 'Pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rota_entry_id' => null,
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
            'meta' => ['can' => ['delete' => true]],
        ];
    }

    /**
     * Mark the request as approved, with the shift it created.
     */
    public function approved(): static
    {
        return $this->state(static fn (int $index): array => [
            'status' => 'Approved',
            'reviewed_by' => 2,
            'reviewed_at' => '2026-06-07T09:00:00Z',
            'rota_entry_id' => 100 + $index,
            'meta' => ['can' => ['delete' => false]],
        ]);
    }

    /**
     * Mark the request as rejected.
     */
    public function rejected(): static
    {
        return $this->state([
            'status' => 'Rejected',
            'reviewed_by' => 2,
            'reviewed_at' => '2026-06-07T09:00:00Z',
            'meta' => ['can' => ['delete' => false]],
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('rota/shift-requests', static function (RecordStore $store, array $matches, Request $request): mixed {
                $input = $request->data();
                $errors = [];

                foreach (['date', 'start_time', 'end_time', 'type'] as $field) {
                    if (! isset($input[$field]) || $input[$field] === '') {
                        $errors[$field] = ['This field is required.'];
                    }
                }

                if (isset($input['type']) && ! in_array($input['type'], self::TYPES, true)) {
                    $errors['type'] = ['The selected type is invalid.'];
                }

                if ($errors !== []) {
                    return Http::response(['message' => 'The information provided was invalid.', 'errors' => $errors], 422);
                }

                $type = (string) $input['type'];

                // A sleeping night is always worked 23:00 to 07:00.
                [$startTime, $endTime] = $type === 'Overtime Sleeping Night'
                    ? ['23:00', '07:00']
                    : [(string) $input['start_time'], (string) $input['end_time']];

                $utc = new DateTimeZone('UTC');
                $start = new DateTimeImmutable("{$input['date']} {$startTime}", $utc);
                $end = new DateTimeImmutable("{$input['date']} {$endTime}", $utc);

                if ($end <= $start) {
                    $end = $end->modify('+1 day');
                }

                $shiftRequest = $store->append('rota/shift-requests', [
                    'id' => $store->nextId('rota/shift-requests'),
                    'team_id' => 42,
                    'user_id' => 7,
                    'start' => $start->format(DATE_ATOM),
                    'end' => $end->format(DATE_ATOM),
                    'type' => $type,
                    'type_label' => $type,
                    'reason' => $input['reason'] ?? null,
                    'requested_pay_enhancement_id' => $input['requested_pay_enhancement_id'] ?? null,
                    'status' => 'Pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rota_entry_id' => null,
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                    'meta' => ['can' => ['delete' => true]],
                ]);

                return Http::response(Json::item('shift_requests', $shiftRequest), 201);
            }),

            FakeRoute::delete('rota/shift-requests/{id}', static function (RecordStore $store, array $matches): mixed {
                $shiftRequest = $store->find('rota/shift-requests', $matches[1]);

                if ($shiftRequest === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                if (($shiftRequest['status'] ?? null) !== 'Pending') {
                    return Http::response(['message' => 'This action is unauthorized.'], 403);
                }

                $store->delete('rota/shift-requests', $matches[1]);

                return Http::response(['message' => 'Shift request deleted.', 'meta' => ['team_id' => 42]]);
            }),
        ];
    }
}

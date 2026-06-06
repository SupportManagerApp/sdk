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
 * Fake rota shifts (the platform's `rota-entries`).
 *
 * Generic CRUD (list, find, create, update, delete) comes for free from the
 * resource path; this factory adds the module's read feeds — {@see today()}'s
 * shifts and the unassigned {@see available()} shifts — plus the `apply()`
 * action, which returns the created {@see ShiftApplicationFactory} shape.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     ShiftFactory::new()->count(3),
 *     ShiftFactory::new()->available(),
 * );
 * SupportManager::shifts()->today();
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
}

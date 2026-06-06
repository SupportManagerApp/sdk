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
 * Fake one-off appointments.
 *
 * Generic CRUD (list, find, create, update, delete) comes from the resource
 * path; this factory adds the `upcoming()` feed mirroring the dashboard widget.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     AppointmentFactory::new()->count(2),
 *     AppointmentFactory::new()->upcoming(),
 * );
 * SupportManager::appointments()->upcoming();
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
            'is_virtual' => false,
            'is_all_day' => false,
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

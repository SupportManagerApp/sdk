<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\Appointment;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * One-off calendar appointments, separate from regular shifts.
 *
 * The collection (`query()`, `find()`, `all()`, `create()`) plus the
 * `upcoming()` feed that mirrors the dashboard's upcoming-appointments widget.
 *
 * `create()` is where a confirmed quick-add parse of an appointment lands (see
 * {@see QuickAddResource}); every team member may call it.
 *
 * ```php
 * SupportManager::appointments()->query()->filter('is_all_day', true)->get();
 * SupportManager::appointments()->upcoming();
 * SupportManager::appointments()->create(['title' => 'Dentist', 'date' => '2026-08-18', 'start_time' => '15:30']);
 * ```
 *
 * @extends resource<Appointment>
 */
final class AppointmentsResource extends Resource
{
    /**
     * Create an appointment, returning the stored record. A timed appointment
     * takes `date` and `start_time` (`H:i`); without an `end_time` it runs for
     * `duration_minutes` (or an hour), and an end at or before the start runs
     * past midnight. An all-day one sets `is_all_day` and spans `date` to
     * `end_date`, ignoring times.
     *
     * @param  array<string, mixed>  $data  `title` and `date` are required.
     */
    public function create(array $data): ?Appointment
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Appointments starting within the next N days (the platform defaults to 7).
     *
     * @param  array<string, mixed>  $query  Optional `days`/`limit` overrides.
     * @return list<Appointment>
     */
    public function upcoming(array $query = []): array
    {
        return $this->collection($this->get('rota/appointments/upcoming', $query));
    }

    protected function path(): string
    {
        return 'rota/appointments';
    }

    protected function hydrate(ResourceObject $object): Appointment
    {
        return Appointment::fromArray($object->flat());
    }
}

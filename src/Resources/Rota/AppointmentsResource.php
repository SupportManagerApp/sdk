<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\Appointment;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * One-off calendar appointments, separate from regular shifts.
 *
 * The collection (`query()`, `find()`, `all()`) plus the `upcoming()` feed that
 * mirrors the dashboard's upcoming-appointments widget.
 *
 * ```php
 * SupportManager::appointments()->query()->filter('is_all_day', true)->get();
 * SupportManager::appointments()->upcoming();
 * ```
 *
 * @extends resource<Appointment>
 */
final class AppointmentsResource extends Resource
{
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

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\Appointment;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Findable;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * One-off calendar appointments, separate from regular shifts.
 *
 * The collection (`query()`, `find()`, `all()`, `create()`, `update()`,
 * `destroy()`) plus the `upcoming()` feed that mirrors the dashboard's
 * upcoming-appointments widget.
 *
 * `create()` is where a confirmed quick-add parse of an appointment lands (see
 * {@see QuickAddResource}); every team member may call it. `update()` and
 * `destroy()` are open to anybody for a public appointment, but a private one
 * only to its creator or an Admin/Manager — offer them when the appointment's
 * `canUpdate`/`canDelete` is true.
 *
 * ```php
 * SupportManager::appointments()->query()->filter('is_all_day', true)->get();
 * SupportManager::appointments()->upcoming();
 * SupportManager::appointments()->create(['title' => 'Dentist', 'date' => '2026-08-18', 'start_time' => '15:30']);
 * SupportManager::appointments()->update($id, ['date' => '2026-08-25', 'start_time' => '10:00', 'end_time' => '10:30']);
 * SupportManager::appointments()->destroy($id);
 * ```
 *
 * @extends Resource<Appointment>
 */
final class AppointmentsResource extends Resource
{
    /** @use Findable<Appointment> */
    use Findable;

    /** @use Listable<Appointment> */
    use Listable;

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
     * Change an appointment, returning the stored record. Only the fields sent
     * change. To move it, send `date` with the when-fields exactly as on
     * create: any of `start_time`, `end_time`, `end_date`, `duration_minutes`
     * or `is_all_day` requires `date`, and a timed appointment requires
     * `start_time`. Making an appointment public forgets its named viewers.
     *
     * @param  array<string, mixed>  $data  Any of the create fields, all optional.
     */
    public function update(string|int $id, array $data): ?Appointment
    {
        return $this->item($this->patch("{$this->path()}/{$id}", $data));
    }

    /**
     * Delete an appointment. The platform returns only a confirmation message,
     * so nothing is hydrated.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
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

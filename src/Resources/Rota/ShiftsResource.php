<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\Shift;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Published rota entries — the team's scheduled shifts and leave blocks.
 *
 * The collection (`query()`, `find()`, `all()`, `create()`) plus the module's
 * read feeds — `today()`'s shifts, the unpaginated `upcoming()` window for
 * glanceable clients, and the unassigned `available()` shifts open for
 * application — and the `apply()` action a user uses to apply for one.
 *
 * `create()` is where a confirmed quick-add parse of a shift lands (see
 * {@see QuickAddResource}); the platform restricts it to Admins/Managers.
 *
 * ```php
 * SupportManager::shifts()->query()->filter('mode', 'available')->sort('start')->get();
 * SupportManager::shifts()->today();
 * SupportManager::shifts()->upcoming(['days' => 7, 'mode' => 'team']);
 * SupportManager::shifts()->create(['assignee_id' => 12, 'date' => '2026-08-17', 'start_time' => '09:00', 'end_time' => '17:00']);
 * SupportManager::shifts()->apply($shiftId);
 * ```
 *
 * @extends resource<Shift>
 */
final class ShiftsResource extends Resource
{
    /**
     * Create a shift, returning the stored rota entry. The platform creates it
     * as published overtime (a sleeping night when `is_sleeping_night` is set);
     * an omitted `end_time` defaults to an hour after `start_time`, and one at
     * or before the start rolls overnight.
     *
     * @param  array<string, mixed>  $data  `assignee_id`, `date` and `start_time` (`H:i`) are required.
     */
    public function create(array $data): ?Shift
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Today's published shifts for the team.
     *
     * @return list<Shift>
     */
    public function today(): array
    {
        return $this->collection($this->get('rota/shifts/today'));
    }

    /**
     * The shifts still to come in a short server-decided window, ordered by
     * start — built for watch faces and widgets, so never paginated and never
     * leave. A shift still running counts as upcoming.
     *
     * @param  array<string, mixed>  $query  Optional `days` (default 2), `limit` (default 10) and `mode` (`scheduled` by default, `team` or `available`) overrides.
     * @return list<Shift>
     */
    public function upcoming(array $query = []): array
    {
        return $this->collection($this->get('rota/shifts/upcoming', $query));
    }

    /**
     * Unassigned, published shifts open for application.
     *
     * @return list<Shift>
     */
    public function available(): array
    {
        return $this->collection($this->get('rota/shifts/available'));
    }

    /**
     * Apply for an available shift. Returns the created application(s); the
     * platform supports split applications, so a list is returned.
     *
     * @param  array<string, mixed>  $payload  Optional split/partial application data.
     * @return list<ShiftApplication>
     */
    public function apply(string|int $id, array $payload = []): array
    {
        $response = $this->post("rota/shifts/{$id}/apply", $payload);

        $this->throwForErrorResponse($response, "rota/shifts/{$id}/apply");

        return array_map(
            static fn (ResourceObject $object): ShiftApplication => ShiftApplication::fromArray($object->flat()),
            Document::fromArray($response->json() ?? [])->resources(),
        );
    }

    protected function path(): string
    {
        return 'rota/shifts';
    }

    protected function hydrate(ResourceObject $object): Shift
    {
        return Shift::fromArray($object->flat());
    }
}

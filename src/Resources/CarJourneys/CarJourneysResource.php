<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\CarJourneys;

use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Trip tracking for team vehicles.
 *
 * The full CRUD collection (`query()`, `find()`, `all()`, `create()`,
 * `update()`, `destroy()`) plus the module's non-RESTful actions: the single
 * `ongoing()` journey, `start()`ing a new trip (which the server uses to
 * auto-close any open one), and `finish()`ing a trip.
 *
 * `start()`/`finish()` drive the live "open a trip now, close it later" flow;
 * `create()`/`update()`/`destroy()` are the direct record management for
 * back-filling or correcting logged journeys.
 *
 * Visibility is role-dependent server-side: admins and managers see the full
 * history and may filter by `trip_start_from`, `trip_start_to` and `ongoing`;
 * anyone else sees only journeys completed in the last 30 days (plus the
 * ongoing one by id), may filter only by `user_id` and `driver_name`, and gets
 * a 400 for the privileged filters. Shift workers get a 403 outside their shift
 * window (an hour either side of a published shift).
 *
 * ```php
 * SupportManager::journeys()->query()->filter('ongoing', true)->sort('-trip_start')->get();
 * SupportManager::journeys()->ongoing();
 * SupportManager::journeys()->create(['driver_name' => 'Ada', 'trip_start' => '2026-06-06T09:00:00Z']);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<CarJourney>
 */
final class CarJourneysResource extends Resource
{
    /**
     * Create a journey record directly, returning the stored trip. Logging a
     * journey ends any journey still ongoing for the team (its `trip_end` is
     * set to now), whoever started it.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ?CarJourney
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Update a journey record, returning the updated trip.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string|int $id, array $data): ?CarJourney
    {
        return $this->item($this->patch("{$this->path()}/{$id}", $data));
    }

    /**
     * Permanently delete a journey record.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"), $this->path());
    }

    /**
     * The one ongoing journey, or null when none is open (the platform 404s).
     */
    public function ongoing(): ?CarJourney
    {
        $response = $this->get('car-journeys/ongoing');

        if ($response->status() === 404) {
            return null;
        }

        return $this->item($response);
    }

    /**
     * Start a new journey. The server auto-closes any previously open trip.
     */
    public function start(string $driverName, ?string $comments = null): ?CarJourney
    {
        return $this->item($this->post('car-journeys/start', array_filter([
            'driver_name' => $driverName,
            'comments' => $comments,
        ], static fn (mixed $value): bool => $value !== null)));
    }

    /**
     * Finish an open journey by id, returning the completed trip.
     */
    public function finish(string|int $id): ?CarJourney
    {
        return $this->item($this->patch("car-journeys/{$id}/finish"));
    }

    protected function path(): string
    {
        return 'car-journeys';
    }

    protected function hydrate(ResourceObject $object): CarJourney
    {
        return CarJourney::fromArray($object->flat());
    }
}

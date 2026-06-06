<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\CarJourneys;

use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Trip tracking for team vehicles.
 *
 * The collection (`query()`, `find()`, `all()`) plus the module's non-RESTful
 * actions: the single `ongoing()` journey, `start()`ing a new trip (which the
 * server uses to auto-close any open one), and `finish()`ing a trip.
 *
 * ```php
 * SupportManager::journeys()->query()->filter('ongoing', true)->sort('-trip_start')->get();
 * SupportManager::journeys()->ongoing();
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<CarJourney>
 */
final class CarJourneysResource extends Resource
{
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

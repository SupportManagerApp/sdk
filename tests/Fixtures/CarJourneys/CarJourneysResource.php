<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Fixtures\CarJourneys;

use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Throwaway resource proving the seams (fluent query, pagination, item helpers,
 * facade registration) against the platform's real `car-journeys` endpoint
 * shape. Lives in the test suite, not in shipped `src/`.
 *
 * @extends \SupportManager\Sdk\Resources\Resource<CarJourney>
 */
final class CarJourneysResource extends Resource
{
    /**
     * The one ongoing journey, via the non-RESTful `car-journeys/ongoing`
     * action — demonstrating the `get()` + `item()` helpers and a 404 → null.
     */
    public function ongoing(): ?CarJourney
    {
        $response = $this->get('car-journeys/ongoing');

        // The platform returns 404 when there is no ongoing journey.
        if ($response->status() === 404) {
            return null;
        }

        return $this->item($response);
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

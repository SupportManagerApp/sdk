<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\ShiftAmendmentRequest;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's requests to change one of their shifts.
 *
 * Index-only collection (`query()`, `all()`), newest first, filterable by
 * `status` and `rota_entry_id`. A request is made against its shift with
 * {@see ShiftsResource::requestAmendment()}, and managers review it on the web.
 * The platform exposes no show route, so `find()` is not used here.
 *
 * ```php
 * SupportManager::shiftAmendmentRequests()->query()->filter('status', 'Pending')->get();
 * SupportManager::shiftAmendmentRequests()->query()->filter('rota_entry_id', $shiftId)->get();
 * ```
 *
 * @extends Resource<ShiftAmendmentRequest>
 */
final class ShiftAmendmentRequestsResource extends Resource
{
    protected function path(): string
    {
        return 'rota/shift-amendment-requests';
    }

    protected function hydrate(ResourceObject $object): ShiftAmendmentRequest
    {
        return ShiftAmendmentRequest::fromArray($object->flat());
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\ShiftRequest;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's requests for extra hours.
 *
 * Index-only collection (`query()`, `all()`), newest first, filterable by
 * `status` and sortable by `created_at` or `start`, plus `create()` to ask for
 * an overtime shift and `destroy()` to withdraw one. Managers approve (creating
 * the shift) or reject requests on the web. The platform exposes no show
 * route, so `find()` is not used here.
 *
 * ```php
 * SupportManager::shiftRequests()->create(['date' => '2026-11-20', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'Overtime Hours']);
 * SupportManager::shiftRequests()->query()->filter('status', 'Pending')->sort('start')->get();
 * SupportManager::shiftRequests()->destroy($requestId);
 * ```
 *
 * @extends Resource<ShiftRequest>
 */
final class ShiftRequestsResource extends Resource
{
    /**
     * Ask the managers for an overtime shift, returning the pending request.
     * A sleeping night is always worked 23:00 to 07:00, so for
     * `Overtime Sleeping Night` the platform replaces the times sent; otherwise
     * an end at or before the start runs into the next morning.
     *
     * @param  array<string, mixed>  $data  `date`, `start_time`, `end_time` (`H:i`) and `type` (`Overtime Hours` or `Overtime Sleeping Night`) are required; `reason` and `requested_pay_enhancement_id` are optional.
     */
    public function create(array $data): ?ShiftRequest
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Withdraw a shift request. The caller may withdraw their own while it is
     * pending (Admins and Managers may delete any) — offer it when the
     * request's `canDelete` is true; anything else is a 403. The platform
     * returns only a confirmation message, so nothing is hydrated.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
    }

    /**
     * @deprecated Use {@see self::destroy()}, the name every resource gives a DELETE of one record.
     */
    public function cancel(string|int $id): void
    {
        $this->deprecated(__FUNCTION__, 'destroy');

        $this->destroy($id);
    }

    protected function path(): string
    {
        return 'rota/shift-requests';
    }

    protected function hydrate(ResourceObject $object): ShiftRequest
    {
        return ShiftRequest::fromArray($object->flat());
    }
}

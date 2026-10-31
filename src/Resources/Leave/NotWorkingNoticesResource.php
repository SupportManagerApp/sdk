<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use SupportManager\Sdk\Data\Leave\NotWorkingNotice;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * Notices self-employed contractors give that they will not be working.
 *
 * The collection (`query()`, `all()`) is filtered by `show`: `upcoming` (the
 * default) lists standing notices that have not ended, soonest first; `past`
 * those that have ended and `withdrawn` the withdrawn ones, latest first.
 * `create()` records a notice and `withdraw()` takes one back. Contractors see
 * and record their own notices; privileged users see everyone's and must name
 * the `contractor_id`. Everyone else gets a 403. The platform has no
 * per-notice show endpoint.
 *
 * ```php
 * SupportManager::leaveNotWorkingNotices()->query()->filter('show', 'past')->get();
 * SupportManager::leaveNotWorkingNotices()->withdraw(12);
 * ```
 *
 * @extends Resource<NotWorkingNotice>
 */
final class NotWorkingNoticesResource extends Resource
{
    /** @use Listable<NotWorkingNotice> */
    use Listable;

    /**
     * Record a not-working notice, returning the stored record. It stands
     * straight away and the contractor's shifts in that time are released for
     * cover.
     *
     * Send `duration_type` and `start_date` (today or later, YYYY-MM-DD), plus
     * `end_date` for `Whole Days` or `start_time`/`end_time` (HH:MM) for
     * `Custom Timings`, and an optional `note`. Privileged users must send the
     * contractor's `contractor_id`; contractors must not. A notice overlapping
     * another standing one is a 422 on `start_date`.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ?NotWorkingNotice
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Withdraw a standing notice, returning the updated record. Released shifts
     * nobody has picked up go back to the contractor; the record's
     * `shiftsNeedingCoverCount` says how many still await cover.
     * {@see NotWorkingNotice::$canWithdraw} says whether this is allowed.
     */
    public function withdraw(string|int $id): ?NotWorkingNotice
    {
        return $this->item($this->patch("{$this->path()}/{$id}/withdraw"));
    }

    protected function path(): string
    {
        return 'leave/not-working';
    }

    protected function hydrate(ResourceObject $object): NotWorkingNotice
    {
        return NotWorkingNotice::fromArray($object->flat());
    }
}

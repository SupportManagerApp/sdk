<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\Shift;
use SupportManager\Sdk\Data\Rota\ShiftAmendmentRequest;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Enums\Rota\RecurringShiftScope;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Findable;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * Published rota entries — the team's scheduled shifts and leave blocks.
 *
 * The collection (`query()`, `find()`, `all()`, `create()`) plus the module's
 * read feeds — `today()`'s shifts, the unpaginated `upcoming()` window for
 * glanceable clients, and the unassigned `available()` shifts open for
 * application — the `apply()` action a user uses to apply for one, and
 * `requestAmendment()`, which asks the managers to change one of the caller's
 * own shifts (listed afterwards through {@see ShiftAmendmentRequestsResource}).
 *
 * `create()` is where a confirmed quick-add parse of a shift lands (see
 * {@see QuickAddResource}); the platform restricts it to Admins/Managers.
 *
 * Managers also `update()`, `destroy()`, `duplicate()` and `assign()` shifts.
 * Offer each when the shift's matching `canUpdate`, `canDelete`,
 * `canDuplicate` or `canAssign` flag is true; anything else is a 403. A
 * repeating shift's edit or delete takes a {@see RecurringShiftScope}.
 *
 * ```php
 * SupportManager::shifts()->query()->filter('mode', 'available')->sort('start')->get();
 * SupportManager::shifts()->today();
 * SupportManager::shifts()->upcoming(['days' => 7, 'mode' => 'team']);
 * SupportManager::shifts()->create(['assignee_id' => 12, 'date' => '2026-08-17', 'start_time' => '09:00', 'end_time' => '17:00']);
 * SupportManager::shifts()->apply($shiftId);
 * SupportManager::shifts()->requestAmendment($shiftId, ['proposed_start_time' => '15:00', 'proposed_end_time' => '23:00']);
 * SupportManager::shifts()->update($shiftId, ['start_time' => '22:00', 'end_time' => '06:00', 'pay_enhancement_id' => 4]);
 * SupportManager::shifts()->update($shiftId, ['type' => 'Overtime Hours'], RecurringShiftScope::Future);
 * SupportManager::shifts()->destroy($shiftId, RecurringShiftScope::Future);
 * SupportManager::shifts()->duplicate($shiftId, '2026-11-03');
 * SupportManager::shifts()->assign($shiftId, $userId);
 * ```
 *
 * @extends Resource<Shift>
 */
final class ShiftsResource extends Resource
{
    /** @use Findable<Shift> */
    use Findable;

    /** @use Listable<Shift> */
    use Listable;

    /**
     * Create a shift, returning the stored rota entry. The platform creates it
     * as published overtime (a sleeping night when `is_sleeping_night` is set);
     * an omitted `end_time` defaults to an hour after `start_time`, and one at
     * or before the start rolls overnight.
     *
     * @param  array<string, mixed>  $data  `assignee_id`, `date` and `start_time` (`H:i`) are required; `end_time`, `notes`, `is_sleeping_night`, `is_private`, `tags`, `pay_enhancement_id` (one of the team's pay enhancements, else a 422) and `is_pay_enhancement_private` are optional.
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
     * platform supports split applications, so a list is returned. A segment
     * of a sleepover (a shift with a `shiftBlockId`) is rejected with a 422:
     * apply for the sleepover as a whole through {@see ShiftBlocksResource::apply()}.
     *
     * @param  array<string, mixed>  $payload  Optional split/partial application data.
     * @return list<ShiftApplication>
     */
    public function apply(string|int $id, array $payload = []): array
    {
        return $this->collection($this->post("{$this->path()}/{$id}/apply", $payload), ShiftApplication::class);
    }

    /**
     * Ask the managers to change the times, pay enhancement or tags of one of
     * the caller's own shifts, returning the pending request. The proposed
     * times are placed on the day the shift starts; an end at or before the
     * start runs into the next morning. Offer it when the shift's
     * `canRequestAmendment` is true — anything else is a 403.
     *
     * @param  array<string, mixed>  $data  `proposed_start_time` and `proposed_end_time` (`H:i`) are required; `requested_pay_enhancement_id`, `reason` and `requested_tags` are optional.
     */
    public function requestAmendment(string|int $id, array $data): ?ShiftAmendmentRequest
    {
        return $this->item($this->post("{$this->path()}/{$id}/amendment-requests", $data), ShiftAmendmentRequest::class);
    }

    /**
     * Edit a shift, returning it as saved. Only the fields sent change. The
     * times are wall-clock on the shift's `date`, and an end at or before the
     * start runs into the next day. Putting somebody on the shift (`user_id`)
     * closes it to applications. A repeating shift's edit reaches `$scope`:
     * this shift only (detaching it from its series) by default, or this and
     * every later one.
     *
     * A 403 unless the shift's `canUpdate` is true. A 422 for an unknown
     * person or pay enhancement, for `is_for_toil` when the person has no
     * leave entitlement for the shift's leave year, and for a shift on a
     * completed payslip until `reset_payslip` is true, which puts that payslip
     * back to draft: regenerate it and resubmit the FPS afterwards.
     *
     * @param  array<string, mixed>  $data  Any of `date`, `start_time`, `end_time` (`H:i`), `type`, `user_id`, `pay_enhancement_id`, `is_pay_enhancement_private`, `is_for_toil`, `is_available`, `is_published`, `is_private`, `tags` (replaces them) and `reset_payslip`.
     */
    public function update(string|int $id, array $data, RecurringShiftScope $scope = RecurringShiftScope::Single): ?Shift
    {
        return $this->item($this->patch("{$this->path()}/{$id}", [...$data, 'scope' => $scope->value]));
    }

    /**
     * Delete a shift. A repeating shift's delete reaches `$scope`: this shift
     * only by default, or this and every later one in its series. Deleting a
     * shift that has been paid makes a payroll correction. A 403 unless the
     * shift's `canDelete` is true.
     */
    public function destroy(string|int $id, RecurringShiftScope $scope = RecurringShiftScope::Single): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}?scope={$scope->value}"));
    }

    /**
     * Copy a shift onto `$date` (`Y-m-d`, or the shift's own day when null) as
     * a new, published shift with the same times, person, type, pay
     * enhancement, availability, privacy and tags, returning the copy. Copying
     * any segment of a sleepover copies the whole sleepover, keeping the
     * person on the segment copied, and returns the copy's sleeping-night
     * segment. A 403 unless the shift's `canDuplicate` is true; a 422 for a
     * bad date.
     */
    public function duplicate(string|int $id, ?string $date = null): ?Shift
    {
        return $this->item($this->post("{$this->path()}/{$id}/duplicate", $date === null ? [] : ['date' => $date]));
    }

    /**
     * Put an active shift worker in the team on a shift, returning the shift.
     * Their pending application is approved and everyone else's turned down;
     * a sleepover segment assigns the whole sleepover. A 403 unless the
     * shift's `canAssign` is true. A 422 on `user_id` when the person isn't an
     * active shift worker in the team, or can't take the shift: on leave it
     * covers, not working then, past young-worker limits or short of their
     * Working Time rest.
     */
    public function assign(string|int $id, int $userId): ?Shift
    {
        return $this->item($this->post("{$this->path()}/{$id}/assignment", ['user_id' => $userId]));
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

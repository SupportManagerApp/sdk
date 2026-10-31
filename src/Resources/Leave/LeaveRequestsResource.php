<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use SupportManager\Sdk\Data\Leave\LeaveRequest;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Leave (holiday) requests: the full CRUD collection plus the approval
 * workflow actions.
 *
 * `query()`, `find()`, `all()` read the collection; `create()`/`update()`/
 * `destroy()` manage requests; and the lifecycle transitions
 * `approve()`/`reject()`/`cancel()` move a request through its workflow.
 * Privileged users see every request in the team, everyone else only their
 * own; contractors' not-working notices are never included (see
 * {@see NotWorkingNoticesResource}). The index `meta` carries `status_counts`,
 * the number of visible requests per status, ignoring filters and paging.
 *
 * ```php
 * SupportManager::leaveRequests()->query()->filter('status', 'Pending')->get();
 * SupportManager::leaveRequests()->query()->get()->meta()['status_counts'];
 * SupportManager::leaveRequests()->approve(7);
 * ```
 *
 * @extends Resource<LeaveRequest>
 */
final class LeaveRequestsResource extends Resource
{
    /**
     * Create a new leave request, returning the stored record.
     *
     * Send `type`, `start_date`, `end_date` and optional `requester_comments`.
     * For `Whole Days` the dates are the first and last days off (YYYY-MM-DD).
     * The server works out `hours` and `sleeps` from the requester's working
     * pattern and answers a 422 if they are sent.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ?LeaveRequest
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Update an existing leave request, returning the updated record.
     *
     * The date fields (`type`, `start_date`, `end_date`) are required while
     * {@see LeaveRequest::$canEditDates} holds and refused once it does not;
     * leave them out to change only the comments. `hours`/`sleeps` are sent only
     * by a privileged user editing someone else's request, and
     * `approver_comments` only by a privileged user. The platform accepts PUT
     * and PATCH alike here; this sends PATCH.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string|int $id, array $data): ?LeaveRequest
    {
        return $this->item($this->patch("{$this->path()}/{$id}", $data));
    }

    /**
     * Permanently delete a leave request.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
    }

    /**
     * Approve a pending request, returning the updated record.
     */
    public function approve(string|int $id): ?LeaveRequest
    {
        return $this->item($this->patch("{$this->path()}/{$id}/approve"));
    }

    /**
     * Reject a pending request with optional approver comments, returning the
     * updated record.
     */
    public function reject(string|int $id, ?string $reason = null): ?LeaveRequest
    {
        return $this->item($this->patch("{$this->path()}/{$id}/reject", array_filter([
            'approver_comments' => $reason,
        ], static fn (mixed $value): bool => $value !== null)));
    }

    /**
     * Cancel a request with an optional reason, returning the updated record.
     * The reason is recorded as approver comments only when a privileged user
     * gives it; the owner's reason is ignored.
     */
    public function cancel(string|int $id, ?string $reason = null): ?LeaveRequest
    {
        return $this->item($this->patch("{$this->path()}/{$id}/cancel", array_filter([
            'reason' => $reason,
        ], static fn (mixed $value): bool => $value !== null)));
    }

    protected function path(): string
    {
        return 'leave/requests';
    }

    protected function hydrate(ResourceObject $object): LeaveRequest
    {
        return LeaveRequest::fromArray($object->flat());
    }
}

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
 * `delete()` manage requests; and the lifecycle transitions
 * `approve()`/`reject()`/`cancel()` move a request through its workflow.
 *
 * ```php
 * SupportManager::leaveRequests()->query()->filter('status', 'Pending')->get();
 * SupportManager::leaveRequests()->approve(7);
 * ```
 *
 * @extends resource<LeaveRequest>
 */
final class LeaveRequestsResource extends Resource
{
    /**
     * Create a new leave request, returning the stored record.
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
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"), $this->path());
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

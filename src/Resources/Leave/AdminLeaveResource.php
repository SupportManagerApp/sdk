<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Data\Leave\LeaveRequest;
use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Privileged administrative surface for the leave module (`leave/admin/*`).
 *
 * One resource grouping the admin-only endpoints: the pending-approval queue
 * and bulk approve/reject, team balance overviews, the adjustments and
 * entitlements registers (entitlements being writable here), and the dashboard
 * summary. Where a DTO already models the shape it is reused; the bespoke
 * balance/summary payloads are returned as raw arrays.
 *
 * ```php
 * SupportManager::leaveAdmin()->pendingRequests();
 * SupportManager::leaveAdmin()->bulkApprove([1, 2, 3]);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<LeaveRequest>
 */
final class AdminLeaveResource extends Resource
{
    /**
     * The pending-approval queue. Items are flat (not JSON:API), so they are
     * hydrated straight from the response `data` block.
     *
     * @return list<LeaveRequest>
     */
    public function pendingRequests(): array
    {
        return $this->dataList($this->get("{$this->path()}/requests/pending"), LeaveRequest::class);
    }

    /**
     * Approve many pending requests at once.
     *
     * @param  list<int|string>  $ids
     */
    public function bulkApprove(array $ids): void
    {
        $this->throwForErrorResponse($this->post("{$this->path()}/requests/bulk-approve", ['request_ids' => $ids]));
    }

    /**
     * Reject many pending requests at once, with an optional shared reason.
     *
     * @param  list<int|string>  $ids
     */
    public function bulkReject(array $ids, ?string $reason = null): void
    {
        $this->throwForErrorResponse($this->post("{$this->path()}/requests/bulk-reject", array_filter([
            'request_ids' => $ids,
            'reason' => $reason,
        ], static fn (mixed $value): bool => $value !== null)));
    }

    /**
     * Team balance overview for every member, as raw arrays (the platform's
     * bespoke `TeamBalanceResource` shape, not a JSON:API resource).
     *
     * @return list<array<string, mixed>>
     */
    public function balances(): array
    {
        /** @var list<array<string, mixed>> */
        return array_values(array_filter(
            $this->raw($this->get("{$this->path()}/balances")),
            static fn (mixed $item): bool => is_array($item),
        ));
    }

    /**
     * Detailed balance breakdown for a single user, as a raw array, or null
     * when the body carries none.
     *
     * @return array<string, mixed>|null
     */
    public function balanceFor(int|string $userId): ?array
    {
        /** @var array<string, mixed> $data */
        $data = $this->raw($this->get("{$this->path()}/balances/{$userId}"));

        return $data === [] ? null : $data;
    }

    /**
     * The adjustments register (JSON:API).
     *
     * @return list<ManualAdjustment>
     */
    public function adjustments(): array
    {
        return $this->collection($this->get("{$this->path()}/adjustments"), ManualAdjustment::class);
    }

    /**
     * The entitlements register (JSON:API).
     *
     * @return list<LeaveEntitlement>
     */
    public function entitlements(): array
    {
        return $this->collection($this->get("{$this->path()}/entitlements"), LeaveEntitlement::class);
    }

    /**
     * Create an entitlement, returning the stored record.
     *
     * @param  array<string, mixed>  $data
     */
    public function createEntitlement(array $data): ?LeaveEntitlement
    {
        return $this->item($this->post("{$this->path()}/entitlements", $data), LeaveEntitlement::class);
    }

    /**
     * Update an entitlement, returning the updated record.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateEntitlement(string|int $id, array $data): ?LeaveEntitlement
    {
        return $this->item($this->put("{$this->path()}/entitlements/{$id}", $data), LeaveEntitlement::class);
    }

    /**
     * Permanently delete an entitlement.
     */
    public function deleteEntitlement(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/entitlements/{$id}"));
    }

    /**
     * The admin dashboard summary, as a raw array.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        /** @var array<string, mixed> */
        return $this->raw($this->get("{$this->path()}/summary"));
    }

    protected function path(): string
    {
        return 'leave/admin';
    }

    protected function hydrate(ResourceObject $object): LeaveRequest
    {
        return LeaveRequest::fromArray($object->flat());
    }
}

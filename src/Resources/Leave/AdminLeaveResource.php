<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Data\Leave\LeaveRequest;
use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Http\Document;
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
 * @extends resource<LeaveRequest>
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
        $response = $this->get('leave/admin/requests/pending');
        $this->throwForErrorResponse($response, 'leave/admin/requests/pending');

        return array_map(
            static fn (array $item): LeaveRequest => LeaveRequest::fromArray($item),
            $this->dataItems($response),
        );
    }

    /**
     * Approve many pending requests at once.
     *
     * @param  list<int|string>  $ids
     */
    public function bulkApprove(array $ids): void
    {
        $response = $this->post('leave/admin/requests/bulk-approve', ['request_ids' => $ids]);
        $this->throwForErrorResponse($response, 'leave/admin/requests/bulk-approve');
    }

    /**
     * Reject many pending requests at once, with an optional shared reason.
     *
     * @param  list<int|string>  $ids
     */
    public function bulkReject(array $ids, ?string $reason = null): void
    {
        $response = $this->post('leave/admin/requests/bulk-reject', array_filter([
            'request_ids' => $ids,
            'reason' => $reason,
        ], static fn (mixed $value): bool => $value !== null));

        $this->throwForErrorResponse($response, 'leave/admin/requests/bulk-reject');
    }

    /**
     * Team balance overview for every member, as raw arrays (the platform's
     * bespoke `TeamBalanceResource` shape, not a JSON:API resource).
     *
     * @return list<array<string, mixed>>
     */
    public function balances(): array
    {
        $response = $this->get('leave/admin/balances');
        $this->throwForErrorResponse($response, 'leave/admin/balances');

        return $this->dataItems($response);
    }

    /**
     * Detailed balance breakdown for a single user, as a raw array.
     *
     * @return array<string, mixed>|null
     */
    public function balanceFor(int|string $userId): ?array
    {
        $response = $this->get("leave/admin/balances/{$userId}");
        $this->throwForErrorResponse($response, 'leave/admin/balances');

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    /**
     * The adjustments register (JSON:API).
     *
     * @return list<ManualAdjustment>
     */
    public function adjustments(): array
    {
        return array_map(
            static fn (ResourceObject $object): ManualAdjustment => ManualAdjustment::fromArray($object->flat()),
            $this->resourceObjects($this->get('leave/admin/adjustments'), 'leave/admin/adjustments'),
        );
    }

    /**
     * The entitlements register (JSON:API).
     *
     * @return list<LeaveEntitlement>
     */
    public function entitlements(): array
    {
        return array_map(
            static fn (ResourceObject $object): LeaveEntitlement => LeaveEntitlement::fromArray($object->flat()),
            $this->resourceObjects($this->get('leave/admin/entitlements'), 'leave/admin/entitlements'),
        );
    }

    /**
     * Create an entitlement, returning the stored record.
     *
     * @param  array<string, mixed>  $data
     */
    public function createEntitlement(array $data): ?LeaveEntitlement
    {
        $response = $this->post('leave/admin/entitlements', $data);
        $this->throwForErrorResponse($response, 'leave/admin/entitlements');

        return $this->entitlementFromResponse($response);
    }

    /**
     * Update an entitlement, returning the updated record.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateEntitlement(string|int $id, array $data): ?LeaveEntitlement
    {
        $response = $this->put("leave/admin/entitlements/{$id}", $data);
        $this->throwForErrorResponse($response, 'leave/admin/entitlements');

        return $this->entitlementFromResponse($response);
    }

    /**
     * Permanently delete an entitlement.
     */
    public function deleteEntitlement(string|int $id): void
    {
        $response = $this->delete("leave/admin/entitlements/{$id}");
        $this->throwForErrorResponse($response, 'leave/admin/entitlements');
    }

    /**
     * The admin dashboard summary, as a raw array.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $response = $this->get('leave/admin/summary');
        $this->throwForErrorResponse($response, 'leave/admin/summary');

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    protected function path(): string
    {
        return 'leave/admin';
    }

    protected function hydrate(ResourceObject $object): LeaveRequest
    {
        return LeaveRequest::fromArray($object->flat());
    }

    /**
     * The raw `data` array items from a flat (non-JSON:API) collection response.
     *
     * @return list<array<string, mixed>>
     */
    private function dataItems(Response $response): array
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter(
            $data,
            static fn (mixed $item): bool => is_array($item),
        ));
    }

    /**
     * The JSON:API resource objects from a collection response.
     *
     * @return list<ResourceObject>
     */
    private function resourceObjects(Response $response, string $context): array
    {
        $this->throwForErrorResponse($response, $context);

        return Document::fromArray($response->json() ?? [])->resources();
    }

    private function entitlementFromResponse(Response $response): ?LeaveEntitlement
    {
        $resource = Document::fromArray($response->json() ?? [])->resource();

        return $resource === null ? null : LeaveEntitlement::fromArray($resource->flat());
    }
}

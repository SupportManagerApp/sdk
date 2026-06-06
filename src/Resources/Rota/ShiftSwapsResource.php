<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\ShiftSwapOffer;
use SupportManager\Sdk\Data\Rota\ShiftSwapRequest;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Shift swap requests and their multi-step approval workflow.
 *
 * The collection (`query()`, `find()`, `all()`) plus `create()` and the
 * workflow transitions — `cancel()`, `accept()`, `decline()`, `approve()`,
 * `reject()` — each of which returns the updated swap request. `offer()` makes
 * a new offer against an open swap and returns the created {@see ShiftSwapOffer}.
 *
 * ```php
 * SupportManager::shiftSwaps()->query()->filter('scope', 'incoming')->get();
 * SupportManager::shiftSwaps()->accept($swapId, ['respondent_shift_ids' => [12]]);
 * SupportManager::shiftSwaps()->offer($swapId, ['offered_shift_ids' => [34]]);
 * ```
 *
 * @extends resource<ShiftSwapRequest>
 */
final class ShiftSwapsResource extends Resource
{
    /**
     * Create a new shift swap request, returning the created request.
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): ?ShiftSwapRequest
    {
        return $this->item($this->post('rota/shift-swaps', $payload));
    }

    /**
     * Cancel a swap request, returning the updated request.
     */
    public function cancel(string|int $id): ?ShiftSwapRequest
    {
        return $this->item($this->post("rota/shift-swaps/{$id}/cancel"));
    }

    /**
     * Accept a directed swap request, returning the updated request.
     *
     * @param  array<string, mixed>  $payload  Optional `respondent_shift_ids`.
     */
    public function accept(string|int $id, array $payload = []): ?ShiftSwapRequest
    {
        return $this->item($this->post("rota/shift-swaps/{$id}/accept", $payload));
    }

    /**
     * Decline a directed swap request, returning the updated request.
     */
    public function decline(string|int $id): ?ShiftSwapRequest
    {
        return $this->item($this->post("rota/shift-swaps/{$id}/decline"));
    }

    /**
     * Approve a swap request pending manager approval, returning the updated
     * request.
     */
    public function approve(string|int $id): ?ShiftSwapRequest
    {
        return $this->item($this->post("rota/shift-swaps/{$id}/approve"));
    }

    /**
     * Reject a swap request pending manager approval, returning the updated
     * request.
     *
     * @param  array<string, mixed>  $payload  Optional `reason`.
     */
    public function reject(string|int $id, array $payload = []): ?ShiftSwapRequest
    {
        return $this->item($this->post("rota/shift-swaps/{$id}/reject", $payload));
    }

    /**
     * Make an offer against an open swap request, returning the created offer.
     *
     * @param  array<string, mixed>  $payload  Requires `offered_shift_ids`.
     */
    public function offer(string|int $id, array $payload): ?ShiftSwapOffer
    {
        $response = $this->post("rota/shift-swaps/{$id}/offers", $payload);

        $this->throwForErrorResponse($response, "rota/shift-swaps/{$id}/offers");

        $resource = Document::fromArray($response->json() ?? [])->resource();

        return $resource === null ? null : ShiftSwapOffer::fromArray($resource->flat());
    }

    protected function path(): string
    {
        return 'rota/shift-swaps';
    }

    protected function hydrate(ResourceObject $object): ShiftSwapRequest
    {
        return ShiftSwapRequest::fromArray($object->flat());
    }
}

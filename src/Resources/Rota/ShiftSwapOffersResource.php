<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\ShiftSwapOffer;
use SupportManager\Sdk\Data\Rota\ShiftSwapRequest;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Offers made against open shift swap requests.
 *
 * Action-only — the platform exposes no index/show for offers, so this wraps the
 * three offer transitions. `accept()` resolves the parent swap and so returns
 * the updated {@see ShiftSwapRequest}; `decline()` and `withdraw()` return the
 * updated {@see ShiftSwapOffer}.
 *
 * ```php
 * SupportManager::shiftSwapOffers()->accept($offerId);
 * SupportManager::shiftSwapOffers()->withdraw($offerId);
 * ```
 *
 * @extends resource<ShiftSwapOffer>
 */
final class ShiftSwapOffersResource extends Resource
{
    /**
     * Accept an offer on your open swap request. The platform finalises the
     * parent swap, so the updated swap request is returned.
     */
    public function accept(string|int $offerId): ?ShiftSwapRequest
    {
        $response = $this->post("rota/shift-swap-offers/{$offerId}/accept");

        $this->throwForErrorResponse($response, "rota/shift-swap-offers/{$offerId}/accept");

        $resource = Document::fromArray($response->json() ?? [])->resource();

        return $resource === null ? null : ShiftSwapRequest::fromArray($resource->flat());
    }

    /**
     * Decline an offer on your open swap request, returning the updated offer.
     */
    public function decline(string|int $offerId): ?ShiftSwapOffer
    {
        return $this->item($this->post("rota/shift-swap-offers/{$offerId}/decline"));
    }

    /**
     * Withdraw your own offer, returning the updated offer.
     */
    public function withdraw(string|int $offerId): ?ShiftSwapOffer
    {
        return $this->item($this->post("rota/shift-swap-offers/{$offerId}/withdraw"));
    }

    protected function path(): string
    {
        return 'rota/shift-swap-offers';
    }

    protected function hydrate(ResourceObject $object): ShiftSwapOffer
    {
        return ShiftSwapOffer::fromArray($object->flat());
    }
}

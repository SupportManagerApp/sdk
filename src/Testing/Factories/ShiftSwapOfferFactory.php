<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake shift swap offers.
 *
 * The platform exposes no index/show for offers — only three action-only
 * transitions: `accept` finalises the parent swap and returns the updated
 * {@see ShiftSwapRequest}, while `decline` and `withdraw` return the updated
 * offer. All three are custom routes here.
 *
 * The optional `swap_id` attribute links an offer to a seeded
 * {@see ShiftSwapFactory} request so `accept` can return the finalised swap.
 *
 * ```php
 * SupportManager::fake()->seed(ShiftSwapOfferFactory::new()->forSwap(1));
 * SupportManager::shiftSwapOffers()->decline(1);
 * SupportManager::shiftSwapOffers()->accept(1);
 * ```
 */
final class ShiftSwapOfferFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/shift-swap-offers';
    }

    public function type(): string
    {
        return 'shift-swap-offers';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'status' => 'Pending Response',
            'notes' => null,
            'user' => ['id' => 3, 'name' => "Offerer {$number}"],
            'entries' => [
                ['partial_start' => null, 'partial_end' => null, 'rota_entry' => [
                    'id' => (string) $number,
                    'attributes' => ['type' => 'Contracted Hours', 'duration' => 8.0],
                ]],
            ],
            'swap_id' => null,
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
        ];
    }

    /**
     * Link this offer to a seeded swap request, so `accept()` can finalise it.
     */
    public function forSwap(int|string $swapId): static
    {
        return $this->state(['swap_id' => (string) $swapId]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('rota/shift-swap-offers/{id}/accept', static function (RecordStore $store, array $matches): mixed {
                $offer = $store->update('rota/shift-swap-offers', $matches[1], [
                    'status' => 'Accepted',
                ]);

                if ($offer === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $swapId = isset($offer['swap_id']) ? (string) $offer['swap_id'] : null;
                $swap = $swapId === null ? null : $store->update('rota/shift-swaps', $swapId, [
                    'status' => 'Approved',
                    'status_label' => 'Approved',
                ]);

                // The parent swap is finalised and returned. With no linked swap
                // seeded, synthesise a minimal approved request.
                $swap ??= [
                    'id' => $swapId ?? '1',
                    'status' => 'Approved',
                    'status_label' => 'Approved',
                    'requester' => ['id' => 1, 'name' => 'Ada'],
                ];

                return Http::response(Json::item('shift-swap-requests', $swap));
            }),

            FakeRoute::post('rota/shift-swap-offers/{id}/decline', static function (RecordStore $store, array $matches): mixed {
                $offer = $store->update('rota/shift-swap-offers', $matches[1], ['status' => 'Declined']);

                return $offer === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('shift-swap-offers', $offer));
            }),

            FakeRoute::post('rota/shift-swap-offers/{id}/withdraw', static function (RecordStore $store, array $matches): mixed {
                $offer = $store->update('rota/shift-swap-offers', $matches[1], ['status' => 'Cancelled']);

                return $offer === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('shift-swap-offers', $offer));
            }),
        ];
    }
}

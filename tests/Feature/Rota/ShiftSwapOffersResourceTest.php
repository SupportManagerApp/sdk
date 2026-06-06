<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftSwapOffer;
use SupportManager\Sdk\Data\Rota\ShiftSwapRequest;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Rota\ShiftSwapOffersResource;

function rotaSwapOffers(): ShiftSwapOffersResource
{
    return app(ShiftSwapOffersResource::class);
}

function rotaOffer(string $id, string $status = 'Declined'): array
{
    return [
        'data' => [
            'type' => 'shift-swap-offers',
            'id' => $id,
            'attributes' => ['status' => $status, 'user' => ['id' => 3, 'name' => 'Linus']],
        ],
    ];
}

it('accepts an offer and returns the finalised parent swap', function () {
    Http::fake(['api.test/api/v1/rota/shift-swap-offers/50/accept' => Http::response([
        'data' => [
            'type' => 'shift-swap-requests',
            'id' => '1',
            'attributes' => ['status' => 'Approved', 'requester' => ['id' => 1, 'name' => 'Ada']],
        ],
    ])]);
    bootTenantSession();

    $swap = rotaSwapOffers()->accept(50);

    expect($swap)->toBeInstanceOf(ShiftSwapRequest::class)
        ->and($swap->id)->toBe('1');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'rota/shift-swap-offers/50/accept')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('declines an offer and returns the updated offer', function () {
    Http::fake(['api.test/api/v1/rota/shift-swap-offers/50/decline' => Http::response(rotaOffer('50', 'Declined'))]);
    bootTenantSession();

    $offer = rotaSwapOffers()->decline(50);

    expect($offer)->toBeInstanceOf(ShiftSwapOffer::class)
        ->and($offer->status)->toBe('Declined');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'rota/shift-swap-offers/50/decline'));
});

it('withdraws an offer and returns the updated offer', function () {
    Http::fake(['api.test/api/v1/rota/shift-swap-offers/50/withdraw' => Http::response(rotaOffer('50', 'Cancelled'))]);
    bootTenantSession();

    $offer = rotaSwapOffers()->withdraw(50);

    expect($offer->status)->toBe('Cancelled');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'rota/shift-swap-offers/50/withdraw'));
});

it('maps a 422 on an offer action to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/shift-swap-offers/50/accept' => Http::response([
        'message' => 'This offer can no longer be accepted.',
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaSwapOffers()->accept(50))
        ->toThrow(ApiValidationException::class);
});

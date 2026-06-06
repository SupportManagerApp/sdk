<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftSwapOffer;
use SupportManager\Sdk\Data\Rota\ShiftSwapRequest;
use SupportManager\Sdk\Enums\Rota\ShiftSwapStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Rota\ShiftSwapsResource;

function rotaSwaps(): ShiftSwapsResource
{
    return app(ShiftSwapsResource::class);
}

function rotaSwap(string $id, string $status = 'Pending Response'): array
{
    return [
        'data' => [
            'type' => 'shift-swap-requests',
            'id' => $id,
            'attributes' => [
                'type' => 'Directed',
                'status' => $status,
                'status_label' => $status,
                'requester' => ['id' => 1, 'name' => 'Ada'],
                'target_user' => ['id' => 2, 'name' => 'Grace'],
                'requester_entries' => [
                    ['id' => '10', 'attributes' => ['side' => 'requester', 'effective_duration' => 8]],
                ],
                'requester_total_hours' => 8.0,
                'respondent_total_hours' => 8.0,
                'hours_are_equal' => true,
            ],
        ],
        'meta' => ['team_id' => 42],
    ];
}

it('encodes scope filter and tenant header on the swaps index', function () {
    Http::fake(['api.test/api/v1/rota/shift-swaps*' => Http::response([
        'data' => [rotaSwap('1')['data']],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ])]);
    bootTenantSession();

    rotaSwaps()->query()->filter('scope', 'incoming')->sort('expires_at')->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[scope]=incoming')
            && str_contains($url, 'sort=expires_at')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('finds a swap and hydrates the typed status and nested entries', function () {
    Http::fake(['api.test/api/v1/rota/shift-swaps/1' => Http::response(rotaSwap('1'))]);
    bootTenantSession();

    $swap = rotaSwaps()->find(1);

    expect($swap)->toBeInstanceOf(ShiftSwapRequest::class)
        ->and($swap->status)->toBe(ShiftSwapStatus::PendingResponse)
        ->and($swap->requesterName)->toBe('Ada')
        ->and($swap->targetUserName)->toBe('Grace')
        ->and($swap->requesterEntries)->toHaveCount(1)
        ->and($swap->requesterEntries[0]->side)->toBe('requester');
});

it('creates a swap request', function () {
    Http::fake(['api.test/api/v1/rota/shift-swaps' => Http::response(rotaSwap('9'), 201)]);
    bootTenantSession();

    $swap = rotaSwaps()->create(['requester_shift_ids' => [10], 'type' => 'Directed', 'target_user_id' => 2]);

    expect($swap->id)->toBe('9');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['type'] === 'Directed');
});

it('runs each workflow action via POST and returns the updated swap', function () {
    foreach (['cancel', 'accept', 'decline', 'approve', 'reject'] as $action) {
        Http::fake(["api.test/api/v1/rota/shift-swaps/1/{$action}" => Http::response(rotaSwap('1', 'Approved'))]);
        bootTenantSession();

        $swap = rotaSwaps()->{$action}(1);

        expect($swap)->toBeInstanceOf(ShiftSwapRequest::class)
            ->and($swap->status)->toBe(ShiftSwapStatus::Approved);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), "rota/shift-swaps/1/{$action}"));
    }
});

it('passes a payload to accept', function () {
    Http::fake(['api.test/api/v1/rota/shift-swaps/1/accept' => Http::response(rotaSwap('1', 'Both Agreed'))]);
    bootTenantSession();

    rotaSwaps()->accept(1, ['respondent_shift_ids' => [33]]);

    Http::assertSent(fn ($request) => $request['respondent_shift_ids'] === [33]);
});

it('offers against an open swap and hydrates the created offer', function () {
    Http::fake(['api.test/api/v1/rota/shift-swaps/1/offers' => Http::response([
        'data' => [
            'type' => 'shift-swap-offers',
            'id' => '50',
            'attributes' => ['status' => 'Pending Response', 'user' => ['id' => 3, 'name' => 'Linus']],
        ],
    ], 201)]);
    bootTenantSession();

    $offer = rotaSwaps()->offer(1, ['offered_shift_ids' => [34]]);

    expect($offer)->toBeInstanceOf(ShiftSwapOffer::class)
        ->and($offer->id)->toBe('50')
        ->and($offer->userName)->toBe('Linus');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['offered_shift_ids'] === [34]);
});

it('maps a 422 on create to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/shift-swaps' => Http::response([
        'message' => 'Invalid swap configuration.',
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaSwaps()->create([]))
        ->toThrow(ApiValidationException::class);
});

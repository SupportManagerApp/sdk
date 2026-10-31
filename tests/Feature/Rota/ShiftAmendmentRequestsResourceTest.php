<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftAmendmentRequest;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Rota\ShiftAmendmentRequestsResource;

function rotaShiftAmendmentRequests(): ShiftAmendmentRequestsResource
{
    return app(ShiftAmendmentRequestsResource::class);
}

function rotaShiftAmendmentPage(): array
{
    return [
        'data' => [[
            'id' => '4',
            'type' => 'shift_amendment_requests',
            'attributes' => [
                'team_id' => 42,
                'user_id' => 7,
                'rota_entry_id' => 51,
                'proposed_start' => '2026-11-20T15:00:00+00:00',
                'proposed_end' => '2026-11-20T23:00:00+00:00',
                'requested_pay_enhancement_id' => 3,
                'requested_tags' => ['Driving'],
                'reason' => 'Stayed on to cover the handover',
                'status' => 'Pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_at' => '2026-06-06T09:00:00.000000Z',
                'updated_at' => '2026-06-06T09:00:00.000000Z',
            ],
        ]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'team_id' => 42],
        'links' => ['next' => null],
    ];
}

it('encodes filters and tenant header on the amendment requests index', function () {
    Http::fake(['api.test/api/v1/rota/shift-amendment-requests*' => Http::response(rotaShiftAmendmentPage())]);
    bootTenantSession();

    rotaShiftAmendmentRequests()->query()->filter('status', 'Pending')->filter('rota_entry_id', 51)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[status]=Pending')
            && str_contains($url, 'filter[rota_entry_id]=51')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates amendment requests with their proposed times and tags', function () {
    Http::fake(['api.test/api/v1/rota/shift-amendment-requests*' => Http::response(rotaShiftAmendmentPage())]);
    bootTenantSession();

    $page = rotaShiftAmendmentRequests()->all();
    $amendment = $page->items()[0];

    expect($amendment)->toBeInstanceOf(ShiftAmendmentRequest::class)
        ->and($amendment->id)->toBe('4')
        ->and($amendment->rotaEntryId)->toBe(51)
        ->and($amendment->proposedStart)->toBe('2026-11-20T15:00:00+00:00')
        ->and($amendment->proposedEnd)->toBe('2026-11-20T23:00:00+00:00')
        ->and($amendment->requestedPayEnhancementId)->toBe(3)
        ->and($amendment->requestedTags)->toBe(['Driving'])
        ->and($amendment->status)->toBe('Pending')
        ->and($amendment->reviewedBy)->toBeNull()
        ->and($page->total())->toBe(1);
});

it('throws an api exception on a server error', function () {
    Http::fake(['api.test/api/v1/rota/shift-amendment-requests*' => Http::response('<html>boom</html>', 500)]);
    bootTenantSession();

    expect(fn () => rotaShiftAmendmentRequests()->all())
        ->toThrow(SupportManagerApiException::class);
});

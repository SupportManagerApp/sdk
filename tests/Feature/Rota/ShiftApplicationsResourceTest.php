<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftApplication;

function rotaApplicationPage(): array
{
    return [
        'data' => [[
            'type' => 'shift-applications',
            'id' => '12',
            'attributes' => [
                'status' => 'Pending',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'user' => ['id' => 7, 'name' => 'Ada'],
                'rota_entry' => ['data' => ['id' => '5', 'type' => 'rota_entries', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]]],
            ],
        ]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ];
}

it('encodes filter and tenant header on the applications index', function () {
    fakeApi(['rota/shift-applications*' => Http::response(rotaApplicationPage())]);

    sdk()->shiftApplications()->query()->filter('status', 'Pending')->sort('-created_at')->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[status]=Pending')
            && str_contains($url, 'sort=-created_at')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates applications and their nested shift', function () {
    fakeApi(['rota/shift-applications*' => Http::response(rotaApplicationPage())]);

    $page = sdk()->shiftApplications()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(ShiftApplication::class)
        ->and($page->items()[0]->status)->toBe('Pending')
        ->and($page->items()[0]->userName)->toBe('Ada')
        ->and($page->items()[0]->rotaEntry?->id)->toBe('5')
        ->and($page->total())->toBe(1);
});

it('retracts a pending application', function () {
    fakeApi(['rota/shift-applications/12' => ['message' => 'Retracted']]);

    sdk()->shiftApplications()->destroy(12);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'rota/shift-applications/12'));
});

it('reads the privileged-only provisional flag, null otherwise', function () {
    fakeApi(['rota/shift-applications*' => [
        'data' => [
            ['type' => 'shift_applications', 'id' => '1', 'attributes' => ['status' => 'Pencilled In', 'is_provisional' => true]],
            ['type' => 'shift_applications', 'id' => '2', 'attributes' => ['status' => 'Pending']],
        ],
    ]]);

    $items = sdk()->shiftApplications()->all()->items();

    expect($items[0]->isProvisional)->toBeTrue()
        ->and($items[1]->isProvisional)->toBeNull();
});

it('keeps the nested shift meta.can flags', function () {
    fakeApi(['rota/shift-applications*' => [
        'data' => [[
            'type' => 'shift_applications',
            'id' => '1',
            'attributes' => [
                'status' => 'Pending',
                'rota_entry' => ['data' => [
                    'id' => '5',
                    'type' => 'rota_entries',
                    'attributes' => ['type' => 'Contracted Hours', 'shift_block_id' => 9],
                    'meta' => ['can' => ['apply' => false, 'request_amendment' => true]],
                ]],
            ],
        ]],
    ]]);

    $shift = sdk()->shiftApplications()->all()->items()[0]->rotaEntry;

    expect($shift?->id)->toBe('5')
        ->and($shift->shiftBlockId)->toBe(9)
        ->and($shift->canRequestAmendment)->toBeTrue();
});

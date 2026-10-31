<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\BinCollection;
use SupportManager\Sdk\Data\Household\BinCollectionSchedule;
use SupportManager\Sdk\Enums\Household\BinType;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

it('reads each bin\'s next collection from a flat data body', function () {
    // The platform's BinDayControllerTest body.
    fakeApi(['household/bin-day' => [
        'data' => [
            'bins_due_tomorrow' => ['rubbish', 'food'],
            'collections' => [
                ['bin' => 'rubbish', 'label' => 'Rubbish', 'next_collection_on' => '2026-11-03', 'is_due_tomorrow' => true],
                ['bin' => 'recycling', 'label' => 'Recycling', 'next_collection_on' => '2026-11-10', 'is_due_tomorrow' => false],
                ['bin' => 'food', 'label' => 'Food', 'next_collection_on' => '2026-11-03', 'is_due_tomorrow' => true],
            ],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $schedule = sdk()->binDay()->show();

    expect($schedule)->toBeInstanceOf(BinCollectionSchedule::class)
        ->and($schedule->binsDueTomorrow)->toBe([BinType::Rubbish, BinType::Food])
        ->and($schedule->hasBinsDueTomorrow())->toBeTrue()
        ->and($schedule->collections)->toHaveCount(3)
        ->and($schedule->collections[0])->toEqual(new BinCollection(BinType::Rubbish, 'Rubbish', '2026-11-03', true))
        ->and($schedule->collectionOf(BinType::Recycling))->toEqual(new BinCollection(BinType::Recycling, 'Recycling', '2026-11-10', false))
        ->and($schedule->collectionOf(BinType::Food)?->label)->toBe(BinType::Food->label());

    assertSentTo('GET', 'household/bin-day', fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('reads a bin the council has no date for, and a week with nothing due tomorrow', function () {
    fakeApi(['household/bin-day' => [
        'data' => [
            'bins_due_tomorrow' => [],
            'collections' => [
                ['bin' => 'rubbish', 'label' => 'Rubbish', 'next_collection_on' => '2026-11-10', 'is_due_tomorrow' => false],
                ['bin' => 'recycling', 'label' => 'Recycling', 'next_collection_on' => null, 'is_due_tomorrow' => false],
                ['bin' => 'food', 'label' => 'Food', 'next_collection_on' => null, 'is_due_tomorrow' => false],
            ],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $schedule = sdk()->binDay()->show();

    expect($schedule->hasBinsDueTomorrow())->toBeFalse()
        ->and($schedule->collectionOf(BinType::Recycling)?->nextCollectionOn)->toBeNull()
        ->and($schedule->collectionOf(BinType::Food)?->isDueTomorrow)->toBeFalse();
});

it('skips a bin it does not know, as the platform says to', function () {
    fakeApi(['household/bin-day' => [
        'data' => [
            'bins_due_tomorrow' => ['garden', 'rubbish'],
            'collections' => [
                ['bin' => 'rubbish', 'label' => 'Rubbish', 'next_collection_on' => '2026-11-03', 'is_due_tomorrow' => true],
                ['bin' => 'garden', 'label' => 'Garden', 'next_collection_on' => '2026-11-03', 'is_due_tomorrow' => true],
            ],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $schedule = sdk()->binDay()->show();

    expect($schedule->binsDueTomorrow)->toBe([BinType::Rubbish])
        ->and($schedule->collections)->toHaveCount(1)
        ->and($schedule->collections[0]->bin)->toBe(BinType::Rubbish)
        ->and($schedule->collectionOf(BinType::Recycling))->toBeNull();
});

it('throws a 404 while the team has Household switched off', function () {
    fakeApi(['household/bin-day' => Http::response(['message' => 'Not Found'], 404)]);

    expect(fn () => sdk()->binDay()->show())->toThrow(
        fn (SupportManagerApiException $exception) => expect($exception->status)->toBe(404),
    );
});

it('throws a 403 for a shift worker off shift', function () {
    fakeApi(['household/bin-day' => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    sdk()->binDay()->show();
})->throws(SupportManagerApiException::class, 'This action is unauthorized.');

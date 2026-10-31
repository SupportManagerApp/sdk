<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Data\Household\Keysafe;
use SupportManager\Sdk\Data\Notifications\Notification;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\CarJourneyFactory;
use SupportManager\Sdk\Testing\Factories\KeysafeFactory;
use SupportManager\Sdk\Testing\Factories\NotificationFactory;

// These tests are written the way a HOST APP would: seed factories, call the
// SDK normally, assert on DTOs. No Http::fake(), no JSON:API envelopes by hand.

it('installs without a login and lists seeded JSON:API records as DTOs', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->count(3));

    $page = SupportManager::journeys()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(CarJourney::class)
        ->and($page->items()[0]->driverName)->toBe('Driver 1')
        ->and($page->total())->toBe(3);
});

it('accumulates across multiple seed calls and applies states', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->count(2));
    SupportManager::fake()->seed(CarJourneyFactory::new()->ongoing()->state(['driver_name' => 'Ada']));

    $ongoing = SupportManager::journeys()->ongoing();

    expect(SupportManager::journeys()->all()->total())->toBe(3)
        ->and($ongoing)->toBeInstanceOf(CarJourney::class)
        ->and($ongoing->driverName)->toBe('Ada')
        ->and($ongoing->isOngoing)->toBeTrue();
});

it('finds a seeded record by id and returns null for a miss', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->count(2));

    expect(SupportManager::journeys()->find(2))->toBeInstanceOf(CarJourney::class)
        ->and(SupportManager::journeys()->find(2)->id)->toBe('2')
        ->and(SupportManager::journeys()->find(999))->toBeNull();
});

it('paginates seeded records through the real pagination layer', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->count(5));

    $first = SupportManager::journeys()->query()->perPage(2)->get();

    expect($first->items())->toHaveCount(2)
        ->and($first->lastPage())->toBe(3)
        ->and($first->hasMorePages())->toBeTrue()
        ->and($first->next()->items()[0]->driverName)->toBe('Driver 3');
});

it('mutates the store through create, update and delete', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->count(1));

    $created = SupportManager::journeys()->create(['driver_name' => 'Grace']);
    expect($created->driverName)->toBe('Grace')
        ->and(SupportManager::journeys()->all()->total())->toBe(2);

    $updated = SupportManager::journeys()->update($created->id, ['comments' => 'Edited']);
    expect($updated->comments)->toBe('Edited');

    SupportManager::journeys()->destroy($created->id);
    expect(SupportManager::journeys()->all()->total())->toBe(1);
});

it('drives the start/finish lifecycle', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->startedBy('Hopper'));

    $started = SupportManager::journeys()->start();
    expect($started->isOngoing)->toBeTrue()
        ->and($started->driverName)->toBe('Hopper')
        ->and($started->comments)->toBeNull()
        ->and(SupportManager::journeys()->ongoing()->id)->toBe($started->id);

    $finished = SupportManager::journeys()->finish($started->id);
    expect($finished->isOngoing)->toBeFalse()
        ->and(SupportManager::journeys()->ongoing())->toBeNull();
});

it('refuses to start a journey while another is open', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->ongoing());

    expect(fn () => SupportManager::journeys()->start())
        ->toThrow(ApiValidationException::class, 'A journey is already in progress.');

    expect(SupportManager::journeys()->all()->total())->toBe(1);
});

it('honours the flat notifications filter and unread_count meta', function () {
    SupportManager::fake()->seed(
        NotificationFactory::new()->count(3),
        NotificationFactory::new()->read()->count(2),
    );

    expect(SupportManager::notifications()->all()->total())->toBe(5)
        ->and(SupportManager::notifications()->unread()->total())->toBe(3)
        ->and(SupportManager::notifications()->read()->total())->toBe(2)
        ->and(SupportManager::notifications()->unreadCount())->toBe(3)
        ->and(SupportManager::notifications()->all()->items()[0])->toBeInstanceOf(Notification::class);
});

it('toggles notification read state and clears all', function () {
    SupportManager::fake()->seed(NotificationFactory::new()->count(2));

    SupportManager::notifications()->markAsRead('1');
    expect(SupportManager::notifications()->unreadCount())->toBe(1);

    SupportManager::notifications()->markAllAsRead();
    expect(SupportManager::notifications()->unreadCount())->toBe(0);

    SupportManager::notifications()->destroyAll();
    expect(SupportManager::notifications()->all()->total())->toBe(0);
});

it('serves a flat singleton and hides the code until revealed', function () {
    SupportManager::fake()->seed(KeysafeFactory::new()->withCode('4321'));

    $shown = SupportManager::keysafe()->show();
    expect($shown)->toBeInstanceOf(Keysafe::class)
        ->and($shown->hasCode)->toBeTrue()
        ->and($shown->code)->toBeNull();

    expect(SupportManager::keysafe()->reveal()->code)->toBe('4321');
});

it('updates a flat singleton', function () {
    SupportManager::fake()->seed(KeysafeFactory::new()->withoutCode());

    expect(SupportManager::keysafe()->show()->hasCode)->toBeFalse();

    $updated = SupportManager::keysafe()->update('9999');
    expect($updated->hasCode)->toBeTrue()
        ->and(SupportManager::keysafe()->reveal()->code)->toBe('9999');
});

it('returns an empty page for an unseeded resource instead of hitting the network', function () {
    SupportManager::fake();

    expect(SupportManager::journeys()->all()->total())->toBe(0)
        ->and(SupportManager::journeys()->all()->items())->toBe([]);
});

// ── Unhappy path ──────────────────────────────────────────────────────────────

it('maps a programmed 422 to a validation exception with per-field errors', function () {
    SupportManager::fake()->failValidation('car-journeys', [
        'driver_name' => 'The driver name is required.',
    ], method: 'POST');

    try {
        SupportManager::journeys()->create([]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('driver_name'))->toBe('The driver name is required.');
    }
});

it('only fails the targeted method, leaving reads on the happy path', function () {
    SupportManager::fake()
        ->seed(CarJourneyFactory::new()->count(2))
        ->failValidation('car-journeys', ['driver_name' => 'Required'], method: 'POST');

    // Reads still work...
    expect(SupportManager::journeys()->all()->total())->toBe(2);

    // ...but writes hit the programmed failure.
    expect(fn () => SupportManager::journeys()->create([]))
        ->toThrow(ApiValidationException::class);
});

it('maps a programmed 5xx to an api exception', function () {
    SupportManager::fake()->fail('car-journeys*', 500);

    expect(fn () => SupportManager::journeys()->all())
        ->toThrow(SupportManagerApiException::class);
});

it('normalises a simulated connection failure to an api exception', function () {
    SupportManager::fake()->unreachable();

    expect(fn () => SupportManager::journeys()->all())
        ->toThrow(SupportManagerApiException::class);
});

it('can scope an unreachable failure to one resource', function () {
    SupportManager::fake()
        ->seed(CarJourneyFactory::new()->count(1), NotificationFactory::new()->count(1))
        ->unreachable('car-journeys*');

    expect(fn () => SupportManager::journeys()->all())->toThrow(SupportManagerApiException::class)
        ->and(SupportManager::notifications()->all()->total())->toBe(1);
});

it('clears programmed failures', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->count(1))->fail('car-journeys*', 500);

    expect(fn () => SupportManager::journeys()->all())->toThrow(SupportManagerApiException::class);

    SupportManager::fake()->clearFailures();

    expect(SupportManager::journeys()->all()->total())->toBe(1);
});

it('ends the ongoing journey when one is logged directly', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new()->ongoing()->state(['driver_name' => 'Ada']));

    SupportManager::journeys()->create(['driver_name' => 'Grace', 'is_ongoing' => false]);

    expect(SupportManager::journeys()->ongoing())->toBeNull()
        ->and(SupportManager::journeys()->find(1)?->tripEnd)->not->toBeNull();
});

it('carries seeded journey permissions through meta.can', function () {
    SupportManager::fake()->seed(CarJourneyFactory::new(), CarJourneyFactory::new()->locked());

    $journeys = SupportManager::journeys()->all()->items();

    expect($journeys[0]->canUpdate)->toBeTrue()
        ->and($journeys[0]->canDelete)->toBeTrue()
        ->and($journeys[1]->canUpdate)->toBeFalse()
        ->and($journeys[1]->canDelete)->toBeFalse();
});

<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Compliance\DBSCheck;
use SupportManager\Sdk\Data\Compliance\DriversLicenceCheck;
use SupportManager\Sdk\Data\Compliance\RightToWork;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\DBSCheckFactory;
use SupportManager\Sdk\Testing\Factories\DriversLicenceCheckFactory;
use SupportManager\Sdk\Testing\Factories\RightToWorkFactory;

// Host-style: seed factories, call the SDK normally, assert on DTOs.
// No Http::fake(), no JSON:API envelopes by hand.

it('lists seeded DBS checks as DTOs and finds by id', function () {
    SupportManager::fake()->seed(DBSCheckFactory::new()->count(3));

    $page = SupportManager::dbsChecks()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(DBSCheck::class)
        ->and($page->items()[0]->certificateNumber)->toBe('CERT-1')
        ->and($page->items()[0]->userName)->toBe('Employee 1')
        ->and($page->total())->toBe(3);

    $found = SupportManager::dbsChecks()->find(2);
    expect($found)->toBeInstanceOf(DBSCheck::class)
        ->and($found?->id)->toBe('2')
        ->and(SupportManager::dbsChecks()->find(999))->toBeNull();
});

it('applies the flagged DBS state', function () {
    SupportManager::fake()->seed(DBSCheckFactory::new()->flagged());

    expect(SupportManager::dbsChecks()->all()->items()[0]->checkResult)->toBe('flagged');
});

it('lists seeded right to work records as DTOs and finds by id', function () {
    SupportManager::fake()->seed(RightToWorkFactory::new()->count(2));

    $page = SupportManager::rightToWork()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(RightToWork::class)
        ->and($page->items()[0]->primaryDocumentType)->toBe('passport')
        ->and($page->items()[0]->shareCode)->toBe('SHARE-1')
        ->and($page->total())->toBe(2);

    $found = SupportManager::rightToWork()->find(1);
    expect($found)->toBeInstanceOf(RightToWork::class)
        ->and($found?->userName)->toBe('Employee 1')
        ->and(SupportManager::rightToWork()->find(999))->toBeNull();
});

it('applies the biometric residence permit right to work state', function () {
    SupportManager::fake()->seed(RightToWorkFactory::new()->biometricResidencePermit());

    expect(SupportManager::rightToWork()->all()->items()[0]->primaryDocumentType)->toBe('brp');
});

it('lists seeded drivers licence checks as DTOs and finds by id', function () {
    SupportManager::fake()->seed(DriversLicenceCheckFactory::new()->count(3));

    $page = SupportManager::driversLicenceChecks()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($page->items()[0]->driversLicenceNumber)->toBe('DL-1')
        ->and($page->items()[0]->declarationAccepted)->toBeTrue()
        ->and($page->total())->toBe(3);

    $found = SupportManager::driversLicenceChecks()->find(2);
    expect($found)->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($found?->id)->toBe('2')
        ->and(SupportManager::driversLicenceChecks()->find(999))->toBeNull();
});

it('applies the pending declaration drivers licence state', function () {
    SupportManager::fake()->seed(DriversLicenceCheckFactory::new()->declarationPending());

    expect(SupportManager::driversLicenceChecks()->all()->items()[0]->declarationAccepted)->toBeFalse();
});

it('paginates seeded compliance records through the real pagination layer', function () {
    SupportManager::fake()->seed(DBSCheckFactory::new()->count(5));

    $first = SupportManager::dbsChecks()->query()->perPage(2)->get();

    expect($first->items())->toHaveCount(2)
        ->and($first->lastPage())->toBe(3)
        ->and($first->hasMorePages())->toBeTrue()
        ->and($first->next()?->items()[0]->certificateNumber)->toBe('CERT-3');
});

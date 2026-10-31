<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Compliance\DBSCheck;
use SupportManager\Sdk\Data\Compliance\DriversLicenceCheck;
use SupportManager\Sdk\Data\Compliance\RightToWork;
use SupportManager\Sdk\Enums\Compliance\PrimaryDocumentType;
use SupportManager\Sdk\Exceptions\ApiValidationException;
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
        ->and($page->items()[0]->primaryDocumentType)->toBe('British Passport')
        ->and($page->items()[0]->shareCode)->toBe('SHARE-1')
        ->and($page->total())->toBe(2);

    $found = SupportManager::rightToWork()->find(1);
    expect($found)->toBeInstanceOf(RightToWork::class)
        ->and($found?->userName)->toBe('Employee 1')
        ->and(SupportManager::rightToWork()->find(999))->toBeNull();
});

it('applies the biometric residence permit right to work state', function () {
    SupportManager::fake()->seed(RightToWorkFactory::new()->biometricResidencePermit());

    expect(SupportManager::rightToWork()->all()->items()[0]->primaryDocumentType)->toBe('Immigration Share Code');
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

// ── Self-submission and downloads ───────────────────────────────────────────────

it('carries the seeded meta.can flags through to the DTOs', function () {
    SupportManager::fake()->seed(DBSCheckFactory::new(), DBSCheckFactory::new()->own());

    $checks = SupportManager::dbsChecks()->all()->items();

    expect($checks[0]->canUpdate)->toBeFalse()
        ->and($checks[0]->canDownload)->toBeTrue()
        ->and($checks[1]->canUpdate)->toBeTrue();
});

it('creates the user\'s own DBS check on first submission, then replaces it', function () {
    SupportManager::fake()->seed(DBSCheckFactory::new());

    $created = SupportManager::dbsChecks()->submit('2026-03-14', '001234567890', 'PDF-BYTES', 'dbs.pdf');

    expect($created)->toBeInstanceOf(DBSCheck::class)
        ->and($created->certificate)->toBe('dbs.pdf')
        ->and($created->canUpdate)->toBeTrue()
        ->and(SupportManager::dbsChecks()->all()->total())->toBe(2);

    // No file this time: the stored certificate is kept.
    $replaced = SupportManager::dbsChecks()->submit('2026-04-01', '999');

    expect($replaced->id)->toBe($created->id)
        ->and($replaced->certificateNumber)->toBe('999')
        ->and($replaced->certificate)->toBe('dbs.pdf')
        ->and(SupportManager::dbsChecks()->all()->total())->toBe(2);
});

it('rejects a first DBS submission without a certificate', function () {
    // A zero-count seed registers the module's routes with no records.
    SupportManager::fake()->seed(DBSCheckFactory::new()->count(0));

    try {
        SupportManager::dbsChecks()->submit('2026-03-14', '001234567890');
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('certificate'))->toBe('Please upload a copy of your DBS certificate.');
    }
});

it('downloads a stored DBS certificate and 404s when none is uploaded', function () {
    SupportManager::fake()->seed(DBSCheckFactory::new()->withCertificate('dbs.pdf'), DBSCheckFactory::new());

    $response = SupportManager::dbsChecks()->downloadCertificate(1);

    expect($response->json('data.filename'))->toBe('dbs.pdf')
        ->and($response->json('data.download_url'))->toStartWith('https://s3.test/signed/')
        ->and(SupportManager::dbsChecks()->downloadCertificate(2)->status())->toBe(404);
});

it('submits right to work records with the type-dependent rules', function () {
    SupportManager::fake()->seed(RightToWorkFactory::new()->own()->withDocuments('old.pdf', null));

    // A birth certificate also needs a supporting document.
    try {
        SupportManager::rightToWork()->submit(PrimaryDocumentType::UkBirthOrAdoptionCertificate);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->errors)->toHaveKey('supporting_document');
    }

    $saved = SupportManager::rightToWork()->submit(
        PrimaryDocumentType::UkBirthOrAdoptionCertificate,
        supportingDocument: 'NI-BYTES',
        supportingDocumentFilename: 'ni.pdf',
    );

    expect($saved->id)->toBe('1')
        ->and($saved->primaryDocumentType)->toBe('UK Birth or Adoption Certificate')
        ->and($saved->primaryDocument)->toBe('old.pdf')
        ->and($saved->supportingDocument)->toBe('ni.pdf');

    // Switching to a share code needs the code and drops the supporting document.
    $switched = SupportManager::rightToWork()->submit(PrimaryDocumentType::ImmigrationShareCode, shareCode: 'W4X 7YZ 9AB');

    expect($switched->shareCode)->toBe('W4X 7YZ 9AB')
        ->and($switched->supportingDocument)->toBeNull();
});

it('downloads right to work documents and 404s for a missing one', function () {
    SupportManager::fake()->seed(RightToWorkFactory::new()->withDocuments('passport.pdf', null));

    expect(SupportManager::rightToWork()->downloadPrimaryDocument(1)->json('data.filename'))->toBe('passport.pdf')
        ->and(SupportManager::rightToWork()->downloadSupportingDocument(1)->status())->toBe(404)
        ->and(SupportManager::rightToWork()->downloadPrimaryDocument(999)->status())->toBe(404);
});

it('submits driving licence details and requires the declaration', function () {
    SupportManager::fake()->seed(DriversLicenceCheckFactory::new()->own());

    $saved = SupportManager::driversLicenceChecks()->submit('HARBO801234AB9CD', 'Xy7Kp2Qm', declarationAccepted: true);

    expect($saved)->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($saved->id)->toBe('1')
        ->and($saved->driversLicenceNumber)->toBe('HARBO801234AB9CD');

    expect(fn () => SupportManager::driversLicenceChecks()->submit('HARBO801234AB9CD', 'Xy7Kp2Qm', declarationAccepted: false))
        ->toThrow(ApiValidationException::class);
});

it('downloads a drivers licence check result document', function () {
    SupportManager::fake()->seed(DriversLicenceCheckFactory::new()->withCheckResultDocument());

    expect(SupportManager::driversLicenceChecks()->downloadCheckResultDocument(1)->json('data.filename'))->toBe('result.pdf');
});

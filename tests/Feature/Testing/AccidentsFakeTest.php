<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Accidents\AccidentReport;
use SupportManager\Sdk\Enums\Accidents\AccidentSubjectCapacity;
use SupportManager\Sdk\Enums\Accidents\AccidentSubjectType;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\AccidentReportFactory;

// Host-style tests: seed factories, call the SDK normally, assert on DTOs.
// No Http::fake(), no JSON:API envelopes by hand.

it('lists seeded accident reports as DTOs with their meta.can flags', function () {
    SupportManager::fake()->seed(
        AccidentReportFactory::new()->count(2),
        AccidentReportFactory::new()->awaitingSubjectDeclaration(),
    );

    $page = SupportManager::accidents()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(AccidentReport::class)
        ->and($page->items()[0]->reporterName)->toBe('Sam Carer')
        ->and($page->items()[0]->isConfirmedBySubject())->toBeTrue()
        ->and($page->items()[0]->canRecordSubjectDeclaration)->toBeFalse()
        ->and($page->items()[2]->subjectName)->toBe('Jamie Doe')
        ->and($page->items()[2]->canRecordSubjectDeclaration)->toBeTrue()
        ->and($page->total())->toBe(3);
});

it('finds a seeded report by id, with the RIDDOR outcome, and returns null for a miss', function () {
    SupportManager::fake()->seed(AccidentReportFactory::new()->riddorReportable());

    $report = SupportManager::accidents()->find(1);

    expect($report)->toBeInstanceOf(AccidentReport::class)
        ->and($report?->riddorReportable)->toBeTrue()
        ->and($report?->riddor?->declaredName)->toBe('Morgan Manager')
        ->and(SupportManager::accidents()->find(999))->toBeNull();
});

it('records an accident about someone else, awaiting their declaration', function () {
    SupportManager::fake()->seed(AccidentReportFactory::new());

    $report = SupportManager::accidents()->create([
        'subject' => ['type' => AccidentSubjectType::SomeoneElse, 'name' => 'Alex Visitor', 'capacity' => AccidentSubjectCapacity::Visitor],
        'happened_at' => '2026-10-30T14:15:00+00:00',
        'location' => 'Front steps',
        'how_it_happened' => 'Missed the bottom step.',
        'reporter_declared_name' => 'Sam Carer',
    ]);

    expect($report?->id)->toBe('2')
        ->and($report?->reportNumber)->toBe(2)
        ->and($report?->subjectName)->toBe('Alex Visitor')
        ->and($report?->subjectOccupation)->toBe('Visitor')
        ->and($report?->subjectDeclaredAt)->toBeNull()
        ->and($report?->canRecordSubjectDeclaration)->toBeTrue()
        ->and(SupportManager::accidents()->all()->total())->toBe(2);
});

it('records an accident the reporter had, already confirmed', function () {
    // Seeding no records still registers the resource's create route.
    SupportManager::fake()->seed(AccidentReportFactory::new()->count(0));

    $report = SupportManager::accidents()->create([
        'subject' => ['type' => AccidentSubjectType::Reporter],
        'happened_at' => '2026-10-30T14:15:00+00:00',
        'location' => 'Kitchen',
        'how_it_happened' => 'Slipped.',
        'reporter_declared_name' => 'Sam Carer',
        'subject_declared_name' => 'Ignored',
    ]);

    expect($report?->subjectName)->toBe('Sam Carer')
        ->and($report?->subjectDeclaredName)->toBe('Sam Carer')
        ->and($report?->canRecordSubjectDeclaration)->toBeFalse()
        ->and($report?->id)->toBe('1');
});

it('records the subject declaration and then refuses a second one', function () {
    SupportManager::fake()->seed(AccidentReportFactory::new()->awaitingSubjectDeclaration());

    $report = SupportManager::accidents()->recordSubjectDeclaration(1, ' Jamie Doe ');

    expect($report?->subjectDeclaredName)->toBe('Jamie Doe')
        ->and($report?->isConfirmedBySubject())->toBeTrue()
        ->and($report?->canRecordSubjectDeclaration)->toBeFalse();

    try {
        SupportManager::accidents()->recordSubjectDeclaration(1, 'Jamie Doe');
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->getMessage())->toBe('The person who had the accident has already confirmed this report.');
    }
});

it('refuses a subject declaration when the reporter had the accident', function () {
    SupportManager::fake()->seed(AccidentReportFactory::new());

    SupportManager::accidents()->recordSubjectDeclaration(1, 'Sam Carer');
})->throws(ApiValidationException::class, 'The person who had the accident recorded it');

it('drives a validation failure on create through the real error mapping', function () {
    SupportManager::fake()
        ->seed(AccidentReportFactory::new())
        ->failValidation('accidents', ['location' => 'The location field is required.'], method: 'POST');

    try {
        SupportManager::accidents()->create(['subject' => ['type' => 'me']]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('location'))->toBe('The location field is required.');
    }
});

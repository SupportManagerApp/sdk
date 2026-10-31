<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Accidents\AccidentReport;
use SupportManager\Sdk\Data\Accidents\AccidentRiddorOutcome;
use SupportManager\Sdk\Enums\Accidents\AccidentSubjectCapacity;
use SupportManager\Sdk\Enums\Accidents\AccidentSubjectType;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Accidents\AccidentsResource;

function accidents(): AccidentsResource
{
    return app(AccidentsResource::class);
}

function accidentReportObject(string $id, array $attributes = [], bool $canDeclare = false): array
{
    return [
        'type' => 'accident_reports',
        'id' => $id,
        'attributes' => [
            'report_number' => (int) $id,
            'riddor_reportable' => false,
            'subject' => ['user_id' => 12, 'name' => 'Jamie Doe', 'occupation' => null],
            'reporter' => ['user_id' => 4, 'name' => 'Sam Carer'],
            'happened_at' => '2026-10-30T14:15:00+00:00',
            'location' => 'Kitchen',
            'how_it_happened' => 'Slipped on a wet floor.',
            'cause' => 'Spilt water',
            'injury_details' => 'Bruised knee',
            'reporter_declaration' => ['name' => 'Sam Carer', 'declared_at' => '2026-10-30T14:20:00.000000Z'],
            'subject_declaration' => ['name' => null, 'declared_at' => null],
            'created_at' => '2026-10-30T14:20:00.000000Z',
            'updated_at' => '2026-10-30T14:20:00.000000Z',
            ...$attributes,
        ],
        'meta' => ['can' => ['record_subject_declaration' => $canDeclare]],
    ];
}

it('lists accident reports with sort and pagination params and the team header', function () {
    Http::fake(['api.test/api/v1/accidents*' => Http::response([
        'data' => [accidentReportObject('7', canDeclare: true)],
        'meta' => ['team_id' => 42, 'current_page' => 1, 'last_page' => 3, 'per_page' => 1, 'total' => 3],
        'links' => ['next' => 'https://api.test/api/v1/accidents?page=2'],
    ])]);
    bootTenantSession();

    $page = accidents()->query()->sort('-happened_at')->perPage(1)->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(AccidentReport::class)
        ->and($page->items()[0]->id)->toBe('7')
        ->and($page->items()[0]->reportNumber)->toBe(7)
        ->and($page->items()[0]->subjectUserId)->toBe(12)
        ->and($page->items()[0]->subjectName)->toBe('Jamie Doe')
        ->and($page->items()[0]->reporterName)->toBe('Sam Carer')
        ->and($page->items()[0]->reporterDeclaredName)->toBe('Sam Carer')
        ->and($page->items()[0]->subjectDeclaredAt)->toBeNull()
        ->and($page->items()[0]->isConfirmedBySubject())->toBeFalse()
        ->and($page->items()[0]->riddor)->toBeNull()
        ->and($page->items()[0]->canRecordSubjectDeclaration)->toBeTrue()
        ->and($page->hasMorePages())->toBeTrue();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'api/v1/accidents?')
            && str_contains($url, 'sort=-happened_at')
            && str_contains($url, 'per_page=1')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('finds a report including the RIDDOR outcome sent to managers', function () {
    Http::fake(['api.test/api/v1/accidents/7' => Http::response([
        'data' => accidentReportObject('7', [
            'riddor_reportable' => true,
            'riddor' => ['how_reported' => 'HSE online form', 'declared_name' => 'Morgan Manager', 'declared_at' => '2026-10-31T09:30:00.000000Z'],
        ]),
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $report = accidents()->find(7);

    expect($report)->toBeInstanceOf(AccidentReport::class)
        ->and($report->riddorReportable)->toBeTrue()
        ->and($report->riddor)->toBeInstanceOf(AccidentRiddorOutcome::class)
        ->and($report->riddor?->howReported)->toBe('HSE online form')
        ->and($report->riddor?->declaredName)->toBe('Morgan Manager');
});

it('returns null when finding a missing report (404)', function () {
    Http::fake(['api.test/api/v1/accidents/999' => Http::response(['message' => 'Not Found'], 404)]);
    bootTenantSession();

    expect(accidents()->find(999))->toBeNull();
});

it('records an accident, encoding enums to their values', function () {
    Http::fake(['api.test/api/v1/accidents' => Http::response([
        'data' => accidentReportObject('8', [
            'subject' => ['user_id' => null, 'name' => 'Alex Visitor', 'occupation' => 'Visitor'],
        ], canDeclare: true),
        'meta' => ['team_id' => 42],
    ], 201)]);
    bootTenantSession();

    $report = accidents()->create([
        'subject' => ['type' => AccidentSubjectType::SomeoneElse, 'name' => 'Alex Visitor', 'capacity' => AccidentSubjectCapacity::Visitor],
        'happened_at' => '2026-10-30T14:15:00+00:00',
        'location' => 'Front steps',
        'how_it_happened' => 'Missed the bottom step.',
        'reporter_declared_name' => 'Sam Carer',
    ]);

    expect($report)->toBeInstanceOf(AccidentReport::class)
        ->and($report->id)->toBe('8')
        ->and($report->subjectUserId)->toBeNull()
        ->and($report->subjectOccupation)->toBe('Visitor');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), 'api/v1/accidents')
        && json_decode($request->body(), true)['subject'] === ['type' => 'other', 'name' => 'Alex Visitor', 'capacity' => 'Visitor']
        && $request['reporter_declared_name'] === 'Sam Carer');
});

it('maps a 422 on create to a validation exception', function () {
    Http::fake(['api.test/api/v1/accidents' => Http::response([
        'message' => 'The selected subject.user id is invalid.',
        'errors' => ['subject.user_id' => ['The selected subject.user id is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        accidents()->create(['subject' => ['type' => 'team_member', 'user_id' => 999]]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('subject.user_id'))->toBe('The selected subject.user id is invalid.');
    }
});

it('records the subject declaration', function () {
    Http::fake(['api.test/api/v1/accidents/7/subject-declaration' => Http::response([
        'data' => accidentReportObject('7', [
            'subject_declaration' => ['name' => 'Jamie Doe', 'declared_at' => '2026-10-31T09:00:00.000000Z'],
        ]),
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $report = accidents()->recordSubjectDeclaration(7, 'Jamie Doe');

    expect($report?->subjectDeclaredName)->toBe('Jamie Doe')
        ->and($report?->isConfirmedBySubject())->toBeTrue()
        ->and($report?->canRecordSubjectDeclaration)->toBeFalse();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['subject_declared_name'] === 'Jamie Doe');
});

it('surfaces the plain 422 refusal when the report is already confirmed', function () {
    Http::fake(['api.test/api/v1/accidents/7/subject-declaration' => Http::response([
        'message' => 'The person who had the accident has already confirmed this report.',
        'meta' => ['team_id' => 42],
    ], 422)]);
    bootTenantSession();

    try {
        accidents()->recordSubjectDeclaration(7, 'Jamie Doe');
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->getMessage())->toBe('The person who had the accident has already confirmed this report.')
            ->and($e->errors)->toBe([]);
    }
});

it('throws when the team has the accident book switched off (404)', function () {
    Http::fake(['api.test/api/v1/accidents*' => Http::response(['message' => 'Not Found'], 404)]);
    bootTenantSession();

    accidents()->all();
})->throws(SupportManagerApiException::class);

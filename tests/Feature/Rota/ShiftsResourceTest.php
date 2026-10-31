<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\Shift;
use SupportManager\Sdk\Data\Rota\ShiftAmendmentRequest;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Data\Rota\ShiftPayEnhancement;
use SupportManager\Sdk\Enums\Rota\EntryType;
use SupportManager\Sdk\Enums\Rota\RecurringShiftScope;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

function rotaShiftPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'rota-entries',
            'id' => (string) $page,
            'attributes' => [
                'type' => 'Contracted Hours',
                'type_label' => 'Contracted Hours',
                'start' => '2026-06-06T09:00:00Z',
                'end' => '2026-06-06T17:00:00Z',
                'duration' => 8.0,
                'is_published' => true,
                'is_available' => false,
                'user' => ['id' => 7, 'name' => "User {$page}", 'avatar' => 'http://a/avatar.png'],
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/rota/shifts?page='.($page + 1) : null],
    ];
}

it('encodes Spatie-style query params and tenant header', function () {
    fakeApi(['rota/shifts*' => Http::response(rotaShiftPage(1, 1))]);

    sdk()->shifts()->query()
        ->filter('mode', 'available')
        ->sort('start')
        ->perPage(25)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[mode]=available')
            && str_contains($url, 'sort=start')
            && str_contains($url, 'per_page=25')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates shifts with pagination metadata', function () {
    fakeApi(['rota/shifts*' => Http::response(rotaShiftPage(1, 3))]);

    $page = sdk()->shifts()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(Shift::class)
        ->and($page->items()[0]->type)->toBe(EntryType::ContractedHours)
        ->and($page->items()[0]->userName)->toBe('User 1')
        ->and($page->items()[0]->userId)->toBe(7)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single shift by id', function () {
    fakeApi([
        'rota/shifts/5' => [
            'data' => ['type' => 'rota-entries', 'id' => '5', 'attributes' => ['type' => 'Sickness', 'duration' => 0]],
        ],
    ]);

    $shift = sdk()->shifts()->find(5);

    expect($shift)->toBeInstanceOf(Shift::class)
        ->and($shift->id)->toBe('5')
        ->and($shift->type)->toBe(EntryType::Sickness);
});

it('returns null when finding a missing shift (404)', function () {
    fakeApi(['rota/shifts/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->shifts()->find(999))->toBeNull();
});

it('lists todays shifts via the collection helper', function () {
    fakeApi(['rota/shifts/today' => [
        'data' => [
            ['type' => 'rota-entries', 'id' => '1', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]],
            ['type' => 'rota-entries', 'id' => '2', 'attributes' => ['type' => 'Overtime Hours', 'duration' => 4]],
        ],
    ]]);

    $shifts = sdk()->shifts()->today();

    expect($shifts)->toHaveCount(2)
        ->and($shifts[0])->toBeInstanceOf(Shift::class)
        ->and($shifts[1]->type)->toBe(EntryType::OvertimeHours);
});

it('lists available shifts', function () {
    fakeApi(['rota/shifts/available' => [
        'data' => [['type' => 'rota-entries', 'id' => '3', 'attributes' => ['is_available' => true, 'duration' => 8]]],
    ]]);

    $shifts = sdk()->shifts()->available();

    expect($shifts)->toHaveCount(1)
        ->and($shifts[0]->isAvailable)->toBeTrue();
});

it('applies for a shift and hydrates the created applications', function () {
    fakeApi(['rota/shifts/3/apply' => Http::response([
        'data' => [[
            'type' => 'shift-applications',
            'id' => '88',
            'attributes' => ['status' => 'Pending', 'user' => ['id' => 7, 'name' => 'Ada']],
        ]],
        'message' => 'Applied',
    ], 201)]);

    $applications = sdk()->shifts()->apply(3, ['is_split_application' => false]);

    expect($applications)->toHaveCount(1)
        ->and($applications[0])->toBeInstanceOf(ShiftApplication::class)
        ->and($applications[0]->status)->toBe('Pending');
    Http::assertSent(fn ($request) => $request['is_split_application'] === false);
});

it('lists upcoming shifts and forwards query overrides', function () {
    fakeApi(['rota/shifts/upcoming*' => [
        'data' => [['type' => 'rota-entries', 'id' => '9', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]]],
        'meta' => ['team_id' => 42, 'mode' => 'team', 'days' => 7, 'limit' => 10],
    ]]);

    $shifts = sdk()->shifts()->upcoming(['days' => 7, 'mode' => 'team']);

    expect($shifts)->toHaveCount(1)
        ->and($shifts[0])->toBeInstanceOf(Shift::class)
        ->and($shifts[0]->id)->toBe('9');
    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'rota/shifts/upcoming')
            && str_contains($url, 'days=7')
            && str_contains($url, 'mode=team');
    });
});

it('creates a shift and hydrates the stored rota entry', function () {
    fakeApi(['rota/shifts' => Http::response([
        'data' => ['type' => 'rota-entries', 'id' => '51', 'attributes' => [
            'user_id' => 12,
            'type' => 'Overtime Sleeping Night',
            'start' => '2026-08-17T22:00:00+01:00',
            'end' => '2026-08-18T07:00:00+01:00',
            'duration' => 9,
            'is_sleeping_night' => true,
            'is_overtime' => true,
        ]],
        'meta' => ['team_id' => 42],
    ], 201)]);

    $shift = sdk()->shifts()->create([
        'assignee_id' => 12,
        'date' => '2026-08-17',
        'start_time' => '22:00',
        'end_time' => '07:00',
        'is_sleeping_night' => true,
    ]);

    expect($shift)->toBeInstanceOf(Shift::class)
        ->and($shift->id)->toBe('51')
        ->and($shift->type)->toBe(EntryType::OvertimeSleepingNight)
        ->and($shift->isSleepingNight)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['assignee_id'] === 12
        && $request['start_time'] === '22:00'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('hydrates the wave 2 shift fields and its meta.can flags', function () {
    fakeApi(['rota/shifts/7' => [
        'data' => [
            'type' => 'rota_entries',
            'id' => '7',
            'attributes' => [
                'user_id' => null,
                'type' => 'Overtime Sleeping Night',
                'duration' => 8,
                'is_available' => true,
                'is_for_toil' => true,
                'is_private' => true,
                'shift_block_id' => 9,
                'tags' => ['Driving', 'Training'],
                'own_application_status' => 'Pending',
            ],
            'meta' => [
                'can' => ['apply' => false, 'request_amendment' => true],
                'cannot_apply_reason' => 'You have already applied for this shift.',
            ],
        ],
    ]]);

    $shift = sdk()->shifts()->find(7);

    expect($shift?->isForToil)->toBeTrue()
        ->and($shift->isPrivate)->toBeTrue()
        ->and($shift->shiftBlockId)->toBe(9)
        ->and($shift->tags)->toBe(['Driving', 'Training'])
        ->and($shift->ownApplicationStatus)->toBe('Pending')
        ->and($shift->canApply)->toBeFalse()
        ->and($shift->canRequestAmendment)->toBeTrue()
        ->and($shift->cannotApplyReason)->toBe('You have already applied for this shift.');
});

it('defaults the wave 2 shift fields when the platform omits them', function () {
    fakeApi(['rota/shifts/5' => [
        'data' => ['type' => 'rota_entries', 'id' => '5', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]],
    ]]);

    $shift = sdk()->shifts()->find(5);

    expect($shift?->isForToil)->toBeFalse()
        ->and($shift->shiftBlockId)->toBeNull()
        ->and($shift->tags)->toBe([])
        ->and($shift->ownApplicationStatus)->toBeNull()
        ->and($shift->canApply)->toBeFalse()
        ->and($shift->canRequestAmendment)->toBeFalse()
        ->and($shift->cannotApplyReason)->toBeNull();
});

it('hydrates the shift admin meta.can flags and the pay enhancement', function () {
    fakeApi(['rota/shifts/7' => [
        'data' => [
            'type' => 'rota_entries',
            'id' => '7',
            'attributes' => [
                'team_id' => 42,
                'user_id' => 12,
                'type' => 'Overtime Hours',
                'duration' => 8,
                'pay_enhancement' => ['id' => 4, 'name' => 'Weekend', 'factor' => '0.50', 'is_private' => true],
            ],
            'meta' => [
                'can' => [
                    'apply' => false,
                    'request_amendment' => false,
                    'update' => true,
                    'delete' => true,
                    'duplicate' => true,
                    'assign' => false,
                ],
                'cannot_apply_reason' => 'You cannot apply for this shift.',
            ],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $shift = sdk()->shifts()->find(7);

    expect($shift?->canUpdate)->toBeTrue()
        ->and($shift->canDelete)->toBeTrue()
        ->and($shift->canDuplicate)->toBeTrue()
        ->and($shift->canAssign)->toBeFalse()
        ->and($shift->payEnhancement)->toEqual(new ShiftPayEnhancement(4, 'Weekend', '0.50', true));
});

it('reads no pay enhancement and no admin flags when the platform omits them', function () {
    fakeApi(['rota/shifts/5' => [
        'data' => ['type' => 'rota_entries', 'id' => '5', 'attributes' => [
            'type' => 'Contracted Hours',
            'duration' => 8,
            'pay_enhancement' => null,
        ]],
    ]]);

    $shift = sdk()->shifts()->find(5);

    expect($shift?->payEnhancement)->toBeNull()
        ->and($shift->canUpdate)->toBeFalse()
        ->and($shift->canDelete)->toBeFalse()
        ->and($shift->canDuplicate)->toBeFalse()
        ->and($shift->canAssign)->toBeFalse();
});

it('requests an amendment to a shift and hydrates the pending request', function () {
    fakeApi(['rota/shifts/51/amendment-requests' => Http::response([
        'data' => ['type' => 'shift_amendment_requests', 'id' => '4', 'attributes' => [
            'rota_entry_id' => 51,
            'proposed_start' => '2026-11-20T15:00:00+00:00',
            'proposed_end' => '2026-11-20T23:00:00+00:00',
            'requested_tags' => ['Driving'],
            'reason' => 'Stayed on to cover the handover',
            'status' => 'Pending',
        ]],
        'meta' => ['team_id' => 42],
    ], 201)]);

    $amendment = sdk()->shifts()->requestAmendment(51, [
        'proposed_start_time' => '15:00',
        'proposed_end_time' => '23:00',
        'requested_tags' => ['Driving'],
        'reason' => 'Stayed on to cover the handover',
    ]);

    expect($amendment)->toBeInstanceOf(ShiftAmendmentRequest::class)
        ->and($amendment->id)->toBe('4')
        ->and($amendment->rotaEntryId)->toBe(51)
        ->and($amendment->proposedEnd)->toBe('2026-11-20T23:00:00+00:00')
        ->and($amendment->requestedTags)->toBe(['Driving'])
        ->and($amendment->status)->toBe('Pending');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['proposed_start_time'] === '15:00'
        && $request['requested_tags'] === ['Driving']
        && $request->hasHeader('X-Team-ID', '42'));
});

/**
 * A rota entry the way ShiftController's admin actions answer it: the
 * resource with its relations loaded and a manager's meta.can flags.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $meta  Extra document meta.
 * @return array<string, mixed>
 */
function managedShiftBody(string $id, array $attributes = [], array $meta = []): array
{
    return [
        'data' => [
            'type' => 'rota_entries',
            'id' => $id,
            'attributes' => [
                'team_id' => 42,
                'user_id' => 12,
                'user' => ['id' => 12, 'name' => 'Ada Lovelace', 'avatar' => 'https://example.test/ada.png'],
                'start' => '2026-11-02T22:00:00+00:00',
                'end' => '2026-11-03T06:00:00+00:00',
                'type' => 'Overtime Hours',
                'type_label' => 'Overtime Hours',
                'duration' => 8.0,
                'is_published' => true,
                'is_available' => false,
                'is_sleeping_night' => false,
                'is_overtime' => true,
                'is_for_toil' => false,
                'is_private' => false,
                'shift_block_id' => null,
                'tags' => ['Driving'],
                'own_application_status' => null,
                'notes' => null,
                'pay_enhancement' => ['id' => 4, 'name' => 'Nights', 'factor' => '0.50', 'is_private' => false],
                'created_at' => '2026-10-31T20:00:00.000000Z',
                'updated_at' => '2026-10-31T20:00:00.000000Z',
                ...$attributes,
            ],
            'meta' => [
                'can' => [
                    'apply' => false,
                    'request_amendment' => false,
                    'update' => true,
                    'delete' => true,
                    'duplicate' => true,
                    'assign' => true,
                ],
                'cannot_apply_reason' => 'You cannot apply for this shift.',
            ],
        ],
        'meta' => ['team_id' => 42, ...$meta],
    ];
}

it('edits a shift and sends the single-shift scope by default', function () {
    fakeApi(['rota/shifts/51' => managedShiftBody('51')]);

    $shift = sdk()->shifts()->update(51, ['start_time' => '22:00', 'end_time' => '06:00', 'pay_enhancement_id' => 4]);

    expect($shift)->toBeInstanceOf(Shift::class)
        ->and($shift->id)->toBe('51')
        ->and($shift->end)->toBe('2026-11-03T06:00:00+00:00')
        ->and($shift->payEnhancement?->id)->toBe(4)
        ->and($shift->canUpdate)->toBeTrue();
    assertSentTo('PATCH', 'rota/shifts/51', fn ($request) => $request->data() === [
        'start_time' => '22:00',
        'end_time' => '06:00',
        'pay_enhancement_id' => 4,
        'scope' => 'single',
    ]);
});

it('edits the rest of a repeating series with the future scope', function () {
    fakeApi(['rota/shifts/51' => managedShiftBody('51')]);

    sdk()->shifts()->update(51, ['type' => 'Overtime Hours', 'scope' => 'single'], RecurringShiftScope::Future);

    assertSentTo('PATCH', 'rota/shifts/51', fn ($request) => $request['scope'] === 'future' && $request['type'] === 'Overtime Hours');
});

it('edits a shift on a completed payslip once reset_payslip confirms it', function () {
    fakeApi(['rota/shifts/51' => managedShiftBody('51', [], [
        'warnings' => ['October 2026 payslip reset to draft - please regenerate and resubmit FPS.'],
    ])]);

    expect(sdk()->shifts()->update(51, ['end_time' => '07:00', 'reset_payslip' => true])?->id)->toBe('51');
    assertSentTo('PATCH', 'rota/shifts/51', fn ($request) => $request['reset_payslip'] === true);
});

it('surfaces the reset_payslip refusal of an edit as a validation exception', function () {
    $message = 'This shift is on the completed October 2026 payslip. Confirm with reset_payslip to put that payslip back to draft.';
    fakeApi(['rota/shifts/51' => Http::response(['message' => $message, 'errors' => ['reset_payslip' => [$message]]], 422)]);

    expect(fn () => sdk()->shifts()->update(51, ['end_time' => '07:00']))
        ->toThrow(fn (ApiValidationException $e) => expect($e->first('reset_payslip'))->toBe($message));
});

it('surfaces a refused shift edit as a 403', function () {
    fakeApi(['rota/shifts/51' => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    expect(fn () => sdk()->shifts()->update(51, ['end_time' => '18:00']))
        ->toThrow(fn (SupportManagerApiException $e) => expect($e->status)->toBe(403));
});

it('deletes a shift with the single-shift scope on the query string', function () {
    fakeApi(['rota/shifts/51*' => ['message' => 'Shift deleted.', 'meta' => ['team_id' => 42]]]);

    sdk()->shifts()->destroy(51);

    assertSentTo('DELETE', 'rota/shifts/51', fn ($request) => str_ends_with($request->url(), '/rota/shifts/51?scope=single')
        && $request->body() === '');
});

it('deletes the rest of a repeating series with the future scope', function () {
    fakeApi(['rota/shifts/51*' => ['message' => 'This and future shifts deleted.', 'meta' => ['team_id' => 42]]]);

    sdk()->shifts()->destroy(51, RecurringShiftScope::Future);

    assertSentTo('DELETE', 'rota/shifts/51', fn ($request) => str_ends_with($request->url(), '?scope=future'));
});

it('surfaces a refused shift delete as a 403', function () {
    fakeApi(['rota/shifts/51*' => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    expect(fn () => sdk()->shifts()->destroy(51))
        ->toThrow(fn (SupportManagerApiException $e) => expect($e->status)->toBe(403));
});

it('duplicates a shift onto another date and hydrates the copy', function () {
    fakeApi(['rota/shifts/51/duplicate' => Http::response(managedShiftBody('52', [
        'start' => '2026-11-03T22:00:00+00:00',
        'end' => '2026-11-04T06:00:00+00:00',
    ]), 201)]);

    $copy = sdk()->shifts()->duplicate(51, '2026-11-03');

    expect($copy)->toBeInstanceOf(Shift::class)
        ->and($copy->id)->toBe('52')
        ->and($copy->start)->toBe('2026-11-03T22:00:00+00:00')
        ->and($copy->tags)->toBe(['Driving'])
        ->and($copy->payEnhancement?->name)->toBe('Nights');
    assertSentTo('POST', 'rota/shifts/51/duplicate', fn ($request) => $request->data() === ['date' => '2026-11-03']);
});

it('duplicates a shift onto its own day when no date is given', function () {
    fakeApi(['rota/shifts/51/duplicate' => Http::response(managedShiftBody('52'), 201)]);

    expect(sdk()->shifts()->duplicate(51)?->id)->toBe('52');
    assertSentTo('POST', 'rota/shifts/51/duplicate', fn ($request) => $request->data() === []);
});

it('surfaces a bad duplicate date as a validation exception', function () {
    fakeApi(['rota/shifts/51/duplicate' => Http::response([
        'message' => 'The date field must be a valid date.',
        'errors' => ['date' => ['The date field must be a valid date.']],
    ], 422)]);

    expect(fn () => sdk()->shifts()->duplicate(51, '2026-13-45'))->toThrow(ApiValidationException::class);
});

it('assigns a shift to somebody and hydrates the staffed shift', function () {
    fakeApi(['rota/shifts/51/assignment' => managedShiftBody('51', [
        'user_id' => 15,
        'user' => ['id' => 15, 'name' => 'Grace Hopper', 'avatar' => 'https://example.test/grace.png'],
    ])]);

    $shift = sdk()->shifts()->assign(51, 15);

    expect($shift?->userId)->toBe(15)
        ->and($shift->userName)->toBe('Grace Hopper')
        ->and($shift->isAvailable)->toBeFalse();
    assertSentTo('POST', 'rota/shifts/51/assignment', fn ($request) => $request->data() === ['user_id' => 15]);
});

it('surfaces an assignment the platform refuses as a validation exception', function () {
    fakeApi(['rota/shifts/51/assignment' => Http::response([
        'message' => 'The shift could not be assigned.',
        'errors' => ['user_id' => ['The shift could not be assigned.']],
    ], 422)]);

    expect(fn () => sdk()->shifts()->assign(51, 15))
        ->toThrow(fn (ApiValidationException $e) => expect($e->first('user_id'))->toBe('The shift could not be assigned.'));
});

it('surfaces a refused assignment as a 403', function () {
    fakeApi(['rota/shifts/51/assignment' => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    expect(fn () => sdk()->shifts()->assign(51, 15))
        ->toThrow(fn (SupportManagerApiException $e) => expect($e->status)->toBe(403));
});

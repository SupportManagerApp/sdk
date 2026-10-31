<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\QuickAddAssigneeCandidate;
use SupportManager\Sdk\Data\Rota\QuickAddParse;
use SupportManager\Sdk\Data\Rota\QuickAddParseResult;
use SupportManager\Sdk\Enums\Rota\QuickAddIntent;
use SupportManager\Sdk\Enums\Rota\QuickAddStatus;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

function rotaQuickAddResult(array $overrides = []): array
{
    return [
        'intent' => 'shift',
        'title' => null,
        'assignee_name' => 'Sarah',
        'assignee_id' => null,
        'assignee_candidates' => [
            ['id' => 12, 'name' => 'Sarah Jones', 'score' => 0.82],
            ['id' => 14, 'name' => 'Sarah Smith', 'score' => 0.8],
        ],
        'date' => '2026-08-17',
        'end_date' => null,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'duration_minutes' => null,
        'location' => null,
        'is_virtual' => false,
        'is_all_day' => false,
        'is_sleeping_night' => false,
        'is_private' => false,
        'tags' => ['training'],
        'notes' => 'covering for Jane',
        'confidence' => 0.9,
        ...$overrides,
    ];
}

it('submits text with the timezone and returns the pending token', function () {
    fakeApi(['rota/quick-add/parse' => Http::response([
        'data' => ['token' => '9b1f2c1e-7c2a-4d1f-9d0f-1f9f3a4b5c6d', 'status' => 'pending', 'expires_in' => 300],
    ], 202)]);

    $parse = sdk()->quickAdd()->parse('dentist tuesday half three', 'Europe/London');

    expect($parse)->toBeInstanceOf(QuickAddParse::class)
        ->and($parse->token)->toBe('9b1f2c1e-7c2a-4d1f-9d0f-1f9f3a4b5c6d')
        ->and($parse->status)->toBe(QuickAddStatus::Pending)
        ->and($parse->isPending())->toBeTrue()
        ->and($parse->expiresIn)->toBe(300)
        ->and($parse->result)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['text'] === 'dentist tuesday half three'
        && $request['timezone'] === 'Europe/London'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('omits the timezone when none is given', function () {
    fakeApi(['rota/quick-add/parse' => Http::response([
        'data' => ['token' => '9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12', 'status' => 'pending', 'expires_in' => 300],
    ], 202)]);

    sdk()->quickAdd()->parse('dentist tuesday');

    Http::assertSent(fn ($request) => ! array_key_exists('timezone', $request->data()));
});

it('throws an api exception when the parse is throttled', function () {
    fakeApi(['rota/quick-add/parse' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

    expect(fn () => sdk()->quickAdd()->parse('dentist tuesday'))
        ->toThrow(SupportManagerApiException::class);
});

it('polls a pending parse', function () {
    fakeApi(['rota/quick-add/parse/9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12' => ['data' => ['status' => 'pending']]]);

    $state = sdk()->quickAdd()->poll('9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12');

    expect($state->isPending())->toBeTrue()
        ->and($state->isCompleted())->toBeFalse()
        ->and($state->token)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('X-Team-ID', '42'));
});

it('polls a completed parse and hydrates the full result', function () {
    fakeApi(['rota/quick-add/parse/9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12' => [
        'data' => ['status' => 'completed', 'result' => rotaQuickAddResult()],
    ]]);

    $state = sdk()->quickAdd()->poll('9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12');
    $result = $state->result;

    expect($state->isCompleted())->toBeTrue()
        ->and($result)->toBeInstanceOf(QuickAddParseResult::class)
        ->and($result->intent)->toBe(QuickAddIntent::Shift)
        ->and($result->assigneeName)->toBe('Sarah')
        ->and($result->assigneeId)->toBeNull()
        ->and($result->assigneeCandidates)->toHaveCount(2)
        ->and($result->assigneeCandidates[0])->toBeInstanceOf(QuickAddAssigneeCandidate::class)
        ->and($result->assigneeCandidates[0]->id)->toBe(12)
        ->and($result->assigneeCandidates[0]->score)->toBe(0.82)
        ->and($result->startTime)->toBe('09:00')
        ->and($result->tags)->toBe(['training'])
        ->and($result->confidence)->toBe(0.9);
});

it('polls a failed parse and carries the hint', function () {
    fakeApi(['rota/quick-add/parse/9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12' => [
        'data' => ['status' => 'failed', 'hint' => "We couldn't read that. Try again, or enter the details yourself."],
    ]]);

    $state = sdk()->quickAdd()->poll('9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12');

    expect($state->isFailed())->toBeTrue()
        ->and($state->result)->toBeNull()
        ->and($state->hint)->toBe("We couldn't read that. Try again, or enter the details yourself.");
});

it('tolerates a status and intent it does not recognise', function () {
    fakeApi(['rota/quick-add/parse/9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12' => [
        'data' => ['status' => 'queued', 'result' => rotaQuickAddResult(['intent' => 'reminder'])],
    ]]);

    $state = sdk()->quickAdd()->poll('9b2f6c1e-4d7a-4f2b-8e1a-3c5d7e9f0a12');

    expect($state->status)->toBeNull()
        ->and($state->isPending())->toBeFalse()
        ->and($state->isCompleted())->toBeFalse()
        ->and($state->result->intent)->toBeNull();
});

it('returns null for an unknown or expired token (404)', function () {
    fakeApi(['rota/quick-add/parse/gone' => Http::response(['message' => 'Parse token not found.'], 404)]);

    expect(sdk()->quickAdd()->poll('gone'))->toBeNull();
});

it('builds create payloads from a confirmed result', function () {
    $shift = QuickAddParseResult::fromArray(rotaQuickAddResult());
    $appointment = QuickAddParseResult::fromArray(rotaQuickAddResult([
        'intent' => 'appointment',
        'title' => 'Dentist',
        'assignee_name' => null,
        'assignee_candidates' => [],
        'start_time' => '15:30',
        'end_time' => null,
        'duration_minutes' => 30,
        'location' => '12 High Street',
        'tags' => [],
        'notes' => null,
    ]));

    expect($shift->toShiftPayload(['assignee_id' => 12]))->toBe([
        'date' => '2026-08-17',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'notes' => 'covering for Jane',
        'is_sleeping_night' => false,
        'is_private' => false,
        'tags' => ['training'],
        'assignee_id' => 12,
    ])->and($appointment->toAppointmentPayload())->toBe([
        'title' => 'Dentist',
        'date' => '2026-08-17',
        'start_time' => '15:30',
        'duration_minutes' => 30,
        'location' => '12 High Street',
        'is_virtual' => false,
        'is_all_day' => false,
    ]);
});

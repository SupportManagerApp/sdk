<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Rota\Anniversary;
use SupportManager\Sdk\Data\Rota\Appointment;
use SupportManager\Sdk\Data\Rota\BankHoliday;
use SupportManager\Sdk\Data\Rota\Birthday;
use SupportManager\Sdk\Data\Rota\QuickAddParse;
use SupportManager\Sdk\Data\Rota\Shift;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Enums\Rota\EntryType;
use SupportManager\Sdk\Enums\Rota\QuickAddIntent;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\AnniversaryFactory;
use SupportManager\Sdk\Testing\Factories\AppointmentFactory;
use SupportManager\Sdk\Testing\Factories\BankHolidayFactory;
use SupportManager\Sdk\Testing\Factories\BirthdayFactory;
use SupportManager\Sdk\Testing\Factories\QuickAddParseFactory;
use SupportManager\Sdk\Testing\Factories\ShiftApplicationFactory;
use SupportManager\Sdk\Testing\Factories\ShiftFactory;

// Host-style tests: seed factories, call the SDK normally, assert on DTOs.
// No Http::fake(), no JSON:API envelopes by hand.

// ── Shifts ──────────────────────────────────────────────────────────────────

it('lists and finds seeded shifts as DTOs', function () {
    SupportManager::fake()->seed(ShiftFactory::new()->count(3));

    $page = SupportManager::shifts()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(Shift::class)
        ->and($page->items()[0]->type)->toBe(EntryType::ContractedHours)
        ->and($page->items()[0]->userName)->toBe('User 1')
        ->and(SupportManager::shifts()->find(2)?->id)->toBe('2')
        ->and(SupportManager::shifts()->find(999))->toBeNull();
});

it('serves the today and available shift feeds', function () {
    SupportManager::fake()->seed(
        ShiftFactory::new()->count(2),
        ShiftFactory::new()->today(),
        ShiftFactory::new()->available(),
    );

    $today = SupportManager::shifts()->today();
    $available = SupportManager::shifts()->available();

    expect($today)->toHaveCount(1)
        ->and($today[0])->toBeInstanceOf(Shift::class)
        ->and($available)->toHaveCount(1)
        ->and($available[0]->isAvailable)->toBeTrue();
});

it('applies for an available shift and records the application', function () {
    SupportManager::fake()->seed(
        ShiftFactory::new()->available(),
        ShiftApplicationFactory::new()->count(0),
    );

    $applications = SupportManager::shifts()->apply(1, ['is_split_application' => false]);

    expect($applications)->toHaveCount(1)
        ->and($applications[0])->toBeInstanceOf(ShiftApplication::class)
        ->and($applications[0]->status)->toBe('Pending')
        ->and(SupportManager::shiftApplications()->all()->total())->toBe(1);
});

it('serves the unpaginated upcoming shift feed, bounded by limit', function () {
    SupportManager::fake()->seed(
        ShiftFactory::new()->count(2),
        ShiftFactory::new()->upcoming()->count(3),
    );

    expect(SupportManager::shifts()->upcoming())->toHaveCount(3)
        ->and(SupportManager::shifts()->upcoming(['limit' => 2]))->toHaveCount(2);
});

it('creates a shift from quick-add fields as published overtime', function () {
    SupportManager::fake()->seed(ShiftFactory::new()->count(1));

    $shift = SupportManager::shifts()->create([
        'assignee_id' => 12,
        'date' => '2026-08-17',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'notes' => 'covering for Jane',
    ]);

    expect($shift?->id)->toBe('2')
        ->and($shift->userId)->toBe(12)
        ->and($shift->type)->toBe(EntryType::OvertimeHours)
        ->and($shift->isOvertime)->toBeTrue()
        ->and($shift->isPublished)->toBeTrue()
        ->and($shift->start)->toBe('2026-08-17T09:00:00+00:00')
        ->and($shift->end)->toBe('2026-08-17T17:00:00+00:00')
        ->and($shift->duration)->toBe(8.0)
        ->and($shift->notes)->toBe('covering for Jane')
        ->and(SupportManager::shifts()->find(2)?->userId)->toBe(12)
        ->and(SupportManager::shifts()->all()->total())->toBe(2);
});

it('rolls an overnight sleeping night and defaults a missing end to an hour', function () {
    SupportManager::fake()->seed(ShiftFactory::new()->count(0));

    $night = SupportManager::shifts()->create([
        'assignee_id' => 12,
        'date' => '2026-08-17',
        'start_time' => '22:00',
        'end_time' => '07:00',
        'is_sleeping_night' => true,
    ]);
    $short = SupportManager::shifts()->create(['assignee_id' => 12, 'date' => '2026-08-17', 'start_time' => '09:00']);

    expect($night?->type)->toBe(EntryType::OvertimeSleepingNight)
        ->and($night->isSleepingNight)->toBeTrue()
        ->and($night->end)->toBe('2026-08-18T07:00:00+00:00')
        ->and($night->duration)->toBe(9.0)
        ->and($short?->end)->toBe('2026-08-17T10:00:00+00:00');
});

it('rejects a shift missing its required fields', function () {
    SupportManager::fake()->seed(ShiftFactory::new()->count(0));

    expect(fn () => SupportManager::shifts()->create(['date' => '2026-08-17']))
        ->toThrow(ApiValidationException::class);
});

// ── Shift applications ────────────────────────────────────────────────────────

it('lists seeded applications with their nested shift and cancels one', function () {
    SupportManager::fake()->seed(ShiftApplicationFactory::new()->count(2));

    $page = SupportManager::shiftApplications()->all();

    expect($page->total())->toBe(2)
        ->and($page->items()[0])->toBeInstanceOf(ShiftApplication::class)
        ->and($page->items()[0]->status)->toBe('Pending')
        ->and($page->items()[0]->rotaEntry?->id)->toBe('1');

    SupportManager::shiftApplications()->cancel(1);

    expect(SupportManager::shiftApplications()->all()->total())->toBe(1);
});

// ── Appointments ──────────────────────────────────────────────────────────────

it('lists, finds and serves upcoming appointments', function () {
    SupportManager::fake()->seed(
        AppointmentFactory::new()->count(2),
        AppointmentFactory::new()->upcoming()->state(['title' => 'Soon']),
    );

    $page = SupportManager::appointments()->all();
    $upcoming = SupportManager::appointments()->upcoming(['days' => 14]);

    expect($page->total())->toBe(3)
        ->and($page->items()[0])->toBeInstanceOf(Appointment::class)
        ->and(SupportManager::appointments()->find(1)?->title)->toBe('Appointment 1')
        ->and(SupportManager::appointments()->find(999))->toBeNull()
        ->and($upcoming)->toHaveCount(1)
        ->and($upcoming[0]->title)->toBe('Soon');
});

it('creates timed and all-day appointments from quick-add fields', function () {
    SupportManager::fake()->seed(AppointmentFactory::new()->count(1));

    $dentist = SupportManager::appointments()->create([
        'title' => 'Dentist',
        'date' => '2026-08-18',
        'start_time' => '15:30',
        'duration_minutes' => 30,
        'location' => '12 High Street',
        'notes' => 'Bring a list of current medication.',
    ]);
    $holiday = SupportManager::appointments()->create([
        'title' => 'Holiday in France',
        'date' => '2026-08-12',
        'end_date' => '2026-08-19',
        'is_all_day' => true,
    ]);
    $late = SupportManager::appointments()->create(['title' => 'Gig', 'date' => '2026-08-18', 'start_time' => '22:00', 'end_time' => '01:00']);

    expect($dentist?->id)->toBe('2')
        ->and($dentist->start)->toBe('2026-08-18T15:30:00+00:00')
        ->and($dentist->end)->toBe('2026-08-18T16:00:00+00:00')
        ->and($dentist->notes)->toBe('Bring a list of current medication.')
        ->and($holiday?->isAllDay)->toBeTrue()
        ->and($holiday->start)->toBe('2026-08-12T00:00:00+00:00')
        ->and($holiday->end)->toBe('2026-08-19T23:59:59+00:00')
        ->and($late?->end)->toBe('2026-08-19T01:00:00+00:00')
        ->and(SupportManager::appointments()->all()->total())->toBe(4);
});

it('rejects a timed appointment without a start time', function () {
    SupportManager::fake()->seed(AppointmentFactory::new()->count(0));

    expect(fn () => SupportManager::appointments()->create(['title' => 'Dentist', 'date' => '2026-08-18']))
        ->toThrow(ApiValidationException::class);
});

// ── Quick add (flat) ──────────────────────────────────────────────────────────

it('parses then polls a scripted quick-add outcome', function () {
    SupportManager::fake()->seed(QuickAddParseFactory::new());

    $quickAdd = SupportManager::quickAdd();
    $parse = $quickAdd->parse('dentist tuesday half three', 'Europe/London');
    $state = $quickAdd->poll((string) $parse?->token);

    expect($parse)->toBeInstanceOf(QuickAddParse::class)
        ->and($parse->isPending())->toBeTrue()
        ->and($parse->expiresIn)->toBe(300)
        ->and($state?->isCompleted())->toBeTrue()
        ->and($state->result?->intent)->toBe(QuickAddIntent::Appointment)
        ->and($state->result->title)->toBe('Dentist')
        ->and($state->result->durationMinutes)->toBe(30)
        ->and(SupportManager::fake()->store()->all('rota/quick-add/parse')[0]['text'])->toBe('dentist tuesday half three')
        ->and(SupportManager::fake()->store()->all('rota/quick-add/parse')[0]['timezone'])->toBe('Europe/London');
});

it('confirms a parsed shift straight into shifts()->create()', function () {
    SupportManager::fake()->seed(
        QuickAddParseFactory::new()->shift(),
        ShiftFactory::new()->count(0),
    );

    $quickAdd = SupportManager::quickAdd();
    $result = $quickAdd->poll((string) $quickAdd->parse('Sarah 9-5 Monday, tag training')?->token)?->result;
    $shift = SupportManager::shifts()->create($result?->toShiftPayload() ?? []);

    expect($result?->intent)->toBe(QuickAddIntent::Shift)
        ->and($result->assigneeId)->toBe(12)
        ->and($shift?->userId)->toBe(12)
        ->and($shift->start)->toBe('2026-08-17T09:00:00+00:00')
        ->and($shift->end)->toBe('2026-08-17T17:00:00+00:00');
});

it('consumes scripted outcomes in order, then answers failed', function () {
    SupportManager::fake()->seed(
        QuickAddParseFactory::new()->pending(),
        QuickAddParseFactory::new()->failed('Try again.'),
    );

    $quickAdd = SupportManager::quickAdd();
    $first = $quickAdd->parse('first');
    $second = $quickAdd->parse('second');
    $third = $quickAdd->parse('third');

    expect($first?->token)->not->toBe($second?->token)
        ->and($quickAdd->poll((string) $first?->token)?->isPending())->toBeTrue()
        ->and($quickAdd->poll((string) $second?->token)?->hint)->toBe('Try again.')
        ->and($quickAdd->poll((string) $third?->token)?->isFailed())->toBeTrue()
        ->and($quickAdd->poll((string) $third?->token)?->hint)->toBe(QuickAddParseFactory::FAILURE_HINT);
});

it('404s an unknown or unclaimed quick-add token', function () {
    SupportManager::fake()->seed(QuickAddParseFactory::new());

    $quickAdd = SupportManager::quickAdd();

    expect($quickAdd->poll('00000000-0000-4000-8000-000000000001'))->toBeNull()
        ->and($quickAdd->poll('nope'))->toBeNull()
        ->and(fn () => $quickAdd->parse('x'))->toThrow(ApiValidationException::class);
});

// ── Calendar feeds (flat) ─────────────────────────────────────────────────────

it('reads the flat bank-holiday feed', function () {
    SupportManager::fake()->seed(BankHolidayFactory::new()->count(2));

    $holidays = SupportManager::rotaCalendar()->bankHolidays([
        'filter' => ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
    ]);

    expect($holidays)->toHaveCount(2)
        ->and($holidays[0])->toBeInstanceOf(BankHoliday::class)
        ->and($holidays[0]->name)->toBe('Christmas Day')
        ->and($holidays[0]->region)->toBe('england-and-wales');
});

it('reads the flat birthday and anniversary feeds', function () {
    SupportManager::fake()->seed(
        BirthdayFactory::new()->count(1),
        AnniversaryFactory::new()->count(1),
    );

    $birthdays = SupportManager::rotaCalendar()->birthdays();
    $anniversaries = SupportManager::rotaCalendar()->anniversaries();

    expect($birthdays)->toHaveCount(1)
        ->and($birthdays[0])->toBeInstanceOf(Birthday::class)
        ->and($birthdays[0]->userName)->toBe('Member 1')
        ->and($anniversaries)->toHaveCount(1)
        ->and($anniversaries[0])->toBeInstanceOf(Anniversary::class)
        ->and($anniversaries[0]->years)->toBe(5);
});

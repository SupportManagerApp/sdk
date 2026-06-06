<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Rota\Anniversary;
use SupportManager\Sdk\Data\Rota\Appointment;
use SupportManager\Sdk\Data\Rota\BankHoliday;
use SupportManager\Sdk\Data\Rota\Birthday;
use SupportManager\Sdk\Data\Rota\Shift;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Data\Rota\ShiftSwapOffer;
use SupportManager\Sdk\Data\Rota\ShiftSwapRequest;
use SupportManager\Sdk\Enums\Rota\EntryType;
use SupportManager\Sdk\Enums\Rota\ShiftSwapStatus;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\AnniversaryFactory;
use SupportManager\Sdk\Testing\Factories\AppointmentFactory;
use SupportManager\Sdk\Testing\Factories\BankHolidayFactory;
use SupportManager\Sdk\Testing\Factories\BirthdayFactory;
use SupportManager\Sdk\Testing\Factories\ShiftApplicationFactory;
use SupportManager\Sdk\Testing\Factories\ShiftFactory;
use SupportManager\Sdk\Testing\Factories\ShiftSwapFactory;
use SupportManager\Sdk\Testing\Factories\ShiftSwapOfferFactory;

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

// ── Shift swaps ──────────────────────────────────────────────────────────────

it('finds a swap with typed status and nested entries', function () {
    SupportManager::fake()->seed(ShiftSwapFactory::new()->count(2));

    $swap = SupportManager::shiftSwaps()->find(1);

    expect($swap)->toBeInstanceOf(ShiftSwapRequest::class)
        ->and($swap?->status)->toBe(ShiftSwapStatus::PendingResponse)
        ->and($swap?->requesterName)->toBe('Requester 1')
        ->and($swap?->requesterEntries)->toHaveCount(1)
        ->and($swap?->requesterEntries[0]->side)->toBe('requester');
});

it('creates a swap and runs each workflow transition', function () {
    SupportManager::fake()->seed(ShiftSwapFactory::new()->count(1));

    $created = SupportManager::shiftSwaps()->create(['type' => 'Directed', 'target_user_id' => 2]);
    expect($created?->id)->toBe('2');

    $cases = [
        'cancel' => ShiftSwapStatus::Cancelled,
        'accept' => ShiftSwapStatus::BothAgreed,
        'decline' => ShiftSwapStatus::Declined,
        'approve' => ShiftSwapStatus::Approved,
        'reject' => ShiftSwapStatus::Rejected,
    ];

    foreach ($cases as $action => $expected) {
        $swap = SupportManager::shiftSwaps()->{$action}(1);
        expect($swap?->status)->toBe($expected);
    }
});

it('makes an offer against an open swap', function () {
    SupportManager::fake()->seed(ShiftSwapFactory::new()->openForOffers());

    $offer = SupportManager::shiftSwaps()->offer(1, ['offered_shift_ids' => [34]]);

    expect($offer)->toBeInstanceOf(ShiftSwapOffer::class)
        ->and($offer?->status)->toBe('Pending Response');
});

// ── Shift swap offers ─────────────────────────────────────────────────────────

it('accepts an offer and returns the finalised parent swap', function () {
    SupportManager::fake()->seed(
        ShiftSwapFactory::new()->openForOffers(),
        ShiftSwapOfferFactory::new()->forSwap(1),
    );

    $swap = SupportManager::shiftSwapOffers()->accept(1);

    expect($swap)->toBeInstanceOf(ShiftSwapRequest::class)
        ->and($swap?->id)->toBe('1')
        ->and($swap?->status)->toBe(ShiftSwapStatus::Approved);
});

it('declines and withdraws an offer, returning the updated offer', function () {
    SupportManager::fake()->seed(ShiftSwapOfferFactory::new()->count(2));

    expect(SupportManager::shiftSwapOffers()->decline(1)?->status)->toBe('Declined')
        ->and(SupportManager::shiftSwapOffers()->withdraw(2)?->status)->toBe('Cancelled');
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

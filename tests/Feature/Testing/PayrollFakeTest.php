<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Payroll\AutomaticPayEnhancementSettings;
use SupportManager\Sdk\Data\Payroll\EstimatedPay;
use SupportManager\Sdk\Data\Payroll\P60;
use SupportManager\Sdk\Data\Payroll\PayEnhancement;
use SupportManager\Sdk\Data\Payroll\PayRun;
use SupportManager\Sdk\Data\Payroll\Payslip;
use SupportManager\Sdk\Data\Payroll\Timesheet;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\AutomaticPayEnhancementSettingsFactory;
use SupportManager\Sdk\Testing\Factories\EstimatedPayFactory;
use SupportManager\Sdk\Testing\Factories\P60Factory;
use SupportManager\Sdk\Testing\Factories\PayEnhancementFactory;
use SupportManager\Sdk\Testing\Factories\PayRunFactory;
use SupportManager\Sdk\Testing\Factories\PayslipFactory;
use SupportManager\Sdk\Testing\Factories\TimesheetFactory;

// Host-style tests: seed factories, call the SDK normally, assert on DTOs.
// No Http::fake(), no hand-built JSON:API envelopes. Nullable lookups are
// narrowed with assert() so the chained assertions stay type-clean.

// ── Payslips (JSON:API: payslips) ───────────────────────────────────────────

it('lists seeded payslips as DTOs', function () {
    SupportManager::fake()->seed(PayslipFactory::new()->count(3));

    $page = SupportManager::payslips()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(Payslip::class)
        ->and($page->items()[0]->userName)->toBe('Employee 1')
        ->and($page->items()[0]->totalGross)->toBe(2500.0)
        ->and($page->total())->toBe(3);
});

it('finds a payslip with nested line items and returns null for a miss', function () {
    SupportManager::fake()->seed(PayslipFactory::new()->withLines()->count(2));

    $payslip = SupportManager::payslips()->find(1);
    assert($payslip instanceof Payslip);

    expect($payslip->payments)->toHaveCount(1)
        ->and($payslip->payments[0]->description)->toBe('Basic Pay')
        ->and($payslip->payments[0]->hours)->toBe(160.0)
        ->and($payslip->deductions[0]->total)->toBe(320.0)
        ->and($payslip->periodTotals[0]->total)->toBe(2400.0)
        ->and($payslip->ytdTotals[0]->total)->toBe(7200.0)
        ->and(SupportManager::payslips()->find(999))->toBeNull();
});

it('fetches the latest payslip', function () {
    SupportManager::fake()->seed(
        PayslipFactory::new()->count(2),
        PayslipFactory::new()->latest()->state(['net_pay_formatted' => '2,100.00']),
    );

    $latest = SupportManager::payslips()->latest();
    assert($latest instanceof Payslip);

    expect($latest->netPayFormatted)->toBe('2,100.00');
});

it('returns null when no payslip is flagged latest', function () {
    SupportManager::fake()->seed(PayslipFactory::new()->count(1));

    expect(SupportManager::payslips()->latest())->toBeNull();
});

it('downloads a payslip PDF as a raw response', function () {
    SupportManager::fake()->seed(PayslipFactory::new()->count(1));

    $response = SupportManager::payslips()->download(1);

    expect($response->status())->toBe(200)
        ->and($response->body())->toBe('%PDF-1.4 payslip-bytes')
        ->and($response->header('Content-Type'))->toBe('application/pdf');
});

// ── P60s (JSON:API: p60s) ───────────────────────────────────────────────────

it('lists and finds seeded P60s, with detail rows', function () {
    SupportManager::fake()->seed(P60Factory::new()->withDetails()->viewed()->count(2));

    $page = SupportManager::p60s()->all();

    $p60 = SupportManager::p60s()->find(1);
    assert($p60 instanceof P60);

    expect($page->items()[0])->toBeInstanceOf(P60::class)
        ->and($page->items()[0]->taxYear)->toBe('2025/26')
        ->and($p60->viewed)->toBeTrue()
        ->and($p60->payDetails[0]['description'])->toBe('Total Pay')
        ->and($p60->niContributions[0]['ni_letter'])->toBe('A')
        ->and(SupportManager::p60s()->find(999))->toBeNull();
});

it('downloads a P60 PDF as a raw response', function () {
    SupportManager::fake()->seed(P60Factory::new()->count(1));

    expect(SupportManager::p60s()->download(1)->body())->toBe('%PDF-1.4 p60-bytes');
});

// ── Timesheets (JSON:API: timesheets) ───────────────────────────────────────

it('lists timesheets and applies the approved state', function () {
    SupportManager::fake()->seed(
        TimesheetFactory::new()->count(2),
        TimesheetFactory::new()->approved(),
    );

    $page = SupportManager::timesheets()->all();

    $approved = SupportManager::timesheets()->find(3);
    assert($approved instanceof Timesheet);

    expect($page->total())->toBe(3)
        ->and($page->items()[0])->toBeInstanceOf(Timesheet::class)
        ->and($page->items()[0]->status)->toBe('submitted')
        ->and($page->items()[0]->grossPay)->toBe(1800.0)
        ->and($approved->status)->toBe('approved');
});

it('downloads a timesheet PDF as a raw response', function () {
    SupportManager::fake()->seed(TimesheetFactory::new()->count(1));

    expect(SupportManager::timesheets()->download(1)->body())->toBe('%PDF-1.4 timesheet-bytes');
});

// ── Pay enhancements (JSON:API: pay-enhancements, full CRUD) ────────────────

it('runs the pay-enhancement CRUD lifecycle', function () {
    SupportManager::fake()->seed(PayEnhancementFactory::new()->count(2));

    $found = SupportManager::payEnhancements()->find(1);
    assert($found instanceof PayEnhancement);

    expect(SupportManager::payEnhancements()->all()->total())->toBe(2)
        ->and($found->name)->toBe('Enhancement 1');

    $created = SupportManager::payEnhancements()->create(['name' => 'Bank Holiday', 'enhancement_factor' => '1.00']);
    assert($created instanceof PayEnhancement);
    expect($created->name)->toBe('Bank Holiday')
        ->and(SupportManager::payEnhancements()->all()->total())->toBe(3);

    $updated = SupportManager::payEnhancements()->update($created->id, ['name' => 'Renamed']);
    assert($updated instanceof PayEnhancement);
    expect($updated->name)->toBe('Renamed');

    SupportManager::payEnhancements()->destroy($created->id);
    expect(SupportManager::payEnhancements()->all()->total())->toBe(2);
});

// ── Estimated pay (flat singleton) ──────────────────────────────────────────

it('serves the flat estimated-pay singleton', function () {
    SupportManager::fake()->seed(EstimatedPayFactory::new());

    $estimate = SupportManager::estimatedPay()->show(['year' => 2026, 'month' => 6]);
    assert($estimate instanceof EstimatedPay);
    assert(is_array($estimate->deductions));

    expect($estimate->year)->toBe(2026)
        ->and($estimate->monthLabel)->toBe('June 2026')
        ->and($estimate->hours['total_cost'])->toBe(2250.0)
        ->and($estimate->enhancements)->toHaveCount(1)
        ->and($estimate->enhancements[0]['type'])->toBe('Bank Holiday')
        ->and($estimate->grandTotal['total_cost'])->toBe(2570.0)
        ->and($estimate->grossPay)->toBe(2570.0)
        ->and($estimate->deductions['income_tax'])->toBe(300.5);
});

it('serves a worked-hours-only estimate with no net-pay breakdown', function () {
    SupportManager::fake()->seed(EstimatedPayFactory::new()->withoutNetPay());

    $estimate = SupportManager::estimatedPay()->show();
    assert($estimate instanceof EstimatedPay);

    expect($estimate->grossPay)->toBeNull()
        ->and($estimate->netPay)->toBeNull()
        ->and($estimate->deductions)->toBeNull();
});

// ── Pay runs (flat paginated index, read-only) ──────────────────────────────

it('hydrates flat-paginated pay-run summaries', function () {
    SupportManager::fake()->seed(PayRunFactory::new()->count(3));

    $page = SupportManager::payRuns()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(PayRun::class)
        ->and($page->items()[0]->monthKey)->toBe('2026-01')
        ->and($page->items()[0]->status)->toBe('published')
        ->and($page->items()[0]->totalGross)->toBe(25000.0)
        ->and($page->items()[0]->totalNet)->toBe(19500.0)
        ->and($page->currentPage())->toBe(1)
        ->and($page->total())->toBe(3);
});

it('applies the partial pay-run state', function () {
    SupportManager::fake()->seed(PayRunFactory::new()->count(3)->partial());

    $page = SupportManager::payRuns()->page(1);

    expect($page->items()[0]->status)->toBe('partial')
        ->and($page->items()[0]->publishedCount)->toBe(5)
        ->and($page->total())->toBe(3);
});

// ── Automatic pay-enhancement settings (flat singleton) ─────────────────────

it('serves and updates the flat automatic-pay-enhancement settings', function () {
    SupportManager::fake()->seed(AutomaticPayEnhancementSettingsFactory::new());

    $settings = SupportManager::automaticPayEnhancements()->show();
    assert($settings instanceof AutomaticPayEnhancementSettings);

    expect($settings->bankHolidayEnhancementId)->toBe(3)
        ->and($settings->christmasEnhancementId)->toBe(4)
        ->and($settings->holidayCoverEnhancementId)->toBeNull()
        ->and($settings->sicknessCoverEnhancementId)->toBe(5);

    $updated = SupportManager::automaticPayEnhancements()->update(['bank_holiday_enhancement_id' => 9]);
    assert($updated instanceof AutomaticPayEnhancementSettings);

    expect($updated->bankHolidayEnhancementId)->toBe(9);
});

it('returns null-valued settings when none are configured', function () {
    SupportManager::fake()->seed(AutomaticPayEnhancementSettingsFactory::new()->unconfigured());

    $settings = SupportManager::automaticPayEnhancements()->show();
    assert($settings instanceof AutomaticPayEnhancementSettings);

    expect($settings->bankHolidayEnhancementId)->toBeNull();
});

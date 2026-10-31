<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\StarterDeclaration;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Resources\Payroll\StarterDeclarationResource;
use SupportManager\Sdk\Testing\Factories\StarterDeclarationFactory;

function starterDeclaration(): StarterDeclarationResource
{
    return SupportManager::starterDeclaration();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function starterDeclarationBody(array $overrides = [], bool $canUpdate = true): array
{
    return [
        'data' => [
            'ni_number' => 'AB123456C',
            'tax_form_type' => 'P45',
            'starter_declaration_circumstances' => 'A',
            'leaving_date' => '2026-06-30',
            'total_pay_to_date' => 8000.5,
            'total_tax_to_date' => 950.25,
            'final_pay_period_type' => 'monthly',
            'final_pay_period_number' => 3,
            'tax_form_url' => 'https://files.example.com/payroll-starter-declarations/1/5/p45.pdf?signature=abc',
            'national_insurance_letter' => 'A',
            'student_loan_plan' => 'plan_2',
            'postgraduate_loan' => false,
            'tax_code' => '1257L',
            'week1_month1' => true,
            'updated_at' => '2026-07-01T09:30:00.000000Z',
            ...$overrides,
        ],
        'meta' => ['can' => ['update' => $canUpdate], 'team_id' => 42],
    ];
}

/**
 * The named multipart parts of a request, as `name => contents`.
 *
 * @return array<string, mixed>
 */
function starterDeclarationParts(Request $request): array
{
    $parts = [];

    foreach ($request->data() as $part) {
        if (is_array($part) && is_string($part['name'] ?? null)) {
            $parts[$part['name']] = $part['contents'] ?? null;
        }
    }

    return $parts;
}

// ── HTTP layer ──────────────────────────────────────────────────────────────

it('shows the starter declaration with the update permission', function () {
    Http::fake(['api.test/api/v1/payroll/starter-declaration' => Http::response(starterDeclarationBody())]);
    bootTenantSession();

    $declaration = starterDeclaration()->show();

    expect($declaration)->toBeInstanceOf(StarterDeclaration::class)
        ->and($declaration->niNumber)->toBe('AB123456C')
        ->and($declaration->taxFormType)->toBe('P45')
        ->and($declaration->starterDeclarationCircumstances)->toBe('A')
        ->and($declaration->leavingDate)->toBe('2026-06-30')
        ->and($declaration->totalPayToDate)->toBe(8000.5)
        ->and($declaration->totalTaxToDate)->toBe(950.25)
        ->and($declaration->finalPayPeriodType)->toBe('monthly')
        ->and($declaration->finalPayPeriodNumber)->toBe(3)
        ->and($declaration->taxFormUrl)->toStartWith('https://files.example.com/')
        ->and($declaration->nationalInsuranceLetter)->toBe('A')
        ->and($declaration->studentLoanPlan)->toBe('plan_2')
        ->and($declaration->postgraduateLoan)->toBeFalse()
        ->and($declaration->taxCode)->toBe('1257L')
        ->and($declaration->week1Month1)->toBeTrue()
        ->and($declaration->updatedAt)->toBe('2026-07-01T09:30:00.000000Z')
        ->and($declaration->canUpdate)->toBeTrue();

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('reads unanswered fields as null and a read-only declaration as not updatable', function () {
    Http::fake(['api.test/api/v1/payroll/starter-declaration' => Http::response(starterDeclarationBody([
        'ni_number' => null,
        'tax_form_type' => null,
        'leaving_date' => null,
        'total_pay_to_date' => null,
        'final_pay_period_number' => null,
        'tax_form_url' => null,
        'updated_at' => null,
    ], canUpdate: false))]);
    bootTenantSession();

    $declaration = starterDeclaration()->show();

    expect($declaration->niNumber)->toBeNull()
        ->and($declaration->taxFormType)->toBeNull()
        ->and($declaration->leavingDate)->toBeNull()
        ->and($declaration->totalPayToDate)->toBeNull()
        ->and($declaration->finalPayPeriodNumber)->toBeNull()
        ->and($declaration->taxFormUrl)->toBeNull()
        ->and($declaration->updatedAt)->toBeNull()
        ->and($declaration->canUpdate)->toBeFalse();
});

it('surfaces a 403 for a user without a declaration', function () {
    Http::fake(['api.test/api/v1/payroll/starter-declaration' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    expect(fn () => starterDeclaration()->show())->toThrow(SupportManagerApiException::class);
});

it('saves the declaration as a JSON PUT when no form is uploaded', function () {
    Http::fake(['api.test/api/v1/payroll/starter-declaration' => Http::response(starterDeclarationBody(['tax_form_type' => 'New Starter Checklist']))]);
    bootTenantSession();

    $declaration = starterDeclaration()->update([
        'tax_form_type' => 'New Starter Checklist',
        'ni_number' => 'AB123456C',
        'starter_declaration_circumstances' => 'A',
        'national_insurance_letter' => 'A',
        'postgraduate_loan' => false,
    ]);

    expect($declaration->taxFormType)->toBe('New Starter Checklist');

    Http::assertSent(fn ($r) => $r->method() === 'PUT'
        && ! $r->isMultipart()
        && $r['tax_form_type'] === 'New Starter Checklist'
        && $r['postgraduate_loan'] === false
        && $r->hasHeader('X-Team-ID', '42'));
});

it('uploads a P45 as multipart form data spoofed to PUT', function () {
    Http::fake(['api.test/api/v1/payroll/starter-declaration' => Http::response(starterDeclarationBody())]);
    bootTenantSession();

    starterDeclaration()->update([
        'tax_form_type' => 'P45',
        'ni_number' => 'AB123456C',
        'starter_declaration_circumstances' => 'A',
        'national_insurance_letter' => 'A',
        'student_loan_plan' => null,
        'week1_month1' => true,
        'postgraduate_loan' => false,
        'total_pay_to_date' => 8000.5,
        'tax_code' => '1257L',
        'leaving_date' => '2026-06-30',
        'total_tax_to_date' => 1200.0,
        'final_pay_period_type' => 'monthly',
    ], '%PDF-1.4 p45', 'my-p45.pdf');

    Http::assertSent(function (Request $request): bool {
        $parts = starterDeclarationParts($request);
        $file = collect($request->data())->firstWhere('name', 'tax_form_file');

        return $request->method() === 'POST'
            && $request->isMultipart()
            && $parts['_method'] === 'PUT'
            && $parts['tax_form_type'] === 'P45'
            && $parts['week1_month1'] === '1'
            && $parts['postgraduate_loan'] === '0'
            && $parts['student_loan_plan'] === ''
            && (string) $parts['total_pay_to_date'] === '8000.5'
            && $parts['tax_form_file'] === '%PDF-1.4 p45'
            && ($file['filename'] ?? null) === 'my-p45.pdf'
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('maps a 422 on save to a validation exception', function () {
    Http::fake(['api.test/api/v1/payroll/starter-declaration' => Http::response([
        'message' => 'Please upload your P45 document.',
        'errors' => ['tax_form_file' => ['Please upload your P45 document.']],
    ], 422)]);
    bootTenantSession();

    try {
        starterDeclaration()->update(['tax_form_type' => 'P45']);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('tax_form_file'))->toBe('Please upload your P45 document.');
    }
});

// ── SupportManager::fake() ──────────────────────────────────────────────────

it('serves and saves the seeded declaration through the fake', function () {
    SupportManager::fake()->seed(StarterDeclarationFactory::new());

    $declaration = starterDeclaration()->show();
    assert($declaration instanceof StarterDeclaration);

    $saved = starterDeclaration()->update([
        'tax_form_type' => 'New Starter Checklist',
        'ni_number' => 'AB123456C',
        'starter_declaration_circumstances' => 'A',
        'national_insurance_letter' => 'A',
        'student_loan_plan' => 'plan_1',
        'postgraduate_loan' => true,
    ]);
    assert($saved instanceof StarterDeclaration);

    expect($declaration->niNumber)->toBe('AB123456C')
        ->and($declaration->taxFormType)->toBe('New Starter Checklist')
        ->and($declaration->taxFormUrl)->toBeNull()
        ->and($declaration->canUpdate)->toBeTrue()
        ->and($saved->studentLoanPlan)->toBe('plan_1')
        ->and($saved->postgraduateLoan)->toBeTrue()
        ->and(starterDeclaration()->show()?->studentLoanPlan)->toBe('plan_1');
});

it('stores an uploaded P45 through the fake', function () {
    SupportManager::fake()->seed(StarterDeclarationFactory::new());

    $saved = starterDeclaration()->update([
        'tax_form_type' => 'P45',
        'ni_number' => 'AB123456C',
        'starter_declaration_circumstances' => 'A',
        'national_insurance_letter' => 'A',
        'tax_code' => '1257L',
        'leaving_date' => '2026-06-30',
        'total_tax_to_date' => 1200.0,
        'final_pay_period_type' => 'monthly',
        'student_loan_plan' => null,
        'week1_month1' => true,
        'total_pay_to_date' => 8000.5,
        'final_pay_period_number' => 3,
    ], '%PDF-1.4 p45', 'my-p45.pdf');
    assert($saved instanceof StarterDeclaration);

    expect($saved->taxFormType)->toBe('P45')
        ->and($saved->studentLoanPlan)->toBeNull()
        ->and($saved->week1Month1)->toBeTrue()
        ->and($saved->totalPayToDate)->toBe(8000.5)
        ->and($saved->finalPayPeriodNumber)->toBe(3)
        ->and($saved->taxFormUrl)->toContain('my-p45.pdf');
});

it('serves a P45 declaration and refuses saves when read-only', function () {
    SupportManager::fake()->seed(StarterDeclarationFactory::new()->withP45()->readOnly());

    $declaration = starterDeclaration()->show();

    expect($declaration?->taxFormType)->toBe('P45')
        ->and($declaration?->leavingDate)->toBe('2026-06-30')
        ->and($declaration?->taxFormUrl)->not->toBeNull()
        ->and($declaration?->canUpdate)->toBeFalse()
        ->and(fn () => starterDeclaration()->update([
            'tax_form_type' => 'New Starter Checklist',
            'ni_number' => 'AB123456C',
            'starter_declaration_circumstances' => 'A',
            'national_insurance_letter' => 'A',
        ]))->toThrow(SupportManagerApiException::class);
});

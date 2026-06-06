<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\PayEnhancement;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Payroll\PayEnhancementsResource;

function payrollPayEnhancements(): PayEnhancementsResource
{
    return app(PayEnhancementsResource::class);
}

function payrollPayEnhancementPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'pay-enhancements',
            'id' => (string) $page,
            'attributes' => [
                'name' => "Enhancement {$page}",
                'description' => 'Weekend rate',
                'enhancement_factor' => '0.50',
                'enhancement_factor_label' => 'Time and a half',
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/payroll/pay-enhancements?page='.($page + 1) : null],
    ];
}

it('encodes pagination as query params', function () {
    Http::fake(['api.test/api/v1/payroll/pay-enhancements*' => Http::response(payrollPayEnhancementPage(1, 1))]);
    bootTenantSession();

    payrollPayEnhancements()->query()->perPage(50)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'per_page=50')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates pay enhancement DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/payroll/pay-enhancements*' => Http::response(payrollPayEnhancementPage(1, 2))]);
    bootTenantSession();

    $page = payrollPayEnhancements()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(PayEnhancement::class)
        ->and($page->items()[0]->name)->toBe('Enhancement 1')
        ->and($page->items()[0]->enhancementFactor)->toBe('0.50')
        ->and($page->items()[0]->enhancementFactorLabel)->toBe('Time and a half')
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a pay enhancement by id', function () {
    Http::fake([
        'api.test/api/v1/payroll/pay-enhancements/5' => Http::response([
            'data' => ['type' => 'pay-enhancements', 'id' => '5', 'attributes' => ['name' => 'Sunday', 'enhancement_factor' => '1.00']],
        ]),
    ]);
    bootTenantSession();

    expect(payrollPayEnhancements()->find(5)->name)->toBe('Sunday');
});

it('returns null when finding a missing pay enhancement (404)', function () {
    Http::fake(['api.test/api/v1/payroll/pay-enhancements/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(payrollPayEnhancements()->find(999))->toBeNull();
});

it('creates a pay enhancement', function () {
    Http::fake(['api.test/api/v1/payroll/pay-enhancements' => Http::response([
        'data' => ['type' => 'pay-enhancements', 'id' => '8', 'attributes' => ['name' => 'Bank Holiday', 'enhancement_factor' => '1.00']],
    ], 201)]);
    bootTenantSession();

    $created = payrollPayEnhancements()->create(['name' => 'Bank Holiday', 'enhancement_factor' => '1.00']);

    expect($created)->toBeInstanceOf(PayEnhancement::class)
        ->and($created->name)->toBe('Bank Holiday');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['name'] === 'Bank Holiday');
});

it('maps a 422 on create to a validation exception', function () {
    Http::fake(['api.test/api/v1/payroll/pay-enhancements' => Http::response([
        'message' => 'The pay enhancement name is required.',
        'errors' => ['name' => ['The pay enhancement name is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        payrollPayEnhancements()->create(['enhancement_factor' => '0.50']);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('name'))->toBe('The pay enhancement name is required.');
    }
});

it('updates a pay enhancement', function () {
    Http::fake(['api.test/api/v1/payroll/pay-enhancements/8' => Http::response([
        'data' => ['type' => 'pay-enhancements', 'id' => '8', 'attributes' => ['name' => 'Renamed', 'enhancement_factor' => '0.50']],
    ])]);
    bootTenantSession();

    $updated = payrollPayEnhancements()->update(8, ['name' => 'Renamed']);

    expect($updated->name)->toBe('Renamed');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['name'] === 'Renamed');
});

it('deletes a pay enhancement', function () {
    Http::fake(['api.test/api/v1/payroll/pay-enhancements/8' => Http::response(['message' => 'Pay enhancement deleted successfully.'])]);
    bootTenantSession();

    payrollPayEnhancements()->destroy(8);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'payroll/pay-enhancements/8'));
});

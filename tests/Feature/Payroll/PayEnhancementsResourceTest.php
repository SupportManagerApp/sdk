<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\PayEnhancement;

// PayEnhancementController::index returns every enhancement, ordered by name,
// as a plain (non-paginated) resource collection with only meta.team_id.
function payrollPayEnhancementList(int $count): array
{
    return [
        'data' => array_map(static fn (int $n): array => [
            'type' => 'pay-enhancements',
            'id' => (string) $n,
            'attributes' => [
                'name' => "Enhancement {$n}",
                'description' => 'Weekend rate',
                'enhancement_factor' => '0.50',
                'enhancement_factor_label' => 'Time and a half',
                'created_at' => '2026-01-01T09:00:00.000000Z',
                'updated_at' => '2026-01-01T09:00:00.000000Z',
            ],
        ], range(1, $count)),
        'meta' => ['team_id' => 42],
    ];
}

it('hydrates pay enhancement DTOs from the unpaginated list', function () {
    fakeApi(['payroll/pay-enhancements*' => Http::response(payrollPayEnhancementList(2))]);

    $page = sdk()->payEnhancements()->query()->get();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(PayEnhancement::class)
        ->and($page->items()[0]->name)->toBe('Enhancement 1')
        ->and($page->items()[0]->enhancementFactor)->toBe('0.50')
        ->and($page->items()[0]->enhancementFactorLabel)->toBe('Time and a half')
        ->and($page->hasMorePages())->toBeFalse();
});

it('finds a pay enhancement by id', function () {
    fakeApi([
        'payroll/pay-enhancements/5' => [
            'data' => ['type' => 'pay-enhancements', 'id' => '5', 'attributes' => ['name' => 'Sunday', 'enhancement_factor' => '1']],
        ],
    ]);

    expect(sdk()->payEnhancements()->find(5)->name)->toBe('Sunday');
});

it('returns null when finding a missing pay enhancement (404)', function () {
    fakeApi(['payroll/pay-enhancements/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->payEnhancements()->find(999))->toBeNull();
});

it('creates a pay enhancement', function () {
    fakeApi(['payroll/pay-enhancements' => Http::response([
        'data' => ['type' => 'pay-enhancements', 'id' => '8', 'attributes' => ['name' => 'Bank Holiday', 'enhancement_factor' => '1']],
    ], 201)]);

    $created = sdk()->payEnhancements()->create(['name' => 'Bank Holiday', 'enhancement_factor' => '1']);

    expect($created)->toBeInstanceOf(PayEnhancement::class)
        ->and($created->name)->toBe('Bank Holiday');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['name'] === 'Bank Holiday');
});

it('updates a pay enhancement', function () {
    fakeApi(['payroll/pay-enhancements/8' => [
        'data' => ['type' => 'pay-enhancements', 'id' => '8', 'attributes' => ['name' => 'Renamed', 'enhancement_factor' => '0.50']],
    ]]);

    $updated = sdk()->payEnhancements()->update(8, ['name' => 'Renamed']);

    expect($updated->name)->toBe('Renamed');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['name'] === 'Renamed');
});

it('deletes a pay enhancement', function () {
    fakeApi(['payroll/pay-enhancements/8' => ['message' => 'Pay enhancement deleted successfully.']]);

    sdk()->payEnhancements()->destroy(8);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'payroll/pay-enhancements/8'));
});

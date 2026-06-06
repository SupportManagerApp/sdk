<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Compliance\DriversLicenceCheck;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Compliance\DriversLicenceChecksResource;

function complianceDriversLicenceChecks(): DriversLicenceChecksResource
{
    return app(DriversLicenceChecksResource::class);
}

function complianceDriversLicenceCheckPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'drivers-licence-checks',
            'id' => (string) $page,
            'attributes' => [
                'team_id' => 42,
                'user_id' => $page,
                'drivers_licence_number' => "DL-{$page}",
                'check_code' => "CHK-{$page}",
                'declaration_accepted' => true,
                'check_result' => 'clean',
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/compliance/drivers-licence-checks?page='.($page + 1) : null],
    ];
}

it('encodes filter and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/compliance/drivers-licence-checks*' => Http::response(complianceDriversLicenceCheckPage(1, 1))]);
    bootTenantSession();

    complianceDriversLicenceChecks()->query()
        ->filter('user_id', 7)
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[user_id]=7')
            && str_contains($url, 'per_page=15')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/compliance/drivers-licence-checks*' => Http::response(complianceDriversLicenceCheckPage(1, 3))]);
    bootTenantSession();

    $page = complianceDriversLicenceChecks()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($page->items()[0]->driversLicenceNumber)->toBe('DL-1')
        ->and($page->items()[0]->declarationAccepted)->toBeTrue()
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single drivers licence check by id', function () {
    Http::fake([
        'api.test/api/v1/compliance/drivers-licence-checks/7' => Http::response([
            'data' => [
                'type' => 'drivers-licence-checks',
                'id' => '7',
                'attributes' => [
                    'user_id' => 3,
                    'drivers_licence_number' => 'DL-7',
                    'declaration_accepted' => false,
                    'user' => ['id' => 3, 'name' => 'Ada'],
                ],
            ],
        ]),
    ]);
    bootTenantSession();

    $check = complianceDriversLicenceChecks()->find(7);

    expect($check)->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($check->id)->toBe('7')
        ->and($check->declarationAccepted)->toBeFalse()
        ->and($check->userName)->toBe('Ada');
});

it('returns null when finding a missing drivers licence check (404)', function () {
    Http::fake(['api.test/api/v1/compliance/drivers-licence-checks/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(complianceDriversLicenceChecks()->find(999))->toBeNull();
});

it('maps a 422 on the collection path to a validation exception', function () {
    Http::fake(['api.test/api/v1/compliance/drivers-licence-checks*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter' => ['The user_id filter is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        complianceDriversLicenceChecks()->query()->filter('user_id', 'nope')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('filter'))->toBe('The user_id filter is invalid.');
    }
});

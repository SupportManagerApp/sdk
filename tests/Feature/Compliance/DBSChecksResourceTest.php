<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Compliance\DBSCheck;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Compliance\DBSChecksResource;

function complianceDbsChecks(): DBSChecksResource
{
    return app(DBSChecksResource::class);
}

function complianceDbsCheckPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'dbs-checks',
            'id' => (string) $page,
            'attributes' => [
                'team_id' => 42,
                'user_id' => $page,
                'date_of_issue' => '2026-01-01T00:00:00Z',
                'certificate_number' => "CERT-{$page}",
                'check_result' => 'clear',
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/compliance/dbs-checks?page='.($page + 1) : null],
    ];
}

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks*' => Http::response(complianceDbsCheckPage(1, 1))]);
    bootTenantSession();

    complianceDbsChecks()->query()
        ->filter('user_id', 7)
        ->sort('-date_of_issue')
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[user_id]=7')
            && str_contains($url, 'sort=-date_of_issue')
            && str_contains($url, 'per_page=15')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks*' => Http::response(complianceDbsCheckPage(1, 3))]);
    bootTenantSession();

    $page = complianceDbsChecks()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(DBSCheck::class)
        ->and($page->items()[0]->certificateNumber)->toBe('CERT-1')
        ->and($page->items()[0]->checkResult)->toBe('clear')
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(3)
        ->and($page->total())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single DBS check by id', function () {
    Http::fake([
        'api.test/api/v1/compliance/dbs-checks/7' => Http::response([
            'data' => [
                'type' => 'dbs-checks',
                'id' => '7',
                'attributes' => [
                    'user_id' => 3,
                    'certificate_number' => 'CERT-7',
                    'user' => ['id' => 3, 'name' => 'Ada'],
                ],
            ],
        ]),
    ]);
    bootTenantSession();

    $check = complianceDbsChecks()->find(7);

    expect($check)->toBeInstanceOf(DBSCheck::class)
        ->and($check->id)->toBe('7')
        ->and($check->userId)->toBe(3)
        ->and($check->userName)->toBe('Ada');
});

it('returns null when finding a missing DBS check (404)', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(complianceDbsChecks()->find(999))->toBeNull();
});

it('maps a 422 on the collection path to a validation exception', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter' => ['The user_id filter is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        complianceDbsChecks()->query()->filter('user_id', 'nope')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('filter'))->toBe('The user_id filter is invalid.');
    }
});

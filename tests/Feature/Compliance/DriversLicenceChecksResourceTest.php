<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Compliance\DriversLicenceCheck;

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
    fakeApi(['compliance/drivers-licence-checks*' => Http::response(complianceDriversLicenceCheckPage(1, 1))]);

    sdk()->driversLicenceChecks()->query()
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
    fakeApi(['compliance/drivers-licence-checks*' => Http::response(complianceDriversLicenceCheckPage(1, 3))]);

    $page = sdk()->driversLicenceChecks()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($page->items()[0]->driversLicenceNumber)->toBe('DL-1')
        ->and($page->items()[0]->declarationAccepted)->toBeTrue()
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single drivers licence check by id', function () {
    fakeApi([
        'compliance/drivers-licence-checks/7' => [
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
        ],
    ]);

    $check = sdk()->driversLicenceChecks()->find(7);

    expect($check)->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($check->id)->toBe('7')
        ->and($check->declarationAccepted)->toBeFalse()
        ->and($check->userName)->toBe('Ada');
});

it('returns null when finding a missing drivers licence check (404)', function () {
    fakeApi(['compliance/drivers-licence-checks/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->driversLicenceChecks()->find(999))->toBeNull();
});

it('hydrates the meta.can permission flags', function () {
    fakeApi(['compliance/drivers-licence-checks/7' => [
        'data' => [
            'type' => 'drivers-licence-checks',
            'id' => '7',
            'attributes' => ['check_result_document' => 'result.pdf'],
            'meta' => ['can' => ['update' => false, 'download' => true]],
        ],
    ]]);

    $check = sdk()->driversLicenceChecks()->find(7);

    expect($check->checkResultDocument)->toBe('result.pdf')
        ->and($check->canUpdate)->toBeFalse()
        ->and($check->canDownload)->toBeTrue();
});

it('submits driving licence details as JSON', function () {
    fakeApi(['compliance/drivers-licence-checks' => Http::response([
        'data' => [
            'type' => 'drivers-licence-checks',
            'id' => '9',
            'attributes' => ['drivers_licence_number' => 'HARBO801234AB9CD', 'check_code' => 'Xy7Kp2Qm', 'declaration_accepted' => true],
        ],
    ], 201)]);

    $check = sdk()->driversLicenceChecks()->submit('HARBO801234AB9CD', 'Xy7Kp2Qm', declarationAccepted: true);

    expect($check)->toBeInstanceOf(DriversLicenceCheck::class)
        ->and($check->driversLicenceNumber)->toBe('HARBO801234AB9CD')
        ->and($check->declarationAccepted)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/compliance/drivers-licence-checks')
        && $request->hasHeader('X-Team-ID', '42')
        && $request['drivers_licence_number'] === 'HARBO801234AB9CD'
        && $request['check_code'] === 'Xy7Kp2Qm'
        && $request['declaration_accepted'] === true);
});

it('returns the raw check result document download payload response', function () {
    fakeApi(['compliance/drivers-licence-checks/7/check-result-document' => [
        'data' => [
            'download_url' => 'https://s3.test/signed/result.pdf',
            'filename' => 'result.pdf',
            'expires_at' => '2026-06-06T10:05:00Z',
        ],
    ]]);

    $response = sdk()->driversLicenceChecks()->downloadCheckResultDocument(7);

    expect($response->url)->toBe('https://s3.test/signed/result.pdf')
        ->and($response->filename)->toBe('result.pdf');
});

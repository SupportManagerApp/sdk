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

it('maps a 422 (invalid team context) on the collection path to a validation exception', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks*' => Http::response([
        'message' => 'Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.',
    ], 422)]);
    bootTenantSession();

    try {
        complianceDbsChecks()->query()->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->getMessage())->toBe('Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.')
            ->and($e->errors)->toBe([]);
    }
});

it('hydrates the meta.can permission flags', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks/7' => Http::response([
        'data' => [
            'type' => 'dbs-checks',
            'id' => '7',
            'attributes' => ['certificate' => 'certificate.pdf'],
            'meta' => ['can' => ['update' => true, 'download' => true]],
        ],
    ])]);
    bootTenantSession();

    $check = complianceDbsChecks()->find(7);

    expect($check->certificate)->toBe('certificate.pdf')
        ->and($check->canUpdate)->toBeTrue()
        ->and($check->canDownload)->toBeTrue();
});

it('defaults the permission flags to false when meta is absent', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks/7' => Http::response([
        'data' => ['type' => 'dbs-checks', 'id' => '7', 'attributes' => []],
    ])]);
    bootTenantSession();

    $check = complianceDbsChecks()->find(7);

    expect($check->canUpdate)->toBeFalse()
        ->and($check->canDownload)->toBeFalse();
});

it('submits a DBS check as multipart with the certificate file', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks' => Http::response([
        'data' => [
            'type' => 'dbs-checks',
            'id' => '9',
            'attributes' => ['certificate_number' => '001234567890', 'certificate' => 'dbs.pdf'],
            'meta' => ['can' => ['update' => true, 'download' => true]],
        ],
        'meta' => ['team_id' => 42],
    ], 201)]);
    bootTenantSession();

    $check = complianceDbsChecks()->submit('2026-03-14', '001234567890', 'PDF-BYTES', 'dbs.pdf');

    expect($check)->toBeInstanceOf(DBSCheck::class)
        ->and($check->id)->toBe('9')
        ->and($check->certificate)->toBe('dbs.pdf');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/compliance/dbs-checks')
        && $request->hasHeader('X-Team-ID', '42')
        && str_starts_with($request->header('Content-Type')[0], 'multipart/form-data; boundary=')
        && $request->hasFile('certificate', 'PDF-BYTES', 'dbs.pdf')
        && str_contains($request->body(), 'name="date_of_issue"')
        && str_contains($request->body(), '2026-03-14')
        && str_contains($request->body(), 'name="certificate_number"'));
});

it('submits a DBS check without a file to keep the stored certificate', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks' => Http::response([
        'data' => ['type' => 'dbs-checks', 'id' => '9', 'attributes' => ['certificate' => 'dbs.pdf']],
    ])]);
    bootTenantSession();

    complianceDbsChecks()->submit('2026-03-14', '001234567890');

    Http::assertSent(fn ($request) => str_starts_with($request->header('Content-Type')[0], 'multipart/form-data; boundary=')
        && ! str_contains($request->body(), 'name="certificate"'));
});

it('maps a 422 on submit to a validation exception', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks' => Http::response([
        'message' => 'Please upload a copy of your DBS certificate.',
        'errors' => ['certificate' => ['Please upload a copy of your DBS certificate.']],
    ], 422)]);
    bootTenantSession();

    try {
        complianceDbsChecks()->submit('2026-03-14', '001234567890');
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('certificate'))->toBe('Please upload a copy of your DBS certificate.');
    }
});

it('returns the raw certificate download payload response', function () {
    Http::fake(['api.test/api/v1/compliance/dbs-checks/7/certificate' => Http::response([
        'data' => [
            'download_url' => 'https://s3.test/signed/dbs.pdf',
            'filename' => 'certificate.pdf',
            'expires_at' => '2026-06-06T10:05:00Z',
        ],
    ])]);
    bootTenantSession();

    $response = complianceDbsChecks()->downloadCertificate(7);

    expect($response->json('data.download_url'))->toBe('https://s3.test/signed/dbs.pdf')
        ->and($response->json('data.filename'))->toBe('certificate.pdf');
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->hasHeader('X-Team-ID', '42'));
});

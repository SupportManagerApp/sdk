<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Compliance\RightToWork;
use SupportManager\Sdk\Enums\Compliance\PrimaryDocumentType;

function complianceRightToWorkPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'right-to-work',
            'id' => (string) $page,
            'attributes' => [
                'team_id' => 42,
                'user_id' => $page,
                'primary_document_type' => 'passport',
                'share_code' => "SHARE-{$page}",
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/compliance/right-to-work?page='.($page + 1) : null],
    ];
}

it('encodes filter and pagination as Spatie-style query params', function () {
    fakeApi(['compliance/right-to-work*' => Http::response(complianceRightToWorkPage(1, 1))]);

    sdk()->rightToWork()->query()
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
    fakeApi(['compliance/right-to-work*' => Http::response(complianceRightToWorkPage(1, 2))]);

    $page = sdk()->rightToWork()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(RightToWork::class)
        ->and($page->items()[0]->primaryDocumentType)->toBe('passport')
        ->and($page->items()[0]->shareCode)->toBe('SHARE-1')
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(2)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single right to work record by id', function () {
    fakeApi([
        'compliance/right-to-work/7' => [
            'data' => [
                'type' => 'right-to-work',
                'id' => '7',
                'attributes' => [
                    'user_id' => 3,
                    'primary_document_type' => 'brp',
                    'user' => ['id' => 3, 'name' => 'Ada'],
                ],
            ],
        ],
    ]);

    $record = sdk()->rightToWork()->find(7);

    expect($record)->toBeInstanceOf(RightToWork::class)
        ->and($record->id)->toBe('7')
        ->and($record->primaryDocumentType)->toBe('brp')
        ->and($record->userName)->toBe('Ada');
});

it('returns null when finding a missing right to work record (404)', function () {
    fakeApi(['compliance/right-to-work/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->rightToWork()->find(999))->toBeNull();
});

it('hydrates the meta.can permission flags', function () {
    fakeApi(['compliance/right-to-work/7' => [
        'data' => [
            'type' => 'right-to-work',
            'id' => '7',
            'attributes' => ['primary_document' => 'primary.pdf', 'supporting_document' => null],
            'meta' => ['can' => ['update' => true, 'download' => true]],
        ],
    ]]);

    $record = sdk()->rightToWork()->find(7);

    expect($record->primaryDocument)->toBe('primary.pdf')
        ->and($record->supportingDocument)->toBeNull()
        ->and($record->canUpdate)->toBeTrue()
        ->and($record->canDownload)->toBeTrue();
});

it('submits a right to work record as multipart with both documents and the typed enum', function () {
    fakeApi(['compliance/right-to-work' => Http::response([
        'data' => [
            'type' => 'right-to-work',
            'id' => '9',
            'attributes' => ['primary_document_type' => 'UK Birth or Adoption Certificate', 'primary_document' => 'birth.pdf', 'supporting_document' => 'ni.pdf'],
        ],
    ], 201)]);

    $record = sdk()->rightToWork()->submit(
        PrimaryDocumentType::UkBirthOrAdoptionCertificate,
        primaryDocument: 'BIRTH-BYTES',
        primaryDocumentFilename: 'birth.pdf',
        supportingDocument: 'NI-BYTES',
        supportingDocumentFilename: 'ni.pdf',
    );

    expect($record)->toBeInstanceOf(RightToWork::class)
        ->and($record->supportingDocument)->toBe('ni.pdf');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/compliance/right-to-work')
        && $request->hasHeader('X-Team-ID', '42')
        && str_starts_with($request->header('Content-Type')[0], 'multipart/form-data; boundary=')
        && $request->hasFile('primary_document', 'BIRTH-BYTES', 'birth.pdf')
        && $request->hasFile('supporting_document', 'NI-BYTES', 'ni.pdf')
        && str_contains($request->body(), 'UK Birth or Adoption Certificate')
        && ! str_contains($request->body(), 'name="share_code"'));
});

it('submits a share code without files', function () {
    fakeApi(['compliance/right-to-work' => [
        'data' => ['type' => 'right-to-work', 'id' => '9', 'attributes' => ['share_code' => 'W4X 7YZ 9AB']],
    ]]);

    sdk()->rightToWork()->submit('Immigration Share Code', shareCode: 'W4X 7YZ 9AB');

    Http::assertSent(fn ($request) => str_contains($request->body(), 'name="share_code"')
        && str_contains($request->body(), 'W4X 7YZ 9AB')
        && ! str_contains($request->body(), 'filename='));
});

it('returns the raw primary and supporting document download payload responses', function () {
    fakeApi([
        'compliance/right-to-work/7/primary-document' => [
            'data' => ['download_url' => 'https://s3.test/signed/primary.pdf', 'filename' => 'primary.pdf', 'expires_at' => '2026-06-06T10:05:00Z'],
        ],
        'compliance/right-to-work/7/supporting-document' => [
            'data' => ['download_url' => 'https://s3.test/signed/supporting.pdf', 'filename' => 'supporting.pdf', 'expires_at' => '2026-06-06T10:05:00Z'],
        ],
    ]);

    expect(sdk()->rightToWork()->downloadPrimaryDocument(7)->url)->toBe('https://s3.test/signed/primary.pdf')
        ->and(sdk()->rightToWork()->downloadSupportingDocument(7)->filename)->toBe('supporting.pdf');
});

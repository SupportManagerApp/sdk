<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Documents\Document;
use SupportManager\Sdk\Enums\Documents\DocumentScope;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Documents\DocumentsResource;

function documentsResource(): DocumentsResource
{
    return app(DocumentsResource::class);
}

function documentsPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'documents',
            'id' => (string) $page,
            'attributes' => [
                'name' => "Document {$page}",
                'scope' => 'personal',
                'team_id' => 42,
                'user_id' => 9,
                'user' => ['id' => 9, 'name' => 'Ada'],
                'document_folder_id' => 3,
                'document_folder' => ['id' => 3, 'name' => 'Contracts'],
                'uploaded_by' => ['id' => 1, 'name' => 'Grace'],
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/documents/documents?page='.($page + 1) : null],
    ];
}

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/documents/documents*' => Http::response(documentsPage(1, 1))]);
    bootTenantSession();

    documentsResource()->query()
        ->filter('name', 'contract')
        ->sort('-created_at')
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[name]=contract')
            && str_contains($url, 'sort=-created_at')
            && str_contains($url, 'per_page=15')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates document DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/documents/documents*' => Http::response(documentsPage(1, 3))]);
    bootTenantSession();

    $page = documentsResource()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(Document::class)
        ->and($page->items()[0]->name)->toBe('Document 1')
        ->and($page->items()[0]->userName)->toBe('Ada')
        ->and($page->items()[0]->documentFolderName)->toBe('Contracts')
        ->and($page->items()[0]->uploadedByName)->toBe('Grace')
        ->and($page->items()[0]->scope)->toBe(DocumentScope::Personal)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single document by id', function () {
    Http::fake([
        'api.test/api/v1/documents/documents/7' => Http::response([
            'data' => ['type' => 'documents', 'id' => '7', 'attributes' => ['name' => 'NDA', 'scope' => 'team', 'user_id' => null]],
        ]),
    ]);
    bootTenantSession();

    $document = documentsResource()->find(7);

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document->id)->toBe('7')
        ->and($document->name)->toBe('NDA')
        ->and($document->scope)->toBe(DocumentScope::Team)
        ->and($document->userId)->toBeNull();
});

it('returns null when finding a missing document (404)', function () {
    Http::fake(['api.test/api/v1/documents/documents/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(documentsResource()->find(999))->toBeNull();
});

it('returns the raw download payload response', function () {
    Http::fake([
        'api.test/api/v1/documents/documents/7/download' => Http::response([
            'data' => [
                'download_url' => 'https://s3.test/signed/7.pdf',
                'filename' => 'NDA',
                'expires_at' => '2026-06-06T10:05:00Z',
            ],
        ]),
    ]);
    bootTenantSession();

    $response = documentsResource()->download(7);

    expect($response->url)->toBe('https://s3.test/signed/7.pdf')
        ->and($response->filename)->toBe('NDA');
});

it('maps a 422 on the documents path to a validation exception', function () {
    Http::fake(['api.test/api/v1/documents/documents*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter' => ['The name filter is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        documentsResource()->query()->filter('name', '')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('filter'))->toBe('The name filter is invalid.');
    }
});

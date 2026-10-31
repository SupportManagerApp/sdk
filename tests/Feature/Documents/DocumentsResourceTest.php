<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Documents\Document;
use SupportManager\Sdk\Enums\Documents\DocumentScope;

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
    fakeApi(['documents/documents*' => Http::response(documentsPage(1, 1))]);

    sdk()->documents()->query()
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
    fakeApi(['documents/documents*' => Http::response(documentsPage(1, 3))]);

    $page = sdk()->documents()->query()->get();

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
    fakeApi([
        'documents/documents/7' => [
            'data' => ['type' => 'documents', 'id' => '7', 'attributes' => ['name' => 'NDA', 'scope' => 'team', 'user_id' => null]],
        ],
    ]);

    $document = sdk()->documents()->find(7);

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document->id)->toBe('7')
        ->and($document->name)->toBe('NDA')
        ->and($document->scope)->toBe(DocumentScope::Team)
        ->and($document->userId)->toBeNull();
});

it('returns null when finding a missing document (404)', function () {
    fakeApi(['documents/documents/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->documents()->find(999))->toBeNull();
});

it('returns the raw download payload response', function () {
    fakeApi([
        'documents/documents/7/download' => [
            'data' => [
                'download_url' => 'https://s3.test/signed/7.pdf',
                'filename' => 'NDA',
                'expires_at' => '2026-06-06T10:05:00Z',
            ],
        ],
    ]);

    $response = sdk()->documents()->download(7);

    expect($response->url)->toBe('https://s3.test/signed/7.pdf')
        ->and($response->filename)->toBe('NDA');
});

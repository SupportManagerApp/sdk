<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Documents\DocumentFolder;
use SupportManager\Sdk\Enums\Documents\DocumentScope;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Documents\DocumentFoldersResource;

function documentFoldersResource(): DocumentFoldersResource
{
    return app(DocumentFoldersResource::class);
}

function documentFolderPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'document-folders',
            'id' => (string) $page,
            'attributes' => [
                'name' => "Folder {$page}",
                'description' => 'Team files',
                'team_id' => 42,
                'parent_id' => null,
                'documents_count' => 4,
                'children_count' => 2,
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/documents/document-folders?page='.($page + 1) : null],
    ];
}

it('encodes filter and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/documents/document-folders*' => Http::response(documentFolderPage(1, 1))]);
    bootTenantSession();

    documentFoldersResource()->query()->filter('parent_id', 3)->perPage(25)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[parent_id]=3')
            && str_contains($url, 'per_page=25')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates folder DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/documents/document-folders*' => Http::response(documentFolderPage(1, 2))]);
    bootTenantSession();

    $page = documentFoldersResource()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(DocumentFolder::class)
        ->and($page->items()[0]->name)->toBe('Folder 1')
        ->and($page->items()[0]->parentId)->toBeNull()
        ->and($page->items()[0]->documentsCount)->toBe(4)
        ->and($page->items()[0]->childrenCount)->toBe(2)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single folder by id', function () {
    Http::fake([
        'api.test/api/v1/documents/document-folders/5' => Http::response([
            'data' => ['type' => 'document-folders', 'id' => '5', 'attributes' => ['name' => 'Root', 'parent_id' => null]],
        ]),
    ]);
    bootTenantSession();

    $folder = documentFoldersResource()->find(5);

    expect($folder)->toBeInstanceOf(DocumentFolder::class)
        ->and($folder->id)->toBe('5')
        ->and($folder->name)->toBe('Root');
});

it('returns null when finding a missing folder (404)', function () {
    Http::fake(['api.test/api/v1/documents/document-folders/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(documentFoldersResource()->find(999))->toBeNull();
});

it('finds a folder with its scope, owner and breadcrumb ancestors', function () {
    Http::fake([
        'api.test/api/v1/documents/document-folders/7' => Http::response([
            'data' => [
                'type' => 'document-folders',
                'id' => '7',
                'attributes' => [
                    'team_id' => 42,
                    'name' => '2026',
                    'description' => null,
                    'scope' => 'personal',
                    'user_id' => 5,
                    'parent_id' => 3,
                    'ancestors' => [
                        ['id' => 1, 'name' => 'HR'],
                        ['id' => 3, 'name' => 'Contracts'],
                    ],
                    'documents_count' => 4,
                    'children_count' => 0,
                ],
            ],
            'meta' => ['team_id' => 42],
        ]),
    ]);
    bootTenantSession();

    $folder = documentFoldersResource()->find(7);

    expect($folder->scope)->toBe(DocumentScope::Personal)
        ->and($folder->userId)->toBe(5)
        ->and($folder->parentId)->toBe(3)
        ->and($folder->ancestors)->toBe([
            ['id' => 1, 'name' => 'HR'],
            ['id' => 3, 'name' => 'Contracts'],
        ]);
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/api/v1/documents/document-folders/7')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('decodes listed folders without ancestors and tolerates an unknown scope', function () {
    Http::fake(['api.test/api/v1/documents/document-folders*' => Http::response([
        'data' => [['type' => 'document-folders', 'id' => '1', 'attributes' => ['name' => 'Shared', 'scope' => 'archive']]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
    ])]);
    bootTenantSession();

    $folder = documentFoldersResource()->query()->filter('scope', 'team')->get()->items()[0];

    expect($folder->scope)->toBeNull()
        ->and($folder->ancestors)->toBe([]);
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'filter[scope]=team'));
});

it('throws on a 403 for a folder the user may not browse', function () {
    Http::fake(['api.test/api/v1/documents/document-folders/9' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    documentFoldersResource()->find(9);
})->throws(SupportManagerApiException::class);

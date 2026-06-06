<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Compliance\RightToWork;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Compliance\RightToWorkResource;

function complianceRightToWork(): RightToWorkResource
{
    return app(RightToWorkResource::class);
}

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
    Http::fake(['api.test/api/v1/compliance/right-to-work*' => Http::response(complianceRightToWorkPage(1, 1))]);
    bootTenantSession();

    complianceRightToWork()->query()
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
    Http::fake(['api.test/api/v1/compliance/right-to-work*' => Http::response(complianceRightToWorkPage(1, 2))]);
    bootTenantSession();

    $page = complianceRightToWork()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(RightToWork::class)
        ->and($page->items()[0]->primaryDocumentType)->toBe('passport')
        ->and($page->items()[0]->shareCode)->toBe('SHARE-1')
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(2)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single right to work record by id', function () {
    Http::fake([
        'api.test/api/v1/compliance/right-to-work/7' => Http::response([
            'data' => [
                'type' => 'right-to-work',
                'id' => '7',
                'attributes' => [
                    'user_id' => 3,
                    'primary_document_type' => 'brp',
                    'user' => ['id' => 3, 'name' => 'Ada'],
                ],
            ],
        ]),
    ]);
    bootTenantSession();

    $record = complianceRightToWork()->find(7);

    expect($record)->toBeInstanceOf(RightToWork::class)
        ->and($record->id)->toBe('7')
        ->and($record->primaryDocumentType)->toBe('brp')
        ->and($record->userName)->toBe('Ada');
});

it('returns null when finding a missing right to work record (404)', function () {
    Http::fake(['api.test/api/v1/compliance/right-to-work/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(complianceRightToWork()->find(999))->toBeNull();
});

it('maps a 422 on the collection path to a validation exception', function () {
    Http::fake(['api.test/api/v1/compliance/right-to-work*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter' => ['The user_id filter is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        complianceRightToWork()->query()->filter('user_id', 'nope')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('filter'))->toBe('The user_id filter is invalid.');
    }
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Documents\Policy;
use SupportManager\Sdk\Enums\Documents\PolicyCategory;

function policyPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'policies',
            'id' => (string) $page,
            'attributes' => [
                'title' => "Policy {$page}",
                'slug' => "policy-{$page}",
                'content_html' => '<p>Policy content here...</p>',
                'acknowledged_at' => '2026-02-01T09:00:00.000000Z',
                'category' => 'health_and_safety',
                'category_label' => 'Health & Safety',
                'version' => '2.1',
                'effective_date' => '2024-01-01',
                'review_date' => '2025-01-01',
                'is_active' => true,
                'team_id' => 42,
                'created_by' => ['id' => 1, 'name' => 'Grace'],
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/documents/policies?page='.($page + 1) : null],
    ];
}

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    fakeApi(['documents/policies*' => Http::response(policyPage(1, 1))]);

    sdk()->policies()->query()
        ->filter('category', 'hr')
        ->sort('title')
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[category]=hr')
            && str_contains($url, 'sort=title')
            && str_contains($url, 'per_page=15')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates policy DTOs including the typed category enum', function () {
    fakeApi(['documents/policies*' => Http::response(policyPage(1, 2))]);

    $page = sdk()->policies()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(Policy::class)
        ->and($page->items()[0]->title)->toBe('Policy 1')
        ->and($page->items()[0]->category)->toBe(PolicyCategory::HealthAndSafety)
        ->and($page->items()[0]->categoryLabel)->toBe('Health & Safety')
        ->and($page->items()[0]->isActive)->toBeTrue()
        ->and($page->items()[0]->createdByName)->toBe('Grace')
        ->and($page->items()[0]->contentHtml)->toBe('<p>Policy content here...</p>')
        ->and($page->items()[0]->acknowledgedAt)->toBe('2026-02-01T09:00:00.000000Z')
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single policy by id', function () {
    fakeApi([
        'documents/policies/7' => [
            'data' => ['type' => 'policies', 'id' => '7', 'attributes' => ['title' => 'Data Protection', 'category' => 'data_protection', 'is_active' => true]],
        ],
    ]);

    $policy = sdk()->policies()->find(7);

    expect($policy)->toBeInstanceOf(Policy::class)
        ->and($policy->id)->toBe('7')
        ->and($policy->category)->toBe(PolicyCategory::DataProtection);
});

it('returns null when finding a missing policy (404)', function () {
    fakeApi(['documents/policies/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->policies()->find(999))->toBeNull();
});

it('acknowledges a policy', function () {
    fakeApi(['documents/policies/7/acknowledge' => Http::response([
        'message' => 'Policy acknowledged successfully.',
        'data' => ['policy_id' => 7, 'acknowledged_at' => '2026-06-06T10:00:00Z'],
    ], 201)]);

    sdk()->policies()->acknowledge(7);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'documents/policies/7/acknowledge')
        && $request->method() === 'POST');
});

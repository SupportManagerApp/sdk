<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Documents\Document;
use SupportManager\Sdk\Data\Documents\DocumentFolder;
use SupportManager\Sdk\Data\Documents\Policy;
use SupportManager\Sdk\Enums\Documents\PolicyCategory;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\DocumentFactory;
use SupportManager\Sdk\Testing\Factories\DocumentFolderFactory;
use SupportManager\Sdk\Testing\Factories\PolicyFactory;

// Host-style tests: seed factories, call the SDK normally, assert on DTOs.
// No Http::fake(), no JSON:API envelopes by hand.

// ── Documents ───────────────────────────────────────────────────────────────

it('lists seeded documents as DTOs', function () {
    SupportManager::fake()->seed(DocumentFactory::new()->count(3));

    $page = SupportManager::documents()->query()->get();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(Document::class)
        ->and($page->items()[0]->name)->toBe('Document 1')
        ->and($page->items()[0]->userName)->toBe('Ada')
        ->and($page->items()[0]->documentFolderName)->toBe('Contracts')
        ->and($page->items()[0]->uploadedByName)->toBe('Grace')
        ->and($page->total())->toBe(3);
});

it('finds a seeded document by id and returns null for a miss', function () {
    SupportManager::fake()->seed(DocumentFactory::new()->count(2));

    $document = SupportManager::documents()->find(2);

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document?->id)->toBe('2')
        ->and(SupportManager::documents()->find(999))->toBeNull();
});

it('returns the raw download payload for a seeded document', function () {
    SupportManager::fake()->seed(DocumentFactory::new()->count(1));

    $response = SupportManager::documents()->download(1);

    expect($response->json('data.download_url'))->toBe('https://s3.test/signed/1.pdf')
        ->and($response->json('data.filename'))->toBe('Document 1');
});

it('returns a raw 404 download response for a missing document', function () {
    SupportManager::fake()->seed(DocumentFactory::new()->count(1));

    $response = SupportManager::documents()->download(999);

    expect($response->status())->toBe(404);
});

// ── Document folders ──────────────────────────────────────────────────────────

it('lists seeded folders as DTOs', function () {
    SupportManager::fake()->seed(
        DocumentFolderFactory::new()->count(1),
        DocumentFolderFactory::new()->childOf(1),
    );

    $page = SupportManager::documentFolders()->query()->get();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(DocumentFolder::class)
        ->and($page->items()[0]->name)->toBe('Folder 1')
        ->and($page->items()[0]->parentId)->toBeNull()
        ->and($page->items()[0]->documentsCount)->toBe(4)
        ->and($page->items()[1]->parentId)->toBe(1);
});

it('finds a seeded folder by id and returns null for a miss', function () {
    SupportManager::fake()->seed(DocumentFolderFactory::new()->count(2));

    $folder = SupportManager::documentFolders()->find(1);

    expect($folder)->toBeInstanceOf(DocumentFolder::class)
        ->and($folder?->id)->toBe('1')
        ->and(SupportManager::documentFolders()->find(999))->toBeNull();
});

// ── Policies ──────────────────────────────────────────────────────────────────

it('lists seeded policies including the typed category enum', function () {
    SupportManager::fake()->seed(
        PolicyFactory::new()->count(2),
        PolicyFactory::new()->category(PolicyCategory::DataProtection, 'Data Protection')->inactive(),
    );

    $page = SupportManager::policies()->query()->get();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(Policy::class)
        ->and($page->items()[0]->title)->toBe('Policy 1')
        ->and($page->items()[0]->category)->toBe(PolicyCategory::HealthAndSafety)
        ->and($page->items()[0]->categoryLabel)->toBe('Health & Safety')
        ->and($page->items()[0]->isActive)->toBeTrue()
        ->and($page->items()[0]->createdByName)->toBe('Grace')
        ->and($page->items()[2]->category)->toBe(PolicyCategory::DataProtection)
        ->and($page->items()[2]->isActive)->toBeFalse();
});

it('finds a seeded policy by id and returns null for a miss', function () {
    SupportManager::fake()->seed(PolicyFactory::new()->count(2));

    $policy = SupportManager::policies()->find(1);

    expect($policy)->toBeInstanceOf(Policy::class)
        ->and($policy?->id)->toBe('1')
        ->and(SupportManager::policies()->find(999))->toBeNull();
});

it('acknowledges a seeded policy', function () {
    SupportManager::fake()->seed(PolicyFactory::new()->count(1));

    SupportManager::policies()->acknowledge(1);
})->throwsNoExceptions();

it('throws when acknowledging a missing policy', function () {
    SupportManager::fake()->seed(PolicyFactory::new()->count(1));

    expect(fn () => SupportManager::policies()->acknowledge(999))
        ->toThrow(SupportManagerApiException::class);
});

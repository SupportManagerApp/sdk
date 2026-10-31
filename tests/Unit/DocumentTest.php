<?php

declare(strict_types=1);

use SupportManager\Sdk\Http\Document;

it('parses a single JSON:API resource into a flattened array', function () {
    $document = Document::fromArray([
        'data' => [
            'type' => 'car-journeys',
            'id' => '123',
            'attributes' => ['driver_name' => 'Ada', 'is_ongoing' => true],
        ],
        'meta' => ['team_id' => '42'],
    ]);

    expect($document->isCollection())->toBeFalse()
        ->and($document->meta())->toBe(['team_id' => '42']);

    $resource = $document->resource();

    expect($resource->type)->toBe('car-journeys')
        ->and($resource->id)->toBe('123')
        ->and($resource->attribute('driver_name'))->toBe('Ada')
        ->and($resource->flat())->toBe([
            'id' => '123',
            'type' => 'car-journeys',
            'driver_name' => 'Ada',
            'is_ongoing' => true,
        ]);
});

it('coerces a numeric JSON:API id to a string', function () {
    // Laravel emits ids as `(string) getKey()`, but be defensive about numerics.
    $resource = Document::fromArray(['data' => ['type' => 'teams', 'id' => 42]])->resource();

    expect($resource->id)->toBe('42');
});

it('parses a collection with pagination meta and links', function () {
    $document = Document::fromArray([
        'data' => [
            ['type' => 'car-journeys', 'id' => '1', 'attributes' => ['driver_name' => 'Ada']],
            ['type' => 'car-journeys', 'id' => '2', 'attributes' => ['driver_name' => 'Grace']],
        ],
        'meta' => ['current_page' => 1, 'last_page' => 3, 'per_page' => 2, 'total' => 6],
        'links' => ['next' => 'https://api.test/api/v1/car-journeys?page=2'],
    ]);

    expect($document->isCollection())->toBeTrue()
        ->and($document->resources())->toHaveCount(2)
        ->and($document->resources()[1]->id)->toBe('2')
        ->and($document->meta()['total'])->toBe(6);
});

it('treats an absent or empty data member as an empty collection', function () {
    expect(Document::fromArray([])->resources())->toBe([])
        ->and(Document::fromArray([])->resource())->toBeNull()
        ->and(Document::fromArray(['data' => []])->isCollection())->toBeTrue();
});

it('exposes side-loaded included resources', function () {
    $document = Document::fromArray([
        'data' => ['type' => 'car-journeys', 'id' => '1', 'attributes' => []],
        'included' => [
            ['type' => 'users', 'id' => '7', 'attributes' => ['name' => 'Ada']],
        ],
    ]);

    expect($document->included())->toHaveCount(1)
        ->and($document->included()[0]->type)->toBe('users')
        ->and($document->included()[0]->attribute('name'))->toBe('Ada');
});

it('folds a resource-level meta member into the flattened array', function () {
    $resource = Document::fromArray([
        'data' => [
            'type' => 'chores',
            'id' => '7',
            'attributes' => ['name' => 'Vacuum'],
            'meta' => ['can' => ['update' => true, 'delete' => false]],
        ],
    ])->resource();

    expect($resource->meta)->toBe(['can' => ['update' => true, 'delete' => false]])
        ->and($resource->flat())->toBe([
            'id' => '7',
            'type' => 'chores',
            'name' => 'Vacuum',
            'meta' => ['can' => ['update' => true, 'delete' => false]],
        ]);
});

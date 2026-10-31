<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Profile\DirectoryUser;
use SupportManager\Sdk\Data\Profile\UserOption;

/**
 * @return array<string, mixed>
 */
function profileDirectoryPage(): array
{
    return [
        'data' => [
            ['type' => 'users', 'id' => '1', 'attributes' => ['name' => 'Ada', 'avatar' => 'https://ui-avatars.com/api/?name=Ada&background=random', 'role' => 'PA']],
            ['type' => 'users', 'id' => '2', 'attributes' => ['name' => 'Grace', 'avatar' => 'https://ui-avatars.com/api/?name=Grace&background=random', 'role' => 'Manager']],
        ],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 2, 'team_id' => 42],
        'links' => ['next' => null],
    ];
}

it('lists directory users with a team header', function () {
    fakeApi(['users*' => Http::response(profileDirectoryPage())]);

    $users = sdk()->users()->list();

    expect($users)->toHaveCount(2)
        ->and($users[0])->toBeInstanceOf(DirectoryUser::class)
        ->and($users[0]->name)->toBe('Ada')
        ->and($users[1]->role)->toBe('Manager');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/users')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('forwards search and sort filters as query params', function () {
    fakeApi(['users*' => Http::response(profileDirectoryPage())]);

    sdk()->users()->list(['filter[search]' => 'ada', 'sort' => 'name', 'per_page' => 10]);

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[search]=ada')
            && str_contains($url, 'sort=name')
            && str_contains($url, 'per_page=10');
    });
});

it('reads the first page of the directory, with its pagination, from all()', function () {
    fakeApi(['users*' => Http::response(profileDirectoryPage())]);

    $page = sdk()->users()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(DirectoryUser::class)
        ->and($page->items()[1]->name)->toBe('Grace')
        ->and($page->total())->toBe(2)
        ->and($page->hasMorePages())->toBeFalse();

    assertSentTo('GET', 'users', fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('finds a single directory user by id', function () {
    fakeApi(['users/2' => [
        'data' => ['type' => 'users', 'id' => '2', 'attributes' => [
            'name' => 'Grace',
            'role' => 'Manager',
            'mobile_number' => '+44 7700 900003',
        ]],
    ]]);

    $user = sdk()->users()->find(2);

    expect($user)->toBeInstanceOf(DirectoryUser::class)
        ->and($user->id)->toBe('2')
        ->and($user->mobileNumber)->toBe('+44 7700 900003');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('returns null when a directory user is not found (404)', function () {
    fakeApi(['users/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->users()->find(999))->toBeNull();
});

it('lists colleague picker options with a team header', function () {
    fakeApi(['users/options' => [
        'data' => [
            ['type' => 'users', 'id' => '5', 'attributes' => ['name' => 'Ada Lovelace', 'avatar' => 'https://cdn.test/ada.png']],
            ['type' => 'users', 'id' => '6', 'attributes' => ['name' => 'Grace Hopper', 'avatar' => 'https://ui-avatars.com/api/?name=Grace+Hopper&background=random']],
        ],
    ]]);

    $options = sdk()->users()->options();

    expect($options)->toHaveCount(2)
        ->and($options[0])->toBeInstanceOf(UserOption::class)
        ->and($options[0]->id)->toBe('5')
        ->and($options[0]->name)->toBe('Ada Lovelace')
        ->and($options[0]->avatar)->toBe('https://cdn.test/ada.png')
        ->and($options[1]->avatar)->toBe('https://ui-avatars.com/api/?name=Grace+Hopper&background=random');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/users/options')
        && $request->hasHeader('X-Team-ID', '42'));
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Profile\DirectoryUser;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Profile\UsersResource;

function usersResource(): UsersResource
{
    return app(UsersResource::class);
}

/**
 * @return array<string, mixed>
 */
function profileDirectoryPage(): array
{
    return [
        'data' => [
            ['type' => 'users', 'id' => '1', 'attributes' => ['name' => 'Ada', 'avatar' => null, 'role' => 'employee']],
            ['type' => 'users', 'id' => '2', 'attributes' => ['name' => 'Grace', 'avatar' => null, 'role' => 'manager']],
        ],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 2, 'team_id' => 42],
        'links' => ['next' => null],
    ];
}

it('lists directory users without a team header', function () {
    Http::fake(['api.test/api/v1/users*' => Http::response(profileDirectoryPage())]);
    bootTenantSession();

    $users = usersResource()->list();

    expect($users)->toHaveCount(2)
        ->and($users[0])->toBeInstanceOf(DirectoryUser::class)
        ->and($users[0]->name)->toBe('Ada')
        ->and($users[1]->role)->toBe('manager');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/users')
        && ! $request->hasHeader('X-Team-ID'));
});

it('forwards search and sort filters as query params', function () {
    Http::fake(['api.test/api/v1/users*' => Http::response(profileDirectoryPage())]);
    bootTenantSession();

    usersResource()->list(['filter[search]' => 'ada', 'sort' => 'name', 'per_page' => 10]);

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[search]=ada')
            && str_contains($url, 'sort=name')
            && str_contains($url, 'per_page=10');
    });
});

it('finds a single directory user by id', function () {
    Http::fake(['api.test/api/v1/users/2' => Http::response([
        'data' => ['type' => 'users', 'id' => '2', 'attributes' => [
            'name' => 'Grace',
            'role' => 'manager',
            'mobile_number' => '+44 7700 900003',
        ]],
    ])]);
    bootTenantSession();

    $user = usersResource()->find(2);

    expect($user)->toBeInstanceOf(DirectoryUser::class)
        ->and($user->id)->toBe('2')
        ->and($user->mobileNumber)->toBe('+44 7700 900003');

    Http::assertSent(fn ($request) => ! $request->hasHeader('X-Team-ID'));
});

it('returns null when a directory user is not found (404)', function () {
    Http::fake(['api.test/api/v1/users/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(usersResource()->find(999))->toBeNull();
});

it('maps a 422 on the directory to a validation exception', function () {
    Http::fake(['api.test/api/v1/users*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter' => ['The role filter is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        usersResource()->list(['filter[role]' => 'wizard']);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('filter'))->toBe('The role filter is invalid.');
    }
});

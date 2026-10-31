<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Teams\Team;
use SupportManager\Sdk\Resources\Teams\TeamsResource;

function teamsResource(): TeamsResource
{
    return app(TeamsResource::class);
}

it('lists the user teams without tenant scoping', function () {
    Http::fake(['api.test/api/v1/user/teams' => Http::response([
        'data' => [
            ['type' => 'teams', 'id' => '42', 'attributes' => ['name' => 'Acme Care', 'role' => 'Manager', 'slug' => 'acme-care']],
            ['type' => 'teams', 'id' => '43', 'attributes' => ['name' => 'Beta House', 'role' => 'PA', 'slug' => 'beta-house']],
        ],
    ])]);
    bootTenantSession();

    $page = teamsResource()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(Team::class)
        ->and($page->items()[0]->id)->toBe('42')
        ->and($page->items()[0]->name)->toBe('Acme Care')
        ->and($page->items()[0]->role)->toBe('Manager')
        ->and($page->items()[1]->slug)->toBe('beta-house');

    Http::assertSent(fn ($r) => $r->method() === 'GET'
        && str_ends_with($r->url(), '/api/v1/user/teams')
        && ! $r->hasHeader('X-Team-ID'));
});

it('counts the user teams', function () {
    Http::fake(['api.test/api/v1/user/teams/count' => Http::response(['data' => ['count' => 4]])]);
    bootTenantSession();

    expect(teamsResource()->count())->toBe(4);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v1/user/teams/count')
        && ! $r->hasHeader('X-Team-ID'));
});

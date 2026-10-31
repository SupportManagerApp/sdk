<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Teams\Team;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\TeamFactory;

it('lists the user teams as a single-page Paginated of DTOs', function () {
    SupportManager::fake()->seed(
        TeamFactory::new()->count(2),
        TeamFactory::new()->staff()->state(['name' => 'Beta House', 'slug' => 'beta-house']),
    );

    $page = SupportManager::teams()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(Team::class)
        ->and($page->items()[0]->name)->toBe('Team 1')
        ->and($page->items()[0]->role)->toBe('Manager')
        ->and($page->items()[2]->role)->toBe('PA')
        ->and($page->items()[2]->slug)->toBe('beta-house');
});

it('counts the user teams from the flat count endpoint', function () {
    SupportManager::fake()->seed(TeamFactory::new()->count(4));

    expect(SupportManager::teams()->count())->toBe(4);
});

it('returns an empty page when no teams are seeded', function () {
    // Registering the factory with zero records serves the platform's
    // unpaginated collection shape; a bare fake() answers its generic paginated
    // empty page, which `user/teams` never returns.
    SupportManager::fake()->seed(TeamFactory::new()->count(0));

    expect(SupportManager::teams()->all()->items())->toBe([]);
});

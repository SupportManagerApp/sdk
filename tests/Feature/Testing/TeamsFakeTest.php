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
        ->and($page->items()[0]->role)->toBe('manager')
        ->and($page->items()[2]->role)->toBe('staff')
        ->and($page->items()[2]->slug)->toBe('beta-house');
});

it('counts the user teams from the flat count endpoint', function () {
    SupportManager::fake()->seed(TeamFactory::new()->count(4));

    expect(SupportManager::teams()->count())->toBe(4);
});

it('returns an empty page when no teams are seeded', function () {
    SupportManager::fake();

    expect(SupportManager::teams()->all()->items())->toBe([]);
});

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake teams the authenticated user belongs to.
 *
 * The body is an ordinary JSON:API collection (type `teams`) of
 * `TeamJsonResource`s; the resource isn't tenant-scoped but flows through the
 * fake normally. `user/teams` is hand-routed because the platform answers an
 * unpaginated `->get()` collection (no `meta`/`links`), and the flat `count()`
 * endpoint returns a bare `{data: {count: N}}`.
 *
 * ```php
 * SupportManager::fake()->seed(TeamFactory::new()->count(3));
 * SupportManager::teams()->count(); // 3
 * ```
 */
final class TeamFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'user/teams';
    }

    public function type(): string
    {
        return 'teams';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Team {$number}",
            'display_name' => "Team {$number}",
            'role' => 'Manager',
            'slug' => "team-{$number}",
            'avatar_url' => 'https://ui-avatars.com/api/?name='.urlencode("Team {$number}").'&background=0ea5e9&color=fff&bold=true',
        ];
    }

    /**
     * Seed a team the user is only a personal assistant (staff member) of.
     */
    public function staff(): static
    {
        return $this->state(['role' => 'PA']);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('user/teams', static fn (RecordStore $store): mixed => Http::response(
                Json::collection('teams', $store->all('user/teams')),
            )),
            FakeRoute::get('user/teams/count', static fn (RecordStore $store): mixed => Http::response(
                Json::item(null, ['count' => count($store->all('user/teams'))]),
            )),
        ];
    }
}

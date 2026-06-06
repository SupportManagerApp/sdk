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
 * The body is an ordinary JSON:API collection (type `teams`); the resource isn't
 * tenant-scoped but flows through the fake normally and its single-page `all()`
 * is served by generic CRUD. This factory only adds the flat `count()` endpoint,
 * which returns a bare `{data: {count: N}}`.
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
            'role' => 'manager',
            'slug' => "team-{$number}",
        ];
    }

    /**
     * Seed a team the user is only a staff member of.
     */
    public function staff(): static
    {
        return $this->state(['role' => 'staff']);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('user/teams/count', static fn (RecordStore $store): mixed => Http::response(
                Json::item(null, ['count' => count($store->all('user/teams'))]),
            )),
        ];
    }
}

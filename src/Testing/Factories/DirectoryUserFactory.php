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
 * Fake the team's user directory.
 *
 * The {@see \SupportManager\Sdk\Resources\Profile\UsersResource} hydrates via
 * `Document`/`ResourceObject`, so it speaks JSON:API (type `users`). It is a
 * read-only collection — generic GET list (`users`) and GET `users/{id}` cover
 * `list()` and `find()` (and its 404 → null). The one custom route is
 * `users/options`, which projects the same seeded users down to the picker's
 * id, name and avatar, ordered by name (otherwise generic REST would read
 * `options` as an id). Directory users are non-tenant at the route layer but
 * flow through the fake normally.
 *
 * ```php
 * SupportManager::fake()->seed(DirectoryUserFactory::new()->count(3));
 * SupportManager::users()->list();      // list<DirectoryUser>
 * SupportManager::users()->find(2);     // DirectoryUser|null
 * SupportManager::users()->options();   // list<UserOption>
 * ```
 */
final class DirectoryUserFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'users';
    }

    public function type(): string
    {
        return 'users';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Teammate {$number}",
            'avatar' => null,
            'role' => 'employee',
            'mobile_number' => '+44 7700 9000'.mb_str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Mark this directory user as a team manager.
     */
    public function manager(): static
    {
        return $this->state(['role' => 'manager']);
    }

    public function fakeRoutes(): array
    {
        return [
            // GET users/options — every seeded user as a picker option, by name.
            FakeRoute::get('users/options', static function (RecordStore $store): mixed {
                $options = array_map(static fn (array $user): array => [
                    'id' => $user['id'] ?? '',
                    'name' => $user['name'] ?? '',
                    'avatar' => $user['avatar'] ?? null,
                ], $store->all('users'));

                usort($options, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

                return Http::response(Json::collection('users', $options));
            }),
        ];
    }
}

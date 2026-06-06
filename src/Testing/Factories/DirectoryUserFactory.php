<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake the team's user directory.
 *
 * The {@see \SupportManager\Sdk\Resources\Profile\UsersResource} hydrates via
 * `Document`/`ResourceObject`, so it speaks JSON:API (type `users`). It is a
 * read-only collection — generic GET list (`users`) and GET `users/{id}` cover
 * `list()` and `find()` (and its 404 → null), so no custom routes are needed.
 * Directory users are non-tenant at the route layer but flow through the fake
 * normally.
 *
 * ```php
 * SupportManager::fake()->seed(DirectoryUserFactory::new()->count(3));
 * SupportManager::users()->list();      // list<DirectoryUser>
 * SupportManager::users()->find(2);     // DirectoryUser|null
 * ```
 */
final class DirectoryUserFactory extends Factory
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
}

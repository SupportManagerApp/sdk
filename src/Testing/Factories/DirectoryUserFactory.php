<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake the team's user directory.
 *
 * The {@see \SupportManager\Sdk\Resources\Profile\UsersResource} hydrates via
 * `Document`/`ResourceObject`, so it speaks JSON:API (type `users`). It is a
 * read-only collection. Generic GET `users/{id}` covers `find()` (and its
 * 404 → null) with the full `UserProfileResource` attributes. The index
 * `users` is custom because the platform's `UserDirectoryResource` lists only
 * name, avatar and role (no mobile number), sorted by name, 20 per page, with
 * `filter[search]` (name contains) and `filter[role]`. `users/options`
 * projects the same seeded users down to the picker's id, name and avatar,
 * ordered by name (otherwise generic REST would read `options` as an id). Directory users are non-tenant at the route layer but
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
            // The platform never answers a null avatar: users without an
            // upload get a generated initials avatar (HasTenancy::getAvatarUrl()).
            'avatar' => 'https://ui-avatars.com/api/?name='.urlencode("Teammate {$number}").'&background=random',
            'role' => 'PA',
            'mobile_number' => '+44 7700 9000'.mb_str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Mark this directory user as a team manager.
     */
    public function manager(): static
    {
        return $this->state(['role' => 'Manager']);
    }

    public function fakeRoutes(): array
    {
        return [
            // GET users — the directory index (UserDirectoryController::index).
            FakeRoute::get('users', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $query = $fake->query($request);
                $filter = is_array($query['filter'] ?? null) ? $query['filter'] : [];
                $search = is_string($filter['search'] ?? null) ? $filter['search'] : null;
                $role = is_string($filter['role'] ?? null) ? $filter['role'] : null;

                $users = array_values(array_filter(
                    $store->all('users'),
                    static fn (array $user): bool => ($search === null || mb_stripos((string) ($user['name'] ?? ''), $search) !== false)
                        && ($role === null || ($user['role'] ?? null) === $role),
                ));

                $sort = is_string($query['sort'] ?? null) ? $query['sort'] : 'name';
                $descending = str_starts_with($sort, '-');
                $field = mb_ltrim($sort, '-');
                usort($users, static fn (array $a, array $b): int => ($descending ? -1 : 1)
                    * strcmp((string) ($a[$field] ?? ''), (string) ($b[$field] ?? '')));

                $entries = array_map(static fn (array $user): array => [
                    'id' => $user['id'] ?? '',
                    'name' => $user['name'] ?? '',
                    'avatar' => $user['avatar'] ?? null,
                    'role' => $user['role'] ?? null,
                ], $users);

                $perPage = max(1, (int) ($query['per_page'] ?? 20));
                $page = max(1, (int) ($query['page'] ?? 1));
                $lastPage = max(1, (int) ceil(count($entries) / $perPage));

                return Http::response(Json::collection('users', array_slice($entries, ($page - 1) * $perPage, $perPage), [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => count($entries),
                ], ['next' => $page < $lastPage ? strtok($request->url(), '?').'?page='.($page + 1) : null]));
            }),

            // GET users/options — every seeded user as a picker option, by name.
            FakeRoute::collection('users/options', static function (RecordStore $store): array {
                $options = array_map(static fn (array $user): array => [
                    'id' => $user['id'] ?? '',
                    'name' => $user['name'] ?? '',
                    'avatar' => $user['avatar'] ?? null,
                ], $store->all('users'));

                usort($options, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

                return $options;
            }),
        ];
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Profile;

use SupportManager\Sdk\Data\Profile\DirectoryUser;
use SupportManager\Sdk\Data\Profile\UserOption;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's user directory (`GET users`, `GET users/{user}`) and colleague
 * picker options (`GET users/options`).
 *
 * The directory itself is for admins and managers only (anyone else gets a
 * 403); {@see self::options()} is the lighter list every current role can read.
 * All of it is tenant-scoped: the platform requires `X-Team-ID` on these
 * routes (a 422 "Invalid or missing team" without it). It still hand-rolls
 * {@see self::list()} and {@see self::find()} — rather than the base
 * `query()`/`all()` — for its flat Spatie filter params and null-on-404
 * lookup, but routes them through the tenant-scoped base helpers so each
 * carries the current team's id.
 *
 * ```php
 * SupportManager::users()->list(['search' => 'ada']);
 * SupportManager::users()->find(7);
 * SupportManager::users()->options();
 * ```
 *
 * @extends Resource<DirectoryUser>
 */
final class UsersResource extends Resource
{
    /**
     * The directory, optionally filtered (`filter[search]`, `filter[role]`),
     * sorted (`sort=name,-created_at`) or paginated (`per_page`).
     *
     * @param  array<string, scalar|null>  $filters  Raw Spatie query params, e.g.
     *                                               ['filter[search]' => 'ada', 'sort' => 'name'].
     * @return list<DirectoryUser>
     */
    public function list(array $filters = []): array
    {
        return $this->collection($this->get($this->path(), $filters));
    }

    /**
     * Every active member of the current team, ordered by name, for rendering
     * a colleague picker (`GET users/options`). Not paginated, and unlike the
     * directory it is open to every role except former PAs.
     *
     * @return list<UserOption>
     */
    public function options(): array
    {
        return $this->collection($this->get("{$this->path()}/options"), UserOption::class);
    }

    /**
     * A single teammate by id (`GET users/{user}`), or null when not found.
     * Includes the teammate's mobile number.
     */
    public function find(string|int $id): ?DirectoryUser
    {
        $response = $this->get("{$this->path()}/{$id}");

        if ($response->status() === 404) {
            return null;
        }

        return $this->item($response);
    }

    protected function path(): string
    {
        return 'users';
    }

    protected function hydrate(ResourceObject $object): DirectoryUser
    {
        return DirectoryUser::fromArray($object->flat());
    }
}

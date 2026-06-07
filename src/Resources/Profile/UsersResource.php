<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Profile;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Profile\DirectoryUser;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's user directory (`GET users`, `GET users/{user}`).
 *
 * The directory is tenant-scoped: the platform requires `X-Team-ID` on these
 * routes (a 422 "Invalid or missing team" without it). It still hand-rolls
 * {@see self::list()} and {@see self::find()} — rather than the base
 * `query()`/`all()` — for its flat Spatie filter params and null-on-404
 * lookup, but routes them through the tenant-scoped {@see Resource::tenant()}
 * helper so each carries the current team's id.
 *
 * ```php
 * SupportManager::users()->list(['search' => 'ada']);
 * SupportManager::users()->find(7);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DirectoryUser>
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
        $response = $this->tenant(
            fn (PendingRequest $request): Response => $request->get('users', $filters),
        );

        $this->throwForErrorResponse($response, 'users');

        return array_map(
            $this->hydrate(...),
            Document::fromArray($response->json() ?? [])->resources(),
        );
    }

    /**
     * A single teammate by id (`GET users/{user}`), or null when not found.
     */
    public function find(string|int $id): ?DirectoryUser
    {
        $response = $this->tenant(
            fn (PendingRequest $request): Response => $request->get("users/{$id}"),
        );

        if ($response->status() === 404) {
            return null;
        }

        $this->throwForErrorResponse($response, "users/{$id}");

        $resource = Document::fromArray($response->json() ?? [])->resource();

        return $resource === null ? null : $this->hydrate($resource);
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

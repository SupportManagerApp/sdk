<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Documents;

use SupportManager\Sdk\Data\Documents\Policy;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Active company policies for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) plus the non-RESTful
 * `acknowledge()` action recording that the current user has read a policy.
 * Filter by `category` or `title` (partial); each policy carries the current
 * user's `acknowledgedAt`.
 *
 * ```php
 * SupportManager::policies()->query()->filter('category', 'hr')->sort('title')->get();
 * SupportManager::policies()->acknowledge(7);
 * ```
 *
 * @extends Resource<Policy>
 */
final class PoliciesResource extends Resource
{
    /**
     * Record that the current user has acknowledged a policy. The platform
     * responds with an acknowledgement receipt (not a Policy), so nothing is
     * returned; re-acknowledging is a no-op server-side.
     */
    public function acknowledge(string|int $id): void
    {
        $this->throwForErrorResponse($this->post("documents/policies/{$id}/acknowledge"));
    }

    protected function path(): string
    {
        return 'documents/policies';
    }

    protected function hydrate(ResourceObject $object): Policy
    {
        return Policy::fromArray($object->flat());
    }
}

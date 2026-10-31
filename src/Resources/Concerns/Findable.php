<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Concerns;

use SupportManager\Sdk\Resources\Resource;

/**
 * `find($id)` over `GET {path}/{id}`, null on a 404.
 *
 * Opt-in: use it only on a resource whose platform route shows one record as a
 * JSON:API item at `{path}/{id}`. Many collections have no such route (Laravel's
 * `apiResource(...)->except(['show'])`, for one), and there `find()` would only
 * ever get a 405. `tests/Arch/CollectionReadsTest.php` checks every resource
 * that has `find()` against the spec.
 *
 * ```php
 * final class PayslipsResource extends Resource
 * {
 *     // PHPStan: `@use Findable<Payslip>` in a docblock on the use line.
 *     use Findable;
 * }
 * ```
 *
 * @template T
 *
 * @phpstan-require-extends Resource
 */
trait Findable
{
    /**
     * Fetch one record by id, or null when it does not exist.
     *
     * @return T|null
     */
    public function find(string|int $id): mixed
    {
        return $this->newQuery()->find($id);
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Concerns;

use Illuminate\Http\Client\ConnectionException;
use SupportManager\Sdk\Http\Document;
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
 * This is the SDK's only single-record read over `{path}/{id}`. The fluent
 * {@see \SupportManager\Sdk\Http\ResourceQuery} from `Listable::query()`
 * only ever reads `GET {path}`, so a resource without this trait has no way
 * to request a show route the platform may not have.
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
     * Fetch one record by id from `GET {path}/{id}`, or null on a 404 (or a
     * body with no resource). Any other error response maps to a typed
     * exception, and a connection failure to a SupportManagerApiException.
     *
     * @return T|null
     */
    public function find(string|int $id): mixed
    {
        $uri = "{$this->path()}/{$id}";

        try {
            $response = $this->get($uri);
        } catch (ConnectionException $e) {
            throw $this->throwForConnectionFailure($e, $uri);
        }

        $this->throwForErrorResponse($response, $uri, allowStatuses: [404]);

        if ($response->status() === 404) {
            return null;
        }

        $resource = Document::fromArray($response->json() ?? [])->resource();

        return $resource === null ? null : $this->hydrate($resource);
    }
}

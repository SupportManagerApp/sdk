<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use SupportManager\Sdk\Data\Payroll\PayRun;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Pay runs — months rolled up into summary rows. Index only, for privileged
 * users (Admin/Manager); everyone else gets a 403. Totals include draft
 * (unpublished) payslips.
 *
 * The platform returns a flat (non-JSON:API) paginated `data` payload, so this
 * resource hand-rolls {@see self::all()} (and exposes no `query()`/`find()`,
 * which assume the JSON:API resource-object shape).
 *
 * ```php
 * SupportManager::payRuns()->all();
 * ```
 *
 * @extends resource<PayRun>
 */
final class PayRunsResource extends Resource
{
    /**
     * A page of pay-run summaries, newest month first.
     *
     * @return Paginated<PayRun>
     */
    public function all(): Paginated
    {
        return $this->page(1);
    }

    /**
     * Fetch a specific page of pay-run summaries.
     *
     * @return Paginated<PayRun>
     */
    public function page(int $page): Paginated
    {
        $response = $this->get('payroll/pay-runs', $page > 1 ? ['page' => $page] : []);

        $this->throwForErrorResponse($response, 'payroll/pay-runs');

        $body = $response->json() ?? [];
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return new Paginated(
            items: array_values(array_map(
                static fn (mixed $row): PayRun => PayRun::fromArray(is_array($row) ? $row : []),
                $data,
            )),
            meta: is_array($body['meta'] ?? null) ? $body['meta'] : [],
            links: is_array($body['links'] ?? null) ? $body['links'] : [],
            pageFetcher: fn (int $next): Paginated => $this->page($next),
        );
    }

    protected function path(): string
    {
        return 'payroll/pay-runs';
    }

    protected function hydrate(ResourceObject $object): PayRun
    {
        return PayRun::fromArray($object->flat());
    }
}

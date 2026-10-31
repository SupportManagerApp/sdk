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
 * resource reads it with {@see Resource::dataPaginated()} (and exposes no
 * `query()`/`find()`, which assume the JSON:API resource-object shape).
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
        return $this->dataPaginated(
            $this->get($this->path(), $page > 1 ? ['page' => $page] : []),
            PayRun::class,
            fn (int $next): Paginated => $this->page($next),
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

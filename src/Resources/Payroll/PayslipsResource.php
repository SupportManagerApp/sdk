<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Payroll\Payslip;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Monthly payslips for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) — filterable by `user_id`,
 * `year`, `month` and sortable by `process_date`/`created_at` — plus the
 * non-RESTful `latest()` upcoming payslip and a raw PDF `download()`.
 *
 * ```php
 * SupportManager::payslips()->query()->filter('year', 2026)->sort('-process_date')->get();
 * SupportManager::payslips()->latest();
 * ```
 *
 * @extends resource<Payslip>
 */
final class PayslipsResource extends Resource
{
    /**
     * The next upcoming published payslip, or null when there is none.
     */
    public function latest(): ?Payslip
    {
        return $this->item($this->get('payroll/payslips/latest'));
    }

    /**
     * Download a payslip as a PDF. Returns the raw binary {@see Response} — the
     * body is the PDF, not a JSON:API document, so it is intentionally not
     * hydrated into a DTO.
     */
    public function download(string|int $id): Response
    {
        return $this->get("payroll/payslips/{$id}/download");
    }

    protected function path(): string
    {
        return 'payroll/payslips';
    }

    protected function hydrate(ResourceObject $object): Payslip
    {
        return Payslip::fromArray($object->flat());
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use SupportManager\Sdk\Data\Payroll\Payslip;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Monthly payslips for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) — filterable by `user_id`,
 * `year`, `month` and sortable by `process_date`/`created_at` — plus the
 * non-RESTful `latest()` upcoming payslip and a PDF `download()`.
 *
 * ```php
 * SupportManager::payslips()->query()->filter('year', 2026)->sort('-process_date')->get();
 * SupportManager::payslips()->latest();
 * SupportManager::payslips()->download(9)->saveTo(storage_path('payslips'));
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
     * Download a payslip as a PDF. The response body is the PDF itself, so it
     * comes back as a {@see Download} (bytes, filename, `saveTo()`) rather than
     * a DTO. An error response (e.g. a 403 for someone else's payslip) throws.
     */
    public function download(string|int $id): Download
    {
        return $this->downloadFile("payroll/payslips/{$id}/download");
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

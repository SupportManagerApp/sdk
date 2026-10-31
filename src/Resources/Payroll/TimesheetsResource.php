<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use SupportManager\Sdk\Data\Payroll\Timesheet;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Monthly timesheets for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) — filterable by `user_id`,
 * `status`, `year`, `month` and sortable by `process_date`/`created_at`/`status`
 * — plus a PDF `download()`.
 *
 * ```php
 * SupportManager::timesheets()->query()->filter('status', 'submitted')->get();
 * ```
 *
 * @extends resource<Timesheet>
 */
final class TimesheetsResource extends Resource
{
    /**
     * Download a timesheet as a PDF. The response body is the PDF itself, so it
     * comes back as a {@see Download} rather than a DTO; an error response
     * throws.
     */
    public function download(string|int $id): Download
    {
        return $this->downloadFile("payroll/timesheets/{$id}/download");
    }

    protected function path(): string
    {
        return 'payroll/timesheets';
    }

    protected function hydrate(ResourceObject $object): Timesheet
    {
        return Timesheet::fromArray($object->flat());
    }
}

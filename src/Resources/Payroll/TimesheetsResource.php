<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Payroll\Timesheet;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Monthly timesheets for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) — filterable by `user_id`,
 * `status`, `year`, `month` and sortable by `process_date`/`created_at`/`status`
 * — plus a raw PDF `download()`.
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
     * Download a timesheet as a PDF. Returns the raw binary {@see Response} — the
     * body is the PDF, not a JSON:API document, so it is intentionally not
     * hydrated into a DTO.
     */
    public function download(string|int $id): Response
    {
        return $this->get("payroll/timesheets/{$id}/download");
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

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use SupportManager\Sdk\Data\Payroll\P45;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Leaver P45 documents for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) — filterable by `employee_id`
 * and `tax_year` (the year the employee left in, e.g. `2026-27`), sortable by
 * `leaving_date`/`published_at` (default `-leaving_date`) — plus a PDF
 * `download()`. Non-privileged users only ever see their own P45s; anyone
 * else's is a 403. Opening a P45 with `find()` or downloading it marks it
 * `viewed`, as the web app does.
 *
 * ```php
 * SupportManager::p45s()->query()->filter('tax_year', '2026-27')->get();
 * SupportManager::p45s()->find(1);
 * ```
 *
 * @extends resource<P45>
 */
final class P45sResource extends Resource
{
    /**
     * Download a P45 as a PDF. The response body is the PDF itself, so it comes
     * back as a {@see Download} rather than a DTO; an error response throws.
     * Each download is recorded in the platform's audit log.
     */
    public function download(string|int $id): Download
    {
        return $this->downloadFile("payroll/p45s/{$id}/download");
    }

    protected function path(): string
    {
        return 'payroll/p45s';
    }

    protected function hydrate(ResourceObject $object): P45
    {
        return P45::fromArray($object->flat());
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Payroll\P60;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * End-of-tax-year P60 documents for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) — filterable by `employee_id`
 * and `tax_year`, sortable by `tax_year`/`published_at` — plus a raw PDF
 * `download()`. Opening a P60 with `find()` or downloading it marks it
 * `viewed`, as the web app does.
 *
 * ```php
 * SupportManager::p60s()->query()->filter('tax_year', '2025/26')->get();
 * ```
 *
 * @extends resource<P60>
 */
final class P60sResource extends Resource
{
    /**
     * Download a P60 as a PDF. Returns the raw binary {@see Response} — the body
     * is the PDF, not a JSON:API document, so it is intentionally not hydrated
     * into a DTO.
     */
    public function download(string|int $id): Response
    {
        return $this->get("payroll/p60s/{$id}/download");
    }

    protected function path(): string
    {
        return 'payroll/p60s';
    }

    protected function hydrate(ResourceObject $object): P60
    {
        return P60::fromArray($object->flat());
    }
}

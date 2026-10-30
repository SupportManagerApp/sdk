<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Payroll\P45;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Leaver P45 documents for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) — filterable by `employee_id`
 * and `tax_year`, sortable by `leaving_date`/`published_at` (default
 * `-leaving_date`) — plus a raw PDF `download()`. Non-privileged users only
 * ever see their own P45s; anyone else's is a 403.
 *
 * ```php
 * SupportManager::p45s()->query()->filter('tax_year', '2025/26')->get();
 * ```
 *
 * @extends resource<P45>
 */
final class P45sResource extends Resource
{
    /**
     * Download a P45 as a PDF. Returns the raw binary {@see Response} — the body
     * is the PDF, not a JSON:API document, so it is intentionally not hydrated
     * into a DTO.
     */
    public function download(string|int $id): Response
    {
        return $this->get("payroll/p45s/{$id}/download");
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

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use SupportManager\Sdk\Data\Payroll\EstimatedPay;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Estimated pay for a month — a single (non-collection) projection.
 *
 * The platform exposes only a singleton `show()` here, returning a flat
 * (non-JSON:API) `data` payload, so this resource hand-rolls `show()` rather
 * than using the collection machinery. Pass `year`, `month`, and (for
 * privileged callers) `user_id` to target a specific projection; all default
 * server-side to the current month and authenticated user.
 *
 * ```php
 * SupportManager::estimatedPay()->show(['year' => 2026, 'month' => 6]);
 * ```
 *
 * @extends resource<EstimatedPay>
 */
final class EstimatedPayResource extends Resource
{
    /**
     * The estimated-pay projection, or null when the server returns no data.
     *
     * @param  array<string, mixed>  $query  Optional `year`, `month`, `user_id`.
     */
    public function show(array $query = []): ?EstimatedPay
    {
        $response = $this->get('payroll/estimated-pay', $query);

        $this->throwForErrorResponse($response, 'payroll/estimated-pay');

        $data = $response->json('data');

        return is_array($data) ? EstimatedPay::fromArray($data) : null;
    }

    protected function path(): string
    {
        return 'payroll/estimated-pay';
    }

    protected function hydrate(ResourceObject $object): EstimatedPay
    {
        return EstimatedPay::fromArray($object->flat());
    }
}

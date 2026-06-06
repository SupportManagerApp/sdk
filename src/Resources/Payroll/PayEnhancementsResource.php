<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use SupportManager\Sdk\Data\Payroll\PayEnhancement;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Pay enhancements — named rate multipliers managed by the team.
 *
 * Full CRUD: the collection (`query()`, `find()`, `all()`) plus `create()`,
 * `update()`, and `destroy()`.
 *
 * ```php
 * SupportManager::payEnhancements()->all();
 * SupportManager::payEnhancements()->create(['name' => 'Sunday', 'enhancement_factor' => '0.50']);
 * ```
 *
 * @extends resource<PayEnhancement>
 */
final class PayEnhancementsResource extends Resource
{
    /**
     * Create a pay enhancement, returning the created record.
     *
     * @param  array<string, mixed>  $attributes  e.g. `name`, `description`, `enhancement_factor`.
     */
    public function create(array $attributes): ?PayEnhancement
    {
        return $this->item($this->post('payroll/pay-enhancements', $attributes));
    }

    /**
     * Update a pay enhancement by id, returning the updated record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string|int $id, array $attributes): ?PayEnhancement
    {
        return $this->item($this->patch("payroll/pay-enhancements/{$id}", $attributes));
    }

    /**
     * Delete a pay enhancement by id.
     */
    public function destroy(string|int $id): void
    {
        $uri = "payroll/pay-enhancements/{$id}";

        $this->throwForErrorResponse($this->delete($uri), $uri);
    }

    protected function path(): string
    {
        return 'payroll/pay-enhancements';
    }

    protected function hydrate(ResourceObject $object): PayEnhancement
    {
        return PayEnhancement::fromArray($object->flat());
    }
}

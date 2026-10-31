<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The pay enhancement on a shift, from a rota entry's `pay_enhancement`
 * attribute. A summary of the payroll
 * {@see \SupportManager\Sdk\Data\Payroll\PayEnhancement} it points to (`id`),
 * plus whether it is hidden from everyone but the worker and privileged users
 * on this shift (`isPrivate`).
 *
 * `factor` is the raw multiplier, a string-cased decimal such as `"0.50"`.
 */
final readonly class ShiftPayEnhancement
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $factor,
        public bool $isPrivate = false,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  The rota entry's `pay_enhancement` attribute.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

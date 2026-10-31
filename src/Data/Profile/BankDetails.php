<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The user's payroll banking details.
 *
 * Mirrors the platform's `BankDetailsResource`. The `accountNumber` arrives
 * masked from the server (e.g. `****1234`); only its last four digits are ever
 * exposed.
 */
final readonly class BankDetails
{
    public function __construct(
        public string $id,
        public string $nameOnAccount,
        public string $sortCode,
        public ?string $accountNumber,
        public ?string $paymentMethod,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

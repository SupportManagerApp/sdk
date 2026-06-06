<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

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
        return new self(
            id: (string) ($data['id'] ?? ''),
            nameOnAccount: (string) ($data['name_on_account'] ?? ''),
            sortCode: (string) ($data['sort_code'] ?? ''),
            accountNumber: isset($data['account_number']) ? (string) $data['account_number'] : null,
            paymentMethod: isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

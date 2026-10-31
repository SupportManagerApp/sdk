<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * An end-of-tax-year P60 document. Mirrors the platform's `P60Resource`.
 *
 * Collection responses carry the summary fields; a single `show()`
 * side-loads the detail breakdowns ({@see $payDetails}, {@see $niContributions},
 * {@see $statutoryPayments}, {@see $otherDetails}) as associative-array rows,
 * which are empty when not loaded.
 */
final readonly class P60
{
    /**
     * @param  list<array<string, mixed>>  $payDetails
     * @param  list<array<string, mixed>>  $niContributions
     * @param  list<array<string, mixed>>  $statutoryPayments
     * @param  list<array<string, mixed>>  $otherDetails
     */
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $employeeId,
        #[From('employee.name')]
        public ?string $employeeName,
        public ?string $taxYear,
        public ?string $payrollNumber,
        public bool $downloadAvailable,
        public ?string $publishedAt,
        public bool $viewed,
        #[ListOf('array')]
        public array $payDetails,
        #[ListOf('array')]
        public array $niContributions,
        #[ListOf('array')]
        public array $statutoryPayments,
        #[ListOf('array')]
        public array $otherDetails,
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

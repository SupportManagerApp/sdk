<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

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
        public ?string $employeeName,
        public ?string $taxYear,
        public ?string $payrollNumber,
        public bool $downloadAvailable,
        public ?string $publishedAt,
        public bool $viewed,
        public array $payDetails,
        public array $niContributions,
        public array $statutoryPayments,
        public array $otherDetails,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $employee */
        $employee = is_array($data['employee'] ?? null) ? $data['employee'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            employeeName: isset($employee['name']) ? (string) $employee['name'] : null,
            taxYear: isset($data['tax_year']) ? (string) $data['tax_year'] : null,
            payrollNumber: isset($data['payroll_number']) ? (string) $data['payroll_number'] : null,
            downloadAvailable: (bool) ($data['download_available'] ?? false),
            publishedAt: isset($data['published_at']) ? (string) $data['published_at'] : null,
            viewed: (bool) ($data['viewed'] ?? false),
            payDetails: self::rows($data['pay_details'] ?? null),
            niContributions: self::rows($data['ni_contributions'] ?? null),
            statutoryPayments: self::rows($data['statutory_payments'] ?? null),
            otherDetails: self::rows($data['other_details'] ?? null),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $row): bool => is_array($row),
        ));
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

/**
 * A leaver's P45 document. Mirrors the platform's `P45Resource`.
 *
 * Unlike a P60 there are no side-loaded detail rows: collection and `show()`
 * responses carry the same flat attributes. {@see $thisEmploymentPay} and
 * {@see $thisEmploymentTax} are null unless the form shows this employment
 * separately from the year-to-date totals.
 */
final readonly class P45
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $employeeId,
        public ?string $employeeName,
        public ?string $taxYear,
        public ?string $leavingDate,
        public ?string $taxCode,
        public bool $week1Month1,
        public ?int $monthNumber,
        public bool $studentLoanDeductionsContinue,
        public float $totalPayToDate,
        public float $totalTaxToDate,
        public ?float $thisEmploymentPay,
        public ?float $thisEmploymentTax,
        public ?string $publishedAt,
        public bool $viewed,
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
            leavingDate: isset($data['leaving_date']) ? (string) $data['leaving_date'] : null,
            taxCode: isset($data['tax_code']) ? (string) $data['tax_code'] : null,
            week1Month1: (bool) ($data['week1_month1'] ?? false),
            monthNumber: isset($data['month_number']) ? (int) $data['month_number'] : null,
            studentLoanDeductionsContinue: (bool) ($data['student_loan_deductions_continue'] ?? false),
            totalPayToDate: (float) ($data['total_pay_to_date'] ?? 0),
            totalTaxToDate: (float) ($data['total_tax_to_date'] ?? 0),
            thisEmploymentPay: isset($data['this_employment_pay']) ? (float) $data['this_employment_pay'] : null,
            thisEmploymentTax: isset($data['this_employment_tax']) ? (float) $data['this_employment_tax'] : null,
            publishedAt: isset($data['published_at']) ? (string) $data['published_at'] : null,
            viewed: (bool) ($data['viewed'] ?? false),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

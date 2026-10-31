<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

/**
 * The authenticated employee's payroll starter declaration: their NI number,
 * whether they brought a P45 or filled in the new starter checklist, the P45
 * figures, and the payroll details the declaration sets.
 *
 * Mirrors the platform's `PayrollStarterDeclarationResource`, a flat
 * (non-JSON:API) `data` payload, plus `meta.can.update`. Answers the employee
 * has not given yet are null. The coded fields (`taxFormType`,
 * `starterDeclarationCircumstances`, `finalPayPeriodType`, `studentLoanPlan`,
 * `nationalInsuranceLetter`) are open lists on the platform, so they stay
 * strings. {@see $taxFormUrl} is a link to the stored P45 that expires after
 * five minutes, so do not cache it.
 */
final readonly class StarterDeclaration
{
    public function __construct(
        public ?string $niNumber,
        public ?string $taxFormType,
        public ?string $starterDeclarationCircumstances,
        public ?string $leavingDate,
        public ?float $totalPayToDate,
        public ?float $totalTaxToDate,
        public ?string $finalPayPeriodType,
        public ?int $finalPayPeriodNumber,
        public ?string $taxFormUrl,
        public ?string $nationalInsuranceLetter,
        public ?string $studentLoanPlan,
        public bool $postgraduateLoan,
        public ?string $taxCode,
        public bool $week1Month1,
        public ?string $updatedAt,
        public bool $canUpdate,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block.
     * @param  array<string, mixed>  $meta  The response `meta` block.
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        /** @var array<string, mixed> $can */
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        return new self(
            niNumber: isset($data['ni_number']) ? (string) $data['ni_number'] : null,
            taxFormType: isset($data['tax_form_type']) ? (string) $data['tax_form_type'] : null,
            starterDeclarationCircumstances: isset($data['starter_declaration_circumstances']) ? (string) $data['starter_declaration_circumstances'] : null,
            leavingDate: isset($data['leaving_date']) ? (string) $data['leaving_date'] : null,
            totalPayToDate: isset($data['total_pay_to_date']) ? (float) $data['total_pay_to_date'] : null,
            totalTaxToDate: isset($data['total_tax_to_date']) ? (float) $data['total_tax_to_date'] : null,
            finalPayPeriodType: isset($data['final_pay_period_type']) ? (string) $data['final_pay_period_type'] : null,
            finalPayPeriodNumber: isset($data['final_pay_period_number']) ? (int) $data['final_pay_period_number'] : null,
            taxFormUrl: isset($data['tax_form_url']) ? (string) $data['tax_form_url'] : null,
            nationalInsuranceLetter: isset($data['national_insurance_letter']) ? (string) $data['national_insurance_letter'] : null,
            studentLoanPlan: isset($data['student_loan_plan']) ? (string) $data['student_loan_plan'] : null,
            postgraduateLoan: (bool) ($data['postgraduate_loan'] ?? false),
            taxCode: isset($data['tax_code']) ? (string) $data['tax_code'] : null,
            week1Month1: (bool) ($data['week1_month1'] ?? false),
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            canUpdate: (bool) ($can['update'] ?? false),
        );
    }
}

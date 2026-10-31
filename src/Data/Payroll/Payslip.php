<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

/**
 * A monthly pay statement. Mirrors the platform's `PayslipResource`.
 *
 * Collection responses carry the headline figures and status; a single
 * `show()` additionally side-loads the line-item breakdowns
 * ({@see $payments}, {@see $deductions}, {@see $periodTotals},
 * {@see $ytdTotals}), which are empty arrays when not loaded, and the
 * employer's name and PAYE reference, which are null when not loaded.
 * {@see $firstOpenedAt} is set the first time the payslip's own employee opens
 * it. {@see $taxableGross} and {@see $niableGross} fall back to the total gross.
 */
final readonly class Payslip
{
    /**
     * @param  list<PayslipLine>  $payments
     * @param  list<PayslipLine>  $deductions
     * @param  list<PayslipLine>  $periodTotals
     * @param  list<PayslipLine>  $ytdTotals
     */
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?string $processDate,
        public ?string $payPeriodStart,
        public ?string $payPeriodEnd,
        public ?string $taxCode,
        public ?string $niLetter,
        public float $totalGross,
        public float $taxableGross,
        public float $niableGross,
        public ?string $netPayFormatted,
        public ?string $deductionsTotalFormatted,
        public string $status,
        public ?string $publishedAt,
        public ?string $firstOpenedAt,
        public array $payments,
        public array $deductions,
        public array $periodTotals,
        public array $ytdTotals,
        public ?string $employerName,
        public ?string $employerPayeReference,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];
        /** @var array{name?: mixed, paye_reference?: mixed} $employer */
        $employer = is_array($data['employer'] ?? null) ? $data['employer'] : [];
        $totalGross = (float) ($data['total_gross'] ?? 0);

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            processDate: isset($data['process_date']) ? (string) $data['process_date'] : null,
            payPeriodStart: isset($data['pay_period_start']) ? (string) $data['pay_period_start'] : null,
            payPeriodEnd: isset($data['pay_period_end']) ? (string) $data['pay_period_end'] : null,
            taxCode: isset($data['tax_code']) ? (string) $data['tax_code'] : null,
            niLetter: isset($data['ni_letter']) ? (string) $data['ni_letter'] : null,
            totalGross: $totalGross,
            taxableGross: isset($data['taxable_gross']) ? (float) $data['taxable_gross'] : $totalGross,
            niableGross: isset($data['niable_gross']) ? (float) $data['niable_gross'] : $totalGross,
            netPayFormatted: isset($data['net_pay_formatted']) ? (string) $data['net_pay_formatted'] : null,
            deductionsTotalFormatted: isset($data['deductions_total_formatted']) ? (string) $data['deductions_total_formatted'] : null,
            status: (string) ($data['status'] ?? ''),
            publishedAt: isset($data['published_at']) ? (string) $data['published_at'] : null,
            firstOpenedAt: isset($data['first_opened_at']) ? (string) $data['first_opened_at'] : null,
            payments: self::lines($data['payments'] ?? null),
            deductions: self::lines($data['deductions'] ?? null),
            periodTotals: self::lines($data['period_totals'] ?? null),
            ytdTotals: self::lines($data['ytd_totals'] ?? null),
            employerName: isset($employer['name']) ? (string) $employer['name'] : null,
            employerPayeReference: isset($employer['paye_reference']) ? (string) $employer['paye_reference'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * @return list<PayslipLine>
     */
    private static function lines(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $line): PayslipLine => PayslipLine::fromArray(is_array($line) ? $line : []),
            $value,
        ));
    }
}

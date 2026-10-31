<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[From('user.name')]
        public ?string $userName,
        public ?string $processDate,
        public ?string $payPeriodStart,
        public ?string $payPeriodEnd,
        public ?string $taxCode,
        public ?string $niLetter,
        public float $totalGross,
        #[From('taxable_gross', 'total_gross')]
        public float $taxableGross,
        #[From('niable_gross', 'total_gross')]
        public float $niableGross,
        public ?string $netPayFormatted,
        public ?string $deductionsTotalFormatted,
        public string $status,
        public ?string $publishedAt,
        public ?string $firstOpenedAt,
        #[ListOf(PayslipLine::class)]
        public array $payments,
        #[ListOf(PayslipLine::class)]
        public array $deductions,
        #[ListOf(PayslipLine::class)]
        public array $periodTotals,
        #[ListOf(PayslipLine::class)]
        public array $ytdTotals,
        #[From('employer.name')]
        public ?string $employerName,
        #[From('employer.paye_reference')]
        public ?string $employerPayeReference,
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

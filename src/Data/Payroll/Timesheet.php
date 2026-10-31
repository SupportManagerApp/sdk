<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A monthly timesheet record. Mirrors the platform's `TimesheetResource`.
 *
 * {@see $shiftHoursTotal} and {@see $additionalPaymentsTotal} (in pounds) are
 * two-decimal strings, as the platform sends them.
 */
final readonly class Timesheet
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        public ?string $processDate,
        public string $status,
        public ?string $comments,
        public bool $hasVariations,
        public float $grossPay,
        public ?string $shiftHoursTotal,
        public ?int $sleepCount,
        public ?string $additionalPaymentsTotal,
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

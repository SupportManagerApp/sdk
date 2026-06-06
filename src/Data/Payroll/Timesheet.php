<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

/**
 * A monthly timesheet record. Mirrors the platform's `TimesheetResource`.
 */
final readonly class Timesheet
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?string $processDate,
        public string $status,
        public ?string $comments,
        public bool $hasVariations,
        public float $grossPay,
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

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            processDate: isset($data['process_date']) ? (string) $data['process_date'] : null,
            status: (string) ($data['status'] ?? ''),
            comments: isset($data['comments']) ? (string) $data['comments'] : null,
            hasVariations: (bool) ($data['has_variations'] ?? false),
            grossPay: (float) ($data['gross_pay'] ?? 0),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

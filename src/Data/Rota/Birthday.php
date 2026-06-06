<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * A team member's birthday falling on a given date.
 *
 * Mirrors the platform's `birthdays` calendar feed, which returns flat
 * `{date, user_id, user_name}` rows rather than JSON:API resource objects.
 */
final readonly class Birthday
{
    public function __construct(
        public string $date,
        public ?int $userId,
        public ?string $userName,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One flat calendar row.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: (string) ($data['date'] ?? ''),
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($data['user_name']) ? (string) $data['user_name'] : null,
        );
    }
}

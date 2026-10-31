<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        return Hydrator::hydrate(self::class, $data);
    }
}

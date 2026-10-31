<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A team member's work anniversary falling on a given date.
 *
 * Mirrors the platform's `anniversaries` calendar feed, which returns flat
 * `{date, user_id, user_name, years}` rows rather than JSON:API resource
 * objects. `years` is the years of service reached on that date.
 */
final readonly class Anniversary
{
    public function __construct(
        public string $date,
        public ?int $userId,
        public ?string $userName,
        public ?int $years,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One flat calendar row.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

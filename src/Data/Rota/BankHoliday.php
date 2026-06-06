<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * A single bank holiday for the team's configured region.
 *
 * Mirrors the platform's `bank-holidays` calendar feed, which returns flat
 * `{date, name, region}` rows rather than JSON:API resource objects.
 */
final readonly class BankHoliday
{
    public function __construct(
        public string $date,
        public string $name,
        public ?string $region,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One flat calendar row.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: (string) ($data['date'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            region: isset($data['region']) ? (string) $data['region'] : null,
        );
    }
}

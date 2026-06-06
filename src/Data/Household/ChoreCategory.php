<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

/**
 * A category used to organise chores. Mirrors the platform's
 * `ChoreCategoryResource`; `choresCount` is only present when the controller
 * counts the relationship (it always does on index).
 */
final readonly class ChoreCategory
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $color,
        public ?string $icon,
        public ?int $choresCount,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            color: isset($data['color']) ? (string) $data['color'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            choresCount: isset($data['chores_count']) ? (int) $data['chores_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

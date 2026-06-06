<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Documents;

use SupportManager\Sdk\Enums\Documents\PolicyCategory;

/**
 * A company policy with versioning, effective/review dates and content.
 *
 * Mirrors the platform's `PolicyResource` attributes. `category` is the typed
 * enum; `categoryLabel` is the server's human label for it. `createdBy` is only
 * populated when the server includes the relation.
 */
final readonly class Policy
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public ?string $content,
        public ?PolicyCategory $category,
        public ?string $categoryLabel,
        public ?string $version,
        public ?string $effectiveDate,
        public ?string $reviewDate,
        public bool $isActive,
        public ?int $teamId,
        public ?int $createdById,
        public ?string $createdByName,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $createdBy */
        $createdBy = is_array($data['created_by'] ?? null) ? $data['created_by'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            content: isset($data['content']) ? (string) $data['content'] : null,
            category: isset($data['category'])
                ? PolicyCategory::tryFrom((string) $data['category'])
                : null,
            categoryLabel: isset($data['category_label']) ? (string) $data['category_label'] : null,
            version: isset($data['version']) ? (string) $data['version'] : null,
            effectiveDate: isset($data['effective_date']) ? (string) $data['effective_date'] : null,
            reviewDate: isset($data['review_date']) ? (string) $data['review_date'] : null,
            isActive: (bool) ($data['is_active'] ?? false),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            createdById: isset($createdBy['id']) ? (int) $createdBy['id'] : null,
            createdByName: isset($createdBy['name']) ? (string) $createdBy['name'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}

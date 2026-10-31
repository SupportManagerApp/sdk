<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Documents;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Documents\PolicyCategory;

/**
 * A company policy with versioning, effective/review dates and content.
 *
 * Mirrors the platform's `PolicyResource` attributes. `category` is the typed
 * enum; `categoryLabel` is the server's human label for it. `contentHtml` is the
 * policy rendered as sanitised HTML, exactly as the web app shows it.
 * `acknowledgedAt` is when the authenticated user acknowledged the policy (null
 * if they have not). `createdBy` is only populated when the server includes the
 * relation.
 */
final readonly class Policy
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public ?string $contentHtml,
        public ?PolicyCategory $category,
        public ?string $categoryLabel,
        public ?string $version,
        public ?string $effectiveDate,
        public ?string $reviewDate,
        public bool $isActive,
        public ?int $teamId,
        #[From('created_by.id')]
        public ?int $createdById,
        #[From('created_by.name')]
        public ?string $createdByName,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $acknowledgedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}

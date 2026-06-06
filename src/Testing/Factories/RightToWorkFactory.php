<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake right-to-work records.
 *
 * The resource is read-only JSON:API, so generic GET list/find against the
 * resource path is all that's needed — no custom routes.
 *
 * ```php
 * SupportManager::fake()->seed(RightToWorkFactory::new()->count(3));
 * SupportManager::rightToWork()->find(2);
 * ```
 */
final class RightToWorkFactory extends Factory
{
    public function path(): string
    {
        return 'compliance/right-to-work';
    }

    public function type(): string
    {
        return 'right-to-work';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "Employee {$number}"],
            'primary_document_type' => 'passport',
            'primary_document' => null,
            'supporting_document' => null,
            'share_code' => "SHARE-{$number}",
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * A biometric residence permit (with government share code) record.
     */
    public function biometricResidencePermit(): static
    {
        return $this->state(['primary_document_type' => 'brp']);
    }
}

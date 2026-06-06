<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake driving licence verification checks.
 *
 * The resource is read-only JSON:API, so generic GET list/find against the
 * resource path is all that's needed — no custom routes. States cover the
 * declaration toggle ({@see declarationAccepted()}/{@see declarationPending()}).
 *
 * ```php
 * SupportManager::fake()->seed(DriversLicenceCheckFactory::new()->count(3));
 * SupportManager::driversLicenceChecks()->find(2);
 * ```
 */
final class DriversLicenceCheckFactory extends Factory
{
    public function path(): string
    {
        return 'compliance/drivers-licence-checks';
    }

    public function type(): string
    {
        return 'drivers-licence-checks';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "Employee {$number}"],
            'drivers_licence_number' => "DL-{$number}",
            'check_code' => "CHK-{$number}",
            'declaration_accepted' => true,
            'check_result' => 'clean',
            'check_result_document' => null,
            'notes' => null,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * The employee has accepted the driving record declaration.
     */
    public function declarationAccepted(): static
    {
        return $this->state(['declaration_accepted' => true]);
    }

    /**
     * The employee has not yet accepted the declaration.
     */
    public function declarationPending(): static
    {
        return $this->state(['declaration_accepted' => false]);
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake DBS (Disclosure and Barring Service) checks.
 *
 * The resource is read-only JSON:API, so generic GET list/find against the
 * resource path is all that's needed — no custom routes. States cover the
 * obvious outcomes ({@see clear()}/{@see flagged()}).
 *
 * ```php
 * SupportManager::fake()->seed(DBSCheckFactory::new()->count(3));
 * SupportManager::dbsChecks()->find(2);
 * ```
 */
final class DBSCheckFactory extends Factory
{
    public function path(): string
    {
        return 'compliance/dbs-checks';
    }

    public function type(): string
    {
        return 'dbs-checks';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "Employee {$number}"],
            'date_of_issue' => '2026-01-01T00:00:00Z',
            'certificate_number' => "CERT-{$number}",
            'certificate' => null,
            'check_result' => 'clear',
            'notes' => null,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * A clear (no records found) check result.
     */
    public function clear(): static
    {
        return $this->state(['check_result' => 'clear']);
    }

    /**
     * A check that surfaced records to review.
     */
    public function flagged(): static
    {
        return $this->state(['check_result' => 'flagged']);
    }
}

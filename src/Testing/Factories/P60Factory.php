<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake end-of-tax-year P60 documents.
 *
 * A JSON:API collection (type `p60s`): generic list/find come for free; this
 * factory adds the raw PDF `{id}/download`. The side-loaded detail rows
 * (pay details, NI contributions, …) live inline in {@see withDetails()}.
 *
 * ```php
 * SupportManager::fake()->seed(P60Factory::new()->count(2));
 * SupportManager::p60s()->download(1);
 * ```
 */
final class P60Factory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'payroll/p60s';
    }

    public function type(): string
    {
        return 'p60s';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'employee_id' => 7,
            'employee' => ['id' => 7, 'name' => "Employee {$number}"],
            'tax_year' => '2025/26',
            'payroll_number' => 'PR-100',
            'download_available' => true,
            'published_at' => '2026-04-30T09:00:00Z',
            'viewed' => false,
            'pay_details' => [],
            'ni_contributions' => [],
            'statutory_payments' => [],
            'other_details' => [],
            'created_at' => '2026-04-30T09:00:00Z',
            'updated_at' => '2026-04-30T09:00:00Z',
        ];
    }

    /**
     * Mark this P60 as already viewed by the employee.
     */
    public function viewed(): static
    {
        return $this->state(['viewed' => true]);
    }

    /**
     * Seed the side-loaded detail rows a single show() returns.
     */
    public function withDetails(): static
    {
        return $this->state([
            'pay_details' => [['id' => 1, 'description' => 'Total Pay', 'amount' => 30000.0]],
            'ni_contributions' => [['id' => 1, 'ni_letter' => 'A', 'employee_contributions' => 1200.0]],
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('payroll/p60s/{id}/download', static function (RecordStore $store, array $matches): mixed {
                if ($store->find('payroll/p60s', $matches[1]) === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                return Http::response('%PDF-1.4 p60-bytes', 200, ['Content-Type' => 'application/pdf']);
            }),
        ];
    }
}

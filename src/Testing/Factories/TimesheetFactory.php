<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake monthly timesheets.
 *
 * A JSON:API collection (type `timesheets`): generic list/find come for free;
 * this factory adds the raw PDF `{id}/download`.
 *
 * ```php
 * SupportManager::fake()->seed(TimesheetFactory::new()->count(3)->approved());
 * SupportManager::timesheets()->download(1);
 * ```
 */
final class TimesheetFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'payroll/timesheets';
    }

    public function type(): string
    {
        return 'timesheets';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => 7,
            'user' => ['id' => 7, 'name' => "Employee {$number}"],
            'process_date' => '2026-06-30T00:00:00Z',
            'status' => 'submitted',
            'comments' => null,
            'has_variations' => true,
            'gross_pay' => 1800.0,
            'shift_hours_total' => '152.50',
            'sleep_count' => 12,
            'additional_payments_total' => '86.40',
            'created_at' => '2026-06-01T09:00:00Z',
            'updated_at' => '2026-06-25T09:00:00Z',
        ];
    }

    /**
     * Mark the timesheet as approved.
     */
    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('payroll/timesheets/{id}/download', static function (RecordStore $store, array $matches): mixed {
                if ($store->find('payroll/timesheets', $matches[1]) === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                return Http::response('%PDF-1.4 timesheet-bytes', 200, ['Content-Type' => 'application/pdf']);
            }),
        ];
    }
}

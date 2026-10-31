<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake pay-run summaries — index only.
 *
 * The platform returns a flat (non-JSON:API) *paginated* `data` payload keyed by
 * `month_key` (there is no surrogate id), so the generic flat-singleton GET is
 * wrong here: the index is an explicit flat-paginated route, built with
 * {@see Fake::paginate()} and a null type so each row is emitted verbatim.
 *
 * ```php
 * SupportManager::fake()->seed(PayRunFactory::new()->count(3));
 * SupportManager::payRuns()->all();
 * ```
 */
final class PayRunFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'payroll/pay-runs';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;
        $month = mb_str_pad((string) $number, 2, '0', STR_PAD_LEFT);

        return [
            'month_key' => "2026-{$month}",
            'month_label' => "Month {$number}",
            'process_date' => "2026-{$month}-28",
            'payslip_count' => 10,
            'published_count' => 10,
            'status' => 'published',
            'total_gross' => 25000.0,
            'total_net' => 19500.0,
        ];
    }

    /**
     * Seed a partially-published month (some payslips still in draft).
     */
    public function partial(): static
    {
        return $this->state(['status' => 'partial', 'published_count' => 5]);
    }

    public function fakeRoutes(): array
    {
        return [
            // Flat paginated index: type null so rows are emitted verbatim, with
            // a team_id alongside the page meta the platform sends.
            FakeRoute::get('payroll/pay-runs', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                return $fake->paginate('payroll/pay-runs', null, $store->all('payroll/pay-runs'), $request, ['team_id' => 42]);
            }),
        ];
    }
}

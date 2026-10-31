<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake end-of-tax-year P60 documents.
 *
 * A JSON:API collection (type `p60s`): the generic list comes for free; this
 * factory adds `find()` and the raw PDF `{id}/download`, both of which mark the
 * P60 `viewed` as the platform does. The side-loaded detail rows
 * (pay details, NI contributions, …) live inline in {@see withDetails()}.
 *
 * ```php
 * SupportManager::fake()->seed(P60Factory::new()->count(2));
 * SupportManager::p60s()->download(1);
 * ```
 */
final class P60Factory extends Factory implements ProvidesFakeRoutes
{
    private const array DETAIL_KEYS = ['pay_details', 'ni_contributions', 'statutory_payments', 'other_details'];

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
            // The platform's index does not eager-load the detail rows, so
            // P60Resource's whenLoaded() omits them (P60Controller::index).
            FakeRoute::get('payroll/p60s', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $filters = $fake->query($request)['filter'] ?? [];

                $records = array_values(array_filter(
                    $store->all('payroll/p60s'),
                    static function (array $record) use ($filters): bool {
                        foreach (is_array($filters) ? $filters : [] as $key => $value) {
                            if ((string) ($record[$key] ?? '') !== (string) $value) {
                                return false;
                            }
                        }

                        return true;
                    },
                ));

                $rows = array_map(
                    static fn (array $record): array => array_diff_key($record, array_flip(self::DETAIL_KEYS)),
                    $records,
                );

                return $fake->paginate('payroll/p60s', 'p60s', $rows, $request, ['team_id' => 42]);
            }),

            // Opening or downloading marks the P60 viewed, as the platform does.
            FakeRoute::get('payroll/p60s/{id}', static function (RecordStore $store, array $matches): mixed {
                $record = $store->update('payroll/p60s', $matches[1], ['viewed' => true]);

                return $record === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('p60s', $record));
            }),

            FakeRoute::get('payroll/p60s/{id}/download', static function (RecordStore $store, array $matches): mixed {
                if ($store->update('payroll/p60s', $matches[1], ['viewed' => true]) === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                return Http::response('%PDF-1.4 p60-bytes', 200, ['Content-Type' => 'application/pdf']);
            }),
        ];
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake pay enhancements — named rate multipliers.
 *
 * A JSON:API collection (type `pay_enhancements`) with full CRUD. Find, create,
 * update and delete come from the generic REST machinery; the index is a custom
 * route because the platform returns every enhancement, ordered by name, as a
 * plain (non-paginated) collection with only `meta.team_id`
 * (`PayEnhancementController::index`).
 *
 * ```php
 * SupportManager::fake()->seed(PayEnhancementFactory::new()->count(3));
 * SupportManager::payEnhancements()->create(['name' => 'Sunday', 'enhancement_factor' => '1']);
 * ```
 */
final class PayEnhancementFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'payroll/pay-enhancements';
    }

    public function type(): string
    {
        return 'pay_enhancements';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Enhancement {$number}",
            'description' => 'Weekend rate',
            'enhancement_factor' => '0.50',
            'enhancement_factor_label' => 'Time and a half',
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('payroll/pay-enhancements', static function (RecordStore $store): mixed {
                $records = $store->all('payroll/pay-enhancements');

                usort($records, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

                return Http::response(Json::collection('pay_enhancements', $records, ['team_id' => 42]));
            }),
        ];
    }
}

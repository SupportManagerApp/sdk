<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake household consumables, each with size variants.
 *
 * JSON:API collection (`consumables`), so the generic GET list comes for free
 * from the resource path. This factory adds the variant stock adjustment, which
 * lives under a different prefix (`household/consumable-variants/{id}/stock`):
 * it moves the variant's quantity by one (never below zero), recomputes
 * `is_low_stock`, and answers the whole consumable, as the platform does.
 * Variant ids are `{consumable number}0{n}` (101, 102, 201, …) so they are
 * unique across a seeded batch.
 *
 * ```php
 * SupportManager::fake()->seed(ConsumableFactory::new()->count(2), ConsumableFactory::new()->lowStock());
 * SupportManager::consumables()->adjustStock(101, -1);
 * ```
 */
final class ConsumableFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'household/consumables';
    }

    public function type(): string
    {
        return 'consumables';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Consumable {$number}",
            'variants' => [
                ['id' => (int) "{$number}01", 'size' => 'Small', 'quantity' => 5, 'minimum_stock' => 2, 'is_low_stock' => false],
                ['id' => (int) "{$number}02", 'size' => 'Large', 'quantity' => 3, 'minimum_stock' => 0, 'is_low_stock' => false],
            ],
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * Seed explicit variants. Each needs an `id`, `size`, `quantity` and
     * `minimum_stock`; `is_low_stock` is derived.
     *
     * @param  list<array{id: int, size: string, quantity: int, minimum_stock: int}>  $variants
     */
    public function withVariants(array $variants): static
    {
        return $this->state(['variants' => array_map(self::withLowStockFlag(...), $variants)]);
    }

    /**
     * Seed the consumable with its first variant below its minimum stock.
     */
    public function lowStock(): static
    {
        return $this->state(static function (int $index): array {
            $number = $index + 1;

            return ['variants' => [
                ['id' => (int) "{$number}01", 'size' => 'Small', 'quantity' => 1, 'minimum_stock' => 2, 'is_low_stock' => true],
            ]];
        });
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::patch('household/consumable-variants/{variant}/stock', static function (RecordStore $store, array $matches, Request $request): mixed {
                $adjustment = $request->data()['adjustment'] ?? null;

                if (! in_array($adjustment, [1, -1], true)) {
                    return Json::validation(['adjustment' => 'The selected adjustment is invalid.']);
                }

                foreach ($store->all('household/consumables') as $consumable) {
                    /** @var list<array<string, mixed>> $variants */
                    $variants = is_array($consumable['variants'] ?? null) ? $consumable['variants'] : [];

                    foreach ($variants as $position => $variant) {
                        if ((string) ($variant['id'] ?? '') !== $matches[1]) {
                            continue;
                        }

                        $variants[$position] = self::withLowStockFlag([
                            ...$variant,
                            'quantity' => max(0, (int) ($variant['quantity'] ?? 0) + $adjustment),
                        ]);

                        $updated = $store->update('household/consumables', (string) $consumable['id'], ['variants' => $variants]);

                        return Http::response(Json::item('consumables', $updated ?? $consumable));
                    }
                }

                return Json::notFound();
            }),
        ];
    }

    /**
     * A variant with `is_low_stock` set as the platform computes it: a minimum
     * stock above zero, and a quantity below it.
     *
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>
     */
    private static function withLowStockFlag(array $variant): array
    {
        $minimum = (int) ($variant['minimum_stock'] ?? 0);

        return [...$variant, 'is_low_stock' => $minimum > 0 && (int) ($variant['quantity'] ?? 0) < $minimum];
    }
}

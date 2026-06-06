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
 * Fake the team's weekly meal plan — a flat singleton (`type()` is null, so the
 * body is a bare `{data: {team_id, days: {...}, updated_at}}`, not a JSON:API
 * document).
 *
 * The platform takes day updates as flat top-level keys (`{monday: 'Pasta'}`) but
 * nests them under `days` in the response, and clears a single day via
 * `DELETE meal-plan/days/{day}` — so update and removeDay are explicit routes.
 *
 * ```php
 * SupportManager::fake()->seed(MealPlanFactory::new()->withDays(['monday' => 'Pasta']));
 * SupportManager::mealPlan()->update(['tuesday' => 'Curry']);
 * SupportManager::mealPlan()->removeDay('monday');
 * ```
 */
final class MealPlanFactory extends Factory implements ProvidesFakeRoutes
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function path(): string
    {
        return 'household/meal-plan';
    }

    public function definition(int $index): array
    {
        return [
            'team_id' => 42,
            'days' => [
                'monday' => 'Pasta',
                'tuesday' => null,
                'wednesday' => null,
                'thursday' => null,
                'friday' => null,
                'saturday' => null,
                'sunday' => null,
            ],
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * Seed specific days' meals, merged over the empty week.
     *
     * @param  array<string, string|null>  $days
     */
    public function withDays(array $days): static
    {
        return $this->state(static function () use ($days): array {
            $week = array_fill_keys(self::DAYS, null);

            return ['days' => [...$week, ...$days]];
        });
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('household/meal-plan', static fn (RecordStore $store): mixed => Http::response(
                Json::item(null, self::current($store)),
            )),

            // Day updates arrive as flat top-level keys; merge them into `days`.
            FakeRoute::patch('household/meal-plan', static function (RecordStore $store, array $matches, Request $request): mixed {
                $plan = self::current($store);
                /** @var array<string, mixed> $days */
                $days = is_array($plan['days'] ?? null) ? $plan['days'] : [];

                foreach ($request->data() as $day => $meal) {
                    if (in_array($day, self::DAYS, true)) {
                        $days[$day] = $meal;
                    }
                }

                $plan['days'] = $days;
                $store->put('household/meal-plan', [$plan]);

                return Http::response(Json::item(null, $plan));
            }),

            FakeRoute::delete('household/meal-plan/days/{day}', static function (RecordStore $store, array $matches): mixed {
                $day = $matches[1];

                if (! in_array($day, self::DAYS, true)) {
                    return Http::response(['message' => 'Invalid day.'], 422);
                }

                $plan = self::current($store);
                /** @var array<string, mixed> $days */
                $days = is_array($plan['days'] ?? null) ? $plan['days'] : [];
                $days[$day] = null;
                $plan['days'] = $days;
                $store->put('household/meal-plan', [$plan]);

                return Http::response(Json::item(null, $plan));
            }),
        ];
    }

    /**
     * The current stored plan, or an empty week when nothing is seeded.
     *
     * @return array<string, mixed>
     */
    private static function current(RecordStore $store): array
    {
        return $store->all('household/meal-plan')[0] ?? [
            'team_id' => 42,
            'days' => array_fill_keys(self::DAYS, null),
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }
}

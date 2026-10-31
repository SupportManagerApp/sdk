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
 * Fake the team's weekly meal plan — a singleton served as a JSON:API resource
 * (`meal_plans`), as the platform's `MealPlanResource` is.
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

    public function type(): string
    {
        return 'meal_plans';
    }

    public function definition(int $index): array
    {
        return [
            'team_id' => $this->teamId(),
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
            FakeRoute::get('household/meal-plan', static fn (RecordStore $store, array $matches, Request $request, Fake $fake): mixed => Http::response(
                Json::item('meal_plans', self::current($store, $fake)),
            )),

            // Day updates arrive as flat top-level keys; merge them into `days`.
            FakeRoute::patch('household/meal-plan', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $plan = self::current($store, $fake);
                /** @var array<string, mixed> $days */
                $days = is_array($plan['days'] ?? null) ? $plan['days'] : [];

                foreach ($request->data() as $day => $meal) {
                    if (in_array($day, self::DAYS, true)) {
                        $days[$day] = $meal;
                    }
                }

                $plan['days'] = $days;
                $store->put('household/meal-plan', [$plan]);

                return Http::response(Json::item('meal_plans', $plan));
            }),

            FakeRoute::delete('household/meal-plan/days/{day}', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $day = $matches[1];

                if (! in_array($day, self::DAYS, true)) {
                    return Json::message('Invalid day.', 422);
                }

                $plan = self::current($store, $fake);
                /** @var array<string, mixed> $days */
                $days = is_array($plan['days'] ?? null) ? $plan['days'] : [];
                $days[$day] = null;
                $plan['days'] = $days;
                $store->put('household/meal-plan', [$plan]);

                return Http::response(Json::item('meal_plans', $plan));
            }),
        ];
    }

    /**
     * The current stored plan, or an unsaved empty week (an empty-string id, as
     * the platform reports) when nothing is seeded.
     *
     * @return array<string, mixed>
     */
    private static function current(RecordStore $store, Fake $fake): array
    {
        return $store->all('household/meal-plan')[0] ?? [
            'id' => '',
            'team_id' => $fake->teamId(),
            'days' => array_fill_keys(self::DAYS, null),
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }
}

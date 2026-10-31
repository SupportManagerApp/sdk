<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use Illuminate\Http\Client\PendingRequest;
use SupportManager\Sdk\Data\Household\MealPlan;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's weekly meal plan — a singleton, not a collection.
 *
 * There is one meal plan per team, so this resource hand-rolls typed `show()`,
 * `update()` and `removeDay()` methods rather than exposing `query()`/`find()`.
 * The platform's `MealPlanResource` is a JSON:API resource (`meal_plans`), so
 * each body is `{data: {type, id, attributes: {team_id, days: {...},
 * updated_at}}}` and hydrates via `item()` like any other resource. The `id` is
 * an empty string until the team first saves a plan.
 *
 * ```php
 * SupportManager::mealPlan()->update(['monday' => 'Pasta']);
 * SupportManager::mealPlan()->removeDay('monday');
 * ```
 *
 * @extends Resource<MealPlan>
 */
final class MealPlanResource extends Resource
{
    /**
     * The current meal plan (always returned, even when no meals are set).
     */
    public function show(): ?MealPlan
    {
        return $this->item($this->get('household/meal-plan'));
    }

    /**
     * Set one or more days' meals, returning the updated plan. Keys are
     * lowercase day names (`monday` … `sunday`); a null value clears that day.
     *
     * @param  array<string, string|null>  $days
     */
    public function update(array $days): ?MealPlan
    {
        return $this->item($this->patch('household/meal-plan', $days));
    }

    /**
     * Clear a single day's meal, returning the updated plan.
     */
    public function removeDay(string $day): ?MealPlan
    {
        $uri = "household/meal-plan/days/{$day}";

        return $this->item(
            $this->tenant(fn (PendingRequest $request) => $request->delete($uri)),
        );
    }

    protected function path(): string
    {
        return 'household/meal-plan';
    }

    protected function hydrate(ResourceObject $object): MealPlan
    {
        return MealPlan::fromArray($object->flat());
    }
}

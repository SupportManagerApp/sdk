<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\MealPlan;

function householdMealPlanBody(array $days = []): array
{
    return [
        'data' => [
            'id' => '1',
            'type' => 'meal_plans',
            'attributes' => [
                'team_id' => 42,
                'days' => array_merge([
                    'monday' => 'Pasta',
                    'tuesday' => null,
                    'wednesday' => null,
                    'thursday' => null,
                    'friday' => null,
                    'saturday' => null,
                    'sunday' => null,
                ], $days),
                'updated_at' => '2026-06-06T10:00:00Z',
            ],
        ],
        'meta' => ['team_id' => 42],
    ];
}

it('shows the meal plan from a JSON:API body', function () {
    fakeApi(['household/meal-plan' => Http::response(householdMealPlanBody())]);

    $plan = sdk()->mealPlan()->show();

    expect($plan)->toBeInstanceOf(MealPlan::class)
        ->and($plan->teamId)->toBe(42)
        ->and($plan->monday)->toBe('Pasta')
        ->and($plan->tuesday)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('updates meal plan days', function () {
    fakeApi(['household/meal-plan' => Http::response(householdMealPlanBody(['monday' => 'Curry']))]);

    expect(sdk()->mealPlan()->update(['monday' => 'Curry'])->monday)->toBe('Curry');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['monday'] === 'Curry');
});

it('removes a meal plan day', function () {
    fakeApi(['household/meal-plan/days/monday' => Http::response(householdMealPlanBody(['monday' => null]))]);

    expect(sdk()->mealPlan()->removeDay('monday')->monday)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'meal-plan/days/monday'));
});

it('shows an unsaved meal plan, which carries an empty id', function () {
    $body = householdMealPlanBody(['monday' => null]);
    $body['data']['id'] = '';
    $body['data']['attributes']['updated_at'] = null;

    fakeApi(['household/meal-plan' => Http::response($body)]);

    $plan = sdk()->mealPlan()->show();

    expect($plan)->toBeInstanceOf(MealPlan::class)
        ->and($plan->teamId)->toBe(42)
        ->and($plan->monday)->toBeNull()
        ->and($plan->updatedAt)->toBeNull();
});

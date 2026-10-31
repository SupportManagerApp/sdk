<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\MealPlan;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Household\MealPlanResource;

function householdMealPlan(): MealPlanResource
{
    return app(MealPlanResource::class);
}

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
    Http::fake(['api.test/api/v1/household/meal-plan' => Http::response(householdMealPlanBody())]);
    bootTenantSession();

    $plan = householdMealPlan()->show();

    expect($plan)->toBeInstanceOf(MealPlan::class)
        ->and($plan->teamId)->toBe(42)
        ->and($plan->monday)->toBe('Pasta')
        ->and($plan->tuesday)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('updates meal plan days', function () {
    Http::fake(['api.test/api/v1/household/meal-plan' => Http::response(householdMealPlanBody(['monday' => 'Curry']))]);
    bootTenantSession();

    expect(householdMealPlan()->update(['monday' => 'Curry'])->monday)->toBe('Curry');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['monday'] === 'Curry');
});

it('removes a meal plan day', function () {
    Http::fake(['api.test/api/v1/household/meal-plan/days/monday' => Http::response(householdMealPlanBody(['monday' => null]))]);
    bootTenantSession();

    expect(householdMealPlan()->removeDay('monday')->monday)->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'meal-plan/days/monday'));
});

it('maps a 422 on an invalid day to a validation exception', function () {
    Http::fake(['api.test/api/v1/household/meal-plan/days/funday' => Http::response([
        'message' => 'Invalid day.',
    ], 422)]);
    bootTenantSession();

    expect(fn () => householdMealPlan()->removeDay('funday'))
        ->toThrow(ApiValidationException::class);
});

it('shows an unsaved meal plan, which carries an empty id', function () {
    $body = householdMealPlanBody(['monday' => null]);
    $body['data']['id'] = '';
    $body['data']['attributes']['updated_at'] = null;

    Http::fake(['api.test/api/v1/household/meal-plan' => Http::response($body)]);
    bootTenantSession();

    $plan = householdMealPlan()->show();

    expect($plan)->toBeInstanceOf(MealPlan::class)
        ->and($plan->teamId)->toBe(42)
        ->and($plan->monday)->toBeNull()
        ->and($plan->updatedAt)->toBeNull();
});

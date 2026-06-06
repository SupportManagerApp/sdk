<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Household\Chore;
use SupportManager\Sdk\Data\Household\ChoreCategory;
use SupportManager\Sdk\Data\Household\MealPlan;
use SupportManager\Sdk\Data\Household\ShoppingListItem;
use SupportManager\Sdk\Enums\Household\ChoreFrequency;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\ChoreCategoryFactory;
use SupportManager\Sdk\Testing\Factories\ChoreFactory;
use SupportManager\Sdk\Testing\Factories\MealPlanFactory;
use SupportManager\Sdk\Testing\Factories\ShoppingListItemFactory;

// Host-style tests for the Household module: seed factories, call the SDK
// normally, assert on DTOs. No Http::fake(), no JSON:API envelopes by hand.

// ── Shopping list ──────────────────────────────────────────────────────────────

it('lists seeded shopping list items as DTOs', function () {
    SupportManager::fake()->seed(ShoppingListItemFactory::new()->count(3));

    $page = SupportManager::shoppingList()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(ShoppingListItem::class)
        ->and($page->items()[0]->name)->toBe('Item 1')
        ->and($page->items()[0]->requestedByName)->toBe('Ada')
        ->and($page->total())->toBe(3);
});

it('finds a shopping list item by id and returns null for a miss', function () {
    SupportManager::fake()->seed(ShoppingListItemFactory::new()->count(2));

    expect(SupportManager::shoppingList()->find(2))->toBeInstanceOf(ShoppingListItem::class)
        ->and(SupportManager::shoppingList()->find(2)->id)->toBe('2')
        ->and(SupportManager::shoppingList()->find(999))->toBeNull();
});

it('creates, updates and deletes a shopping list item', function () {
    SupportManager::fake()->seed(ShoppingListItemFactory::new()->count(1));

    $created = SupportManager::shoppingList()->create(['name' => 'Milk']);
    expect($created->name)->toBe('Milk')
        ->and(SupportManager::shoppingList()->all()->total())->toBe(2);

    $updated = SupportManager::shoppingList()->update($created->id, ['name' => 'Bread']);
    expect($updated->name)->toBe('Bread');

    SupportManager::shoppingList()->delete($created->id);
    expect(SupportManager::shoppingList()->all()->total())->toBe(1);
});

it('toggles the purchased flag on a shopping list item', function () {
    SupportManager::fake()->seed(ShoppingListItemFactory::new()->count(1));

    expect(SupportManager::shoppingList()->find(1)->needsPurchasing)->toBeTrue();

    $toggled = SupportManager::shoppingList()->togglePurchased(1);
    expect($toggled->needsPurchasing)->toBeFalse();
});

// ── Chores ──────────────────────────────────────────────────────────────────────

it('lists seeded chores with the typed frequency enum', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->count(2));

    $page = SupportManager::chores()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(Chore::class)
        ->and($page->items()[0]->frequency)->toBe(ChoreFrequency::Weekly)
        ->and($page->items()[0]->completedByName)->toBe('Grace')
        ->and($page->items()[0]->daysSinceCompletion)->toBe(5);
});

it('applies the overdue state to a seeded chore', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->overdue());

    $chore = SupportManager::chores()->find(1);

    expect($chore->isOverdue)->toBeTrue()
        ->and($chore->shouldHighlight)->toBeTrue();
});

it('finds a chore by id and returns null for a miss', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->count(2));

    expect(SupportManager::chores()->find(1)->id)->toBe('1')
        ->and(SupportManager::chores()->find(999))->toBeNull();
});

it('completes a chore', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->overdue());

    $completed = SupportManager::chores()->complete(1);

    expect($completed->isOverdue)->toBeFalse()
        ->and($completed->daysSinceCompletion)->toBe(0);
});

it('snoozes a chore until a date', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->count(1));

    $chore = SupportManager::chores()->snooze(1, '2026-06-20');

    expect($chore->isSnoozed)->toBeTrue()
        ->and($chore->snoozedUntil)->toBe('2026-06-20');
});

// ── Chore categories (read-only) ────────────────────────────────────────────────

it('lists seeded chore categories as DTOs', function () {
    SupportManager::fake()->seed(ChoreCategoryFactory::new()->count(2));

    $page = SupportManager::choreCategories()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(ChoreCategory::class)
        ->and($page->items()[0]->name)->toBe('Category 1')
        ->and($page->items()[0]->choresCount)->toBe(4)
        ->and($page->total())->toBe(2);
});

// ── Meal plan (flat singleton) ──────────────────────────────────────────────────

it('shows the meal plan from a flat data body', function () {
    SupportManager::fake()->seed(MealPlanFactory::new());

    $plan = SupportManager::mealPlan()->show();

    expect($plan)->toBeInstanceOf(MealPlan::class)
        ->and($plan->teamId)->toBe(42)
        ->and($plan->monday)->toBe('Pasta')
        ->and($plan->tuesday)->toBeNull();
});

it('updates meal plan days', function () {
    SupportManager::fake()->seed(MealPlanFactory::new());

    $updated = SupportManager::mealPlan()->update(['tuesday' => 'Curry']);

    expect($updated->tuesday)->toBe('Curry')
        ->and($updated->monday)->toBe('Pasta')
        ->and(SupportManager::mealPlan()->show()->tuesday)->toBe('Curry');
});

it('removes a meal plan day', function () {
    SupportManager::fake()->seed(MealPlanFactory::new()->withDays(['monday' => 'Pasta']));

    expect(SupportManager::mealPlan()->show()->monday)->toBe('Pasta');

    $updated = SupportManager::mealPlan()->removeDay('monday');

    expect($updated->monday)->toBeNull();
});

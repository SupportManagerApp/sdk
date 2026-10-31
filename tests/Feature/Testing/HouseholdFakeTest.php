<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Household\Chore;
use SupportManager\Sdk\Data\Household\ChoreCategory;
use SupportManager\Sdk\Data\Household\Consumable;
use SupportManager\Sdk\Data\Household\MealPlan;
use SupportManager\Sdk\Data\Household\ShoppingListItem;
use SupportManager\Sdk\Enums\Household\ChoreFrequency;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\ChoreCategoryFactory;
use SupportManager\Sdk\Testing\Factories\ChoreFactory;
use SupportManager\Sdk\Testing\Factories\ConsumableFactory;
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

    expect(SupportManager::shoppingList()->all()->items()[0]->needsPurchasing)->toBeTrue();

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

    $chore = SupportManager::chores()->all()->items()[0];

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

it('shows the meal plan from a JSON:API body', function () {
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

// ── Chores: wave 2 bulk actions and permissions ─────────────────────────────────

it('seeds chores as a non-privileged user sees them, or editable', function () {
    SupportManager::fake()->seed(ChoreFactory::new(), ChoreFactory::new()->editable()->inCategory(2, 'Garden'));

    $chores = SupportManager::chores()->all()->items();

    expect($chores[0]->canUpdate)->toBeFalse()
        ->and($chores[0]->canDelete)->toBeFalse()
        ->and($chores[0]->categoryName)->toBeNull()
        ->and($chores[1]->canUpdate)->toBeTrue()
        ->and($chores[1]->canDelete)->toBeTrue()
        ->and($chores[1]->categoryName)->toBe('Garden');
});

it('completes several chores by id', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->overdue()->count(3));

    $completed = SupportManager::chores()->completeMany([1, 3]);

    expect($completed)->toHaveCount(2)
        ->and($completed[0]->isOverdue)->toBeFalse()
        ->and($completed[1]->id)->toBe('3')
        ->and(SupportManager::chores()->all()->items()[1]->isOverdue)->toBeTrue();
});

it('completes every chore in a name group', function () {
    SupportManager::fake()->seed(
        ChoreFactory::new()->overdue()->named('Water plants'),
        ChoreFactory::new()->overdue()->named('  WATER PLANTS '),
        ChoreFactory::new()->overdue()->named('Vacuum'),
    );

    $completed = SupportManager::chores()->completeGroup('water plants');

    expect($completed)->toHaveCount(2)
        ->and($completed[0]->normalizedName)->toBe('water plants')
        ->and(SupportManager::chores()->all()->items()[2]->isOverdue)->toBeTrue();
});

it('marks chores not needed today, snoozing them until tomorrow', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->named('Bins')->count(2));

    $byId = SupportManager::chores()->markNotNeeded([1]);
    $byGroup = SupportManager::chores()->markGroupNotNeeded('bins');

    expect($byId[0]->isSnoozed)->toBeTrue()
        ->and($byId[0]->snoozedUntil)->toBe('2026-01-02')
        ->and($byId[0]->snoozedAt)->toBe('2026-01-01')
        ->and($byGroup)->toHaveCount(2);
});

it('rejects an empty, unknown or ambiguous bulk selection', function () {
    SupportManager::fake()->seed(ChoreFactory::new()->count(1));

    expect(fn () => SupportManager::chores()->completeGroup('Nothing'))->toThrow(ApiValidationException::class)
        ->and(fn () => SupportManager::chores()->completeMany([999]))->toThrow(ApiValidationException::class)
        ->and(fn () => SupportManager::chores()->markNotNeeded([]))->toThrow(ApiValidationException::class);
});

it('reports the due chores count on seeded categories', function () {
    SupportManager::fake()->seed(ChoreCategoryFactory::new()->state(['due_chores_count' => 3]));

    expect(SupportManager::choreCategories()->all()->items()[0]->dueChoresCount)->toBe(3);
});

it('seeds shopping list sections and notes', function () {
    SupportManager::fake()->seed(ShoppingListItemFactory::new()->state(['notes' => 'Semi-skimmed']));

    $item = SupportManager::shoppingList()->all()->items()[0];

    expect($item?->section)->toBe('dairy_eggs_and_chilled')
        ->and($item?->notes)->toBe('Semi-skimmed');
});

// ── Consumables ────────────────────────────────────────────────────────────────

it('lists seeded consumables with their variants', function () {
    SupportManager::fake()->seed(ConsumableFactory::new()->count(2), ConsumableFactory::new()->lowStock());

    $page = SupportManager::consumables()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(Consumable::class)
        ->and($page->items()[0]->name)->toBe('Consumable 1')
        ->and($page->items()[0]->variants)->toHaveCount(2)
        ->and($page->items()[0]->variants[0]->id)->toBe(101)
        ->and($page->items()[0]->variants[0]->isLowStock)->toBeFalse()
        ->and($page->items()[2]->variants[0]->isLowStock)->toBeTrue();
});

it('adjusts a variant\'s stock, never below zero, and flags low stock', function () {
    SupportManager::fake()->seed(ConsumableFactory::new()->withVariants([
        ['id' => 7, 'size' => 'Large', 'quantity' => 2, 'minimum_stock' => 2],
    ]));

    $down = SupportManager::consumables()->adjustStock(7, -1);

    expect($down?->variants[0]->quantity)->toBe(1)
        ->and($down?->variants[0]->isLowStock)->toBeTrue();

    SupportManager::consumables()->adjustStock(7, -1);
    $floored = SupportManager::consumables()->adjustStock(7, -1);

    expect($floored?->variants[0]->quantity)->toBe(0)
        ->and(SupportManager::consumables()->all()->items()[0]->variants[0]->quantity)->toBe(0);

    expect(SupportManager::consumables()->adjustStock(7, 1)?->variants[0]->quantity)->toBe(1);
});

it('rejects an invalid adjustment and an unknown variant', function () {
    SupportManager::fake()->seed(ConsumableFactory::new());

    expect(fn () => SupportManager::consumables()->adjustStock(101, 2))->toThrow(ApiValidationException::class)
        ->and(fn () => SupportManager::consumables()->adjustStock(999, 1))->toThrow(SupportManagerApiException::class);
});

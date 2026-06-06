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
 * Fake household chores.
 *
 * Generic CRUD (list, find, create, update, delete) comes for free from the
 * resource path; this factory adds the two lifecycle actions: {@see complete()}
 * (records the completion) and {@see snooze()} (hides it until a given date).
 *
 * ```php
 * SupportManager::fake()->seed(ChoreFactory::new()->count(3), ChoreFactory::new()->overdue());
 * SupportManager::chores()->complete(1);
 * SupportManager::chores()->snooze(1, '2026-06-20');
 * ```
 */
final class ChoreFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'household/chores';
    }

    public function type(): string
    {
        return 'chores';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'name' => "Chore {$number}",
            'description' => 'Do the thing',
            'frequency' => 'Weekly',
            'frequency_label' => 'Weekly',
            'custom_frequency_days' => null,
            'last_completed' => '2026-01-01',
            'snoozed_until' => null,
            'is_snoozed' => false,
            'completed_by' => ['id' => 3, 'name' => 'Grace'],
            'days_since_completion' => 5,
            'is_overdue' => false,
            'should_highlight' => false,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * Mark the chore as overdue (and worth highlighting).
     */
    public function overdue(): static
    {
        return $this->state([
            'is_overdue' => true,
            'should_highlight' => true,
            'days_since_completion' => 30,
        ]);
    }

    /**
     * Mark the chore as snoozed until the given date.
     */
    public function snoozed(string $until = '2026-06-20'): static
    {
        return $this->state([
            'is_snoozed' => true,
            'snoozed_until' => $until,
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::patch('household/chores/{id}/complete', static function (RecordStore $store, array $matches): mixed {
                $completed = $store->update('household/chores', $matches[1], [
                    'last_completed' => '2026-01-01',
                    'days_since_completion' => 0,
                    'is_overdue' => false,
                    'should_highlight' => false,
                ]);

                return $completed === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('chores', $completed));
            }),

            FakeRoute::patch('household/chores/{id}/snooze', static function (RecordStore $store, array $matches, Request $request): mixed {
                $until = $request->data()['snoozed_until'] ?? null;

                $snoozed = $store->update('household/chores', $matches[1], [
                    'snoozed_until' => $until,
                    'is_snoozed' => $until !== null,
                ]);

                return $snoozed === null
                    ? Http::response(['message' => 'Not found'], 404)
                    : Http::response(Json::item('chores', $snoozed));
            }),
        ];
    }
}

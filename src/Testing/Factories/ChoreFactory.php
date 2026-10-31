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
 * Fake household chores.
 *
 * Generic CRUD (list, find, create, update, delete) comes for free from the
 * resource path; this factory adds the lifecycle actions: `complete()` (records
 * the completion), `snooze()` (hides it until a given date), and the bulk
 * `POST chores/complete` / `POST chores/not-needed`, which select by
 * `chore_ids` or by `group` (chores whose trimmed, lower-cased name matches) and
 * answer the platform's 422s for an empty, ambiguous or unknown selection.
 * "Not needed today" snoozes until the fake's tomorrow, `2026-01-02`.
 *
 * Seeded chores carry `meta.can` flags as a non-privileged user sees them
 * (no update or delete); {@see editable()} flips them as an admin would see.
 * Use {@see named()} rather than a bare `name` state so `normalized_name` (the
 * group key) follows.
 *
 * ```php
 * SupportManager::fake()->seed(ChoreFactory::new()->count(3), ChoreFactory::new()->overdue());
 * SupportManager::chores()->complete(1);
 * SupportManager::chores()->snooze(1, '2026-06-20');
 * SupportManager::chores()->markNotNeeded([1, 2]);
 * ```
 */
final class ChoreFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * What completing a chore (singly or in bulk) does to the stored record.
     */
    private const COMPLETED = [
        'last_completed' => '2026-01-01',
        'days_since_completion' => 0,
        'is_overdue' => false,
        'should_highlight' => false,
    ];

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
            'team_id' => $this->teamId(),
            'name' => "Chore {$number}",
            'normalized_name' => "chore {$number}",
            'description' => 'Do the thing',
            'category' => null,
            'frequency' => 'Weekly',
            'frequency_label' => 'Weekly',
            'custom_frequency_days' => null,
            'last_completed' => '2026-01-01',
            'snoozed_until' => null,
            'is_snoozed' => false,
            'snoozed_at' => null,
            'planned_for' => null,
            'completed_by' => ['id' => 3, 'name' => 'Grace'],
            'days_since_completion' => 5,
            'is_overdue' => false,
            'should_highlight' => false,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
            'meta' => ['can' => ['update' => false, 'delete' => false]],
        ];
    }

    /**
     * Name the chore, keeping its `normalized_name` group key in step.
     */
    public function named(string $name): static
    {
        return $this->state(['name' => $name, 'normalized_name' => self::normalize($name)]);
    }

    /**
     * File the chore in a category.
     */
    public function inCategory(int $id, string $name, ?string $color = '#3b82f6', ?string $icon = 'heroicon-o-home'): static
    {
        return $this->state(['category' => ['id' => $id, 'name' => $name, 'color' => $color, 'icon' => $icon]]);
    }

    /**
     * Seed the chore as an admin or manager sees it: editable and deletable.
     */
    public function editable(): static
    {
        return $this->state(['meta' => ['can' => ['update' => true, 'delete' => true]]]);
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
            FakeRoute::action('PATCH', 'household/chores/{id}/complete', static fn (): array => self::COMPLETED),

            FakeRoute::action('PATCH', 'household/chores/{id}/snooze', static function (array $chore, Request $request): array {
                $until = $request->data()['snoozed_until'] ?? null;

                return [
                    'snoozed_until' => $until,
                    'is_snoozed' => $until !== null,
                    'snoozed_at' => '2026-01-01',
                    'planned_for' => null,
                ];
            }),

            FakeRoute::post('household/chores/complete', static fn (RecordStore $store, array $matches, Request $request, Fake $fake): mixed => self::bulk(
                $store,
                $request,
                $fake,
                self::COMPLETED,
            )),

            FakeRoute::post('household/chores/not-needed', static fn (RecordStore $store, array $matches, Request $request, Fake $fake): mixed => self::bulk(
                $store,
                $request,
                $fake,
                ['snoozed_until' => '2026-01-02', 'is_snoozed' => true, 'snoozed_at' => '2026-01-01', 'planned_for' => null],
            )),
        ];
    }

    /**
     * Apply one update to every chore a bulk request selects, answering the
     * collection of updated chores — or the platform's 422 for a selection that
     * is missing, sends both `chore_ids` and `group`, or matches nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function bulk(RecordStore $store, Request $request, Fake $fake, array $attributes): mixed
    {
        $body = $request->data();
        $ids = $body['chore_ids'] ?? null;
        $group = $body['group'] ?? null;

        if (($ids === null) === ($group === null)) {
            return Json::validation(['chore_ids' => 'Send either chore_ids or group.']);
        }

        $selected = [];

        foreach ($store->all('household/chores') as $chore) {
            $inSelection = is_array($ids)
                ? in_array((int) $chore['id'], array_map(intval(...), $ids), true)
                : self::normalize((string) ($chore['name'] ?? '')) === self::normalize((string) $group);

            if ($inSelection) {
                $selected[] = (string) $chore['id'];
            }
        }

        if ($group !== null && $selected === []) {
            return Json::validation(['group' => 'There are no chores in that group.']);
        }

        if (is_array($ids) && ($ids === [] || count($selected) !== count(array_unique($ids)))) {
            return Json::validation(['chore_ids' => 'The selected chore ids are invalid.']);
        }

        $updated = array_map(
            static fn (string $id): array => $store->update('household/chores', $id, $attributes) ?? [],
            $selected,
        );

        return Http::response(Json::collection('chores', $updated, ['team_id' => $fake->teamId()]));
    }

    /**
     * A chore name as the platform groups it: trimmed and lower-cased.
     */
    private static function normalize(string $name): string
    {
        return mb_strtolower(mb_trim($name));
    }
}

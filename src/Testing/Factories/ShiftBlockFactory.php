<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\NestedResource;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake sleepovers (the platform's shift blocks).
 *
 * Each seeded record is one sleepover carrying the `start_options` its
 * start-options feed returns. Its segments are ordinary shifts, seeded with
 * {@see ShiftFactory::sleepoverSegment()} — `apply()` creates one pending
 * {@see ShiftApplicationFactory} application per segment and `cancel()`
 * removes them, so seed both alongside this one. Like the platform, `apply()`
 * 422s when a segment isn't open or is already applied for, or when
 * `start_time` isn't `H:i`; `cancel()` 422s when nothing is pending.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     ShiftBlockFactory::new(),
 *     ShiftFactory::new()->sleepoverSegment(1)->count(3),
 *     ShiftApplicationFactory::new()->count(0),
 * );
 * SupportManager::shiftBlocks()->startOptions(1);
 * SupportManager::shiftBlocks()->apply(1, '20:00');
 * SupportManager::shiftBlocks()->cancel(1);
 * ```
 */
final class ShiftBlockFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/shift-blocks';
    }

    public function type(): string
    {
        return 'shift_blocks';
    }

    public function definition(int $index): array
    {
        return [
            'start_options' => [
                ['time' => '19:00', 'label' => '7:00 PM', 'compliant' => true, 'reason' => null],
                ['time' => '20:00', 'label' => '8:00 PM', 'compliant' => true, 'reason' => null],
                ['time' => '21:00', 'label' => '9:00 PM', 'compliant' => false, 'reason' => 'Starting then leaves less than 11 hours of rest.'],
            ],
        ];
    }

    /**
     * A sleepover with no evening hours to trim, so no start options.
     */
    public function withoutStartOptions(): static
    {
        return $this->state(['start_options' => []]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('rota/shift-blocks/{id}/start-options', static function (RecordStore $store, array $matches): mixed {
                $block = $store->find('rota/shift-blocks', $matches[1]);

                if ($block === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                return Http::response(['data' => $block['start_options'] ?? [], 'meta' => ['team_id' => 42]]);
            }),

            FakeRoute::post('rota/shift-blocks/{id}/application', static function (RecordStore $store, array $matches, Request $request): mixed {
                if ($store->find('rota/shift-blocks', $matches[1]) === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $startTime = $request->data()['start_time'] ?? null;

                if ($startTime !== null && (! is_string($startTime) || preg_match('/^\d{2}:\d{2}$/', $startTime) !== 1)) {
                    return Http::response([
                        'message' => 'The information provided was invalid.',
                        'errors' => ['start_time' => ['The start time must be in HH:MM format.']],
                    ], 422);
                }

                $segments = self::segments($store, $matches[1]);

                if ($segments === [] || array_filter($segments, static fn (array $shift): bool => ! ($shift['is_available'] ?? false)) !== []) {
                    return Http::response(['message' => 'This sleepover is not open for application.'], 422);
                }

                if (self::pendingApplications($store, $matches[1]) !== []) {
                    return Http::response(['message' => 'You have already applied for this sleepover.'], 422);
                }

                $applications = [];

                foreach ($segments as $position => $shift) {
                    $applications[] = $store->append('rota/shift-applications', [
                        'id' => $store->nextId('rota/shift-applications'),
                        'team_id' => 42,
                        'status' => 'Pending',
                        // A later start trims the evening, the first segment.
                        'start_time' => $position === 0 && $startTime !== null ? $startTime : ShiftFactory::clockTime($shift['start'] ?? null),
                        'end_time' => ShiftFactory::clockTime($shift['end'] ?? null),
                        'user' => ['id' => 7, 'name' => 'Applicant'],
                        'rota_entry' => Json::nested('rota-entries', $shift),
                        'reviewed_by' => null,
                        'created_at' => '2026-06-06T09:00:00Z',
                        'updated_at' => '2026-06-06T09:00:00Z',
                    ]);
                }

                $count = count($applications);

                return Http::response([
                    ...Json::collection('shift-applications', $applications),
                    'message' => "Your application for the sleepover ({$count} parts) has been submitted for review.",
                    'meta' => ['team_id' => 42],
                ], 201);
            }),

            FakeRoute::delete('rota/shift-blocks/{id}/application', static function (RecordStore $store, array $matches): mixed {
                if ($store->find('rota/shift-blocks', $matches[1]) === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $pending = self::pendingApplications($store, $matches[1]);

                if ($pending === []) {
                    return Http::response(['message' => 'You have no pending application for this sleepover.'], 422);
                }

                foreach ($pending as $application) {
                    $store->delete('rota/shift-applications', (string) $application['id']);
                }

                return Http::response(['message' => 'Your sleepover application has been withdrawn.', 'meta' => ['team_id' => 42]]);
            }),
        ];
    }

    /**
     * The seeded shifts that are segments of the given sleepover.
     *
     * @return list<array<string, mixed>>
     */
    private static function segments(RecordStore $store, string $blockId): array
    {
        return array_values(array_filter(
            $store->all('rota/shifts'),
            static fn (array $shift): bool => (string) ($shift['shift_block_id'] ?? '') === $blockId,
        ));
    }

    /**
     * The pending applications for the given sleepover's segments.
     *
     * @return list<array<string, mixed>>
     */
    private static function pendingApplications(RecordStore $store, string $blockId): array
    {
        return array_values(array_filter(
            $store->all('rota/shift-applications'),
            static function (array $application) use ($blockId): bool {
                /** @var array<string, mixed> $entry */
                $entry = is_array($application['rota_entry'] ?? null) ? $application['rota_entry'] : [];

                return ($application['status'] ?? null) === 'Pending'
                    && (string) (NestedResource::flatten($entry)['shift_block_id'] ?? '') === $blockId;
            },
        ));
    }
}

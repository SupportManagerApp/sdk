<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use DateTimeImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake contractors' not-working notices.
 *
 * A JSON:API collection (type `not-working-notices`) with custom routes for all
 * three endpoints. The index honours `filter[show]`: `upcoming` (the default)
 * lists standing notices that have not ended, soonest first; `past` those that
 * have ended and `withdrawn` the withdrawn ones, latest first. "Ended" is judged
 * against the real clock, so the default notice is set well in the future and
 * {@see past()} well in the past.
 *
 * Recording a notice works out its start, end and last day off the way the
 * platform does (whole days run from 08:00 on the first day to 08:00 the day
 * after the last) and answers a 422 when `duration_type` or `start_date` is
 * missing. Withdrawing marks the notice `Cancelled`, clears its
 * `meta.can.withdraw`, and answers a 403 for one already withdrawn.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     NotWorkingNoticeFactory::new()->count(2),
 *     NotWorkingNoticeFactory::new()->withdrawn(),
 * );
 * SupportManager::leaveNotWorkingNotices()->withdraw(1);
 * ```
 */
final class NotWorkingNoticeFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'leave/not-working';
    }

    public function type(): string
    {
        return 'not-working-notices';
    }

    public function definition(int $index): array
    {
        $start = (new DateTimeImmutable('2099-11-09'))->modify('+'.($index * 7).' days');

        return [
            'team_id' => $this->teamId(),
            'user_id' => 9,
            'user' => ['id' => 9, 'name' => 'Sam Carter'],
            ...self::period('Whole Days', $start->format('Y-m-d'), $start->modify('+2 days')->format('Y-m-d'), null, null),
            'duration_type' => 'Whole Days',
            'status' => 'Approved',
            'note' => null,
            'given_at_short_notice' => false,
            'shifts_needing_cover_count' => 0,
            'created_at' => '2026-10-01T09:00:00.000000Z',
            'updated_at' => '2026-10-01T09:00:00.000000Z',
            'meta' => ['can' => ['withdraw' => true]],
        ];
    }

    /**
     * A notice that has already ended.
     */
    public function past(): static
    {
        return $this->state(self::period('Whole Days', '2020-03-02', '2020-03-04', null, null));
    }

    /**
     * A withdrawn notice.
     */
    public function withdrawn(): static
    {
        return $this->state(['status' => 'Cancelled', 'meta' => ['can' => ['withdraw' => false]]]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('leave/not-working', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $filter = $fake->query($request)['filter'] ?? [];
                $show = is_array($filter) && is_string($filter['show'] ?? null) ? $filter['show'] : 'upcoming';
                $now = gmdate('Y-m-d\\TH:i:s+00:00');

                $records = array_values(array_filter(
                    $store->all('leave/not-working'),
                    static fn (array $notice): bool => match ($show) {
                        'withdrawn' => $notice['status'] === 'Cancelled',
                        'past' => $notice['status'] !== 'Cancelled' && (string) $notice['end_date'] <= $now,
                        default => $notice['status'] !== 'Cancelled' && (string) $notice['end_date'] > $now,
                    },
                ));

                usort($records, static fn (array $a, array $b): int => $show === 'upcoming'
                    ? strcmp((string) $a['start_date'], (string) $b['start_date'])
                    : strcmp((string) $b['start_date'], (string) $a['start_date']));

                return $fake->paginate('leave/not-working', 'not-working-notices', $records, $request, ['team_id' => $fake->teamId()]);
            }),

            FakeRoute::post('leave/not-working', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $data = $request->data();
                $durationType = is_string($data['duration_type'] ?? null) ? $data['duration_type'] : null;
                $startDate = is_string($data['start_date'] ?? null) ? $data['start_date'] : null;

                if ($durationType === null || $startDate === null) {
                    return Json::validation(array_filter([
                        'duration_type' => $durationType === null ? 'The duration type field is required.' : null,
                        'start_date' => $startDate === null ? 'The start date field is required.' : null,
                    ]), 'The duration type and start date are required.');
                }

                $contractorId = (int) ($data['contractor_id'] ?? 9);
                $record = $store->append('leave/not-working', [
                    'id' => $store->nextId('leave/not-working'),
                    'team_id' => $fake->teamId(),
                    'user_id' => $contractorId,
                    'user' => ['id' => $contractorId, 'name' => "User {$contractorId}"],
                    ...self::period(
                        $durationType,
                        $startDate,
                        is_string($data['end_date'] ?? null) ? $data['end_date'] : $startDate,
                        is_string($data['start_time'] ?? null) ? $data['start_time'] : null,
                        is_string($data['end_time'] ?? null) ? $data['end_time'] : null,
                    ),
                    'duration_type' => $durationType,
                    'status' => 'Approved',
                    'note' => is_string($data['note'] ?? null) ? $data['note'] : null,
                    'given_at_short_notice' => false,
                    'shifts_needing_cover_count' => 0,
                    'created_at' => '2026-10-01T09:00:00.000000Z',
                    'updated_at' => '2026-10-01T09:00:00.000000Z',
                    'meta' => ['can' => ['withdraw' => true]],
                ]);

                return Http::response(Json::item('not-working-notices', $record), 201);
            }),

            FakeRoute::action('PATCH', 'leave/not-working/{id}/withdraw', static fn (array $notice): array|PromiseInterface => $notice['status'] === 'Cancelled'
                ? Json::forbidden()
                : ['status' => 'Cancelled', 'meta' => ['can' => ['withdraw' => false]]]),
        ];
    }

    /**
     * The `start_date`, `end_date` and `last_day_off` of a notice, as the
     * platform serialises them.
     *
     * @return array{start_date: string, end_date: string, last_day_off: string}
     */
    private static function period(string $durationType, string $startDate, string $endDate, ?string $startTime, ?string $endTime): array
    {
        if ($durationType === 'Custom Timings') {
            return [
                'start_date' => "{$startDate}T".($startTime ?? '00:00').':00+00:00',
                'end_date' => "{$startDate}T".($endTime ?? '00:00').':00+00:00',
                'last_day_off' => $startDate,
            ];
        }

        $dayAfter = (new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d');

        return [
            'start_date' => "{$startDate}T08:00:00+00:00",
            'end_date' => "{$dayAfter}T08:00:00+00:00",
            'last_day_off' => $endDate,
        ];
    }
}

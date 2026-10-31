<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\NestedResource;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake rota shifts (the platform's `rota_entries`).
 *
 * Generic CRUD (list, find, update, delete) comes for free from the resource
 * path; this factory adds the module's read feeds — {@see today()}'s shifts,
 * the {@see upcoming()} window and the unassigned {@see available()} shifts —
 * plus the `apply()` action, which returns the created
 * {@see ShiftApplicationFactory} shape (and 422s a {@see sleepoverSegment()},
 * as the platform does), and `requestAmendment()`, which records a pending
 * {@see ShiftAmendmentRequestFactory} request (422 without both proposed
 * times, 403 while one is already pending for the shift).
 *
 * `create()` is answered by a custom route rather than generic REST, because
 * the platform takes quick-add fields (`assignee_id`, `date`, `start_time`, …)
 * and stores a rota entry: it validates the required fields (422), defaults a
 * missing end to an hour, rolls an end at or before the start overnight, and
 * records published overtime (a sleeping night when `is_sleeping_night`).
 * A `pay_enhancement_id` must name a seeded {@see PayEnhancementFactory}
 * enhancement (422 otherwise), which the shift then carries.
 *
 * The manager actions, `update()`, `destroy()`, `duplicate()` and `assign()`,
 * are custom routes too, and each answers 403 unless the stored shift's
 * matching `meta.can` flag is set, so seed {@see manageable()} shifts to drive
 * them. `update()` applies the fields sent as the platform does (re-timing on
 * the shift's day, rolling an early end overnight, closing an assigned shift
 * to applications) and 422s a shift seeded {@see onCompletedPayslip()} until
 * `reset_payslip` is sent, then answers the payslip warning in `meta`. A
 * {@see recurring()} shift's update or delete with the future scope reaches
 * the later shifts of its series, and with the single scope detaches it.
 * `duplicate()` copies onto the given day, and both it and `assign()` act on
 * a whole sleepover when given one of its segments; `assign()` also approves
 * the person's pending application and turns down the rest.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     ShiftFactory::new()->count(3),
 *     ShiftFactory::new()->available(),
 *     ShiftFactory::new()->upcoming(),
 *     ShiftFactory::new()->manageable(),
 * );
 * SupportManager::shifts()->today();
 * SupportManager::shifts()->upcoming(['limit' => 5]);
 * SupportManager::shifts()->create(['assignee_id' => 7, 'date' => '2026-08-17', 'start_time' => '22:00', 'end_time' => '07:00']);
 * SupportManager::shifts()->apply($id);
 * SupportManager::shifts()->requestAmendment($id, ['proposed_start_time' => '15:00', 'proposed_end_time' => '23:00']);
 * SupportManager::shifts()->update($id, ['start_time' => '22:00', 'end_time' => '06:00']);
 * SupportManager::shifts()->duplicate($id, '2026-06-13');
 * SupportManager::shifts()->assign($id, 12);
 * SupportManager::shifts()->destroy($id);
 * ```
 */
final class ShiftFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * The platform's answer to applying for, or withdrawing, one segment of a
     * sleepover on its own.
     */
    public const string SLEEPOVER_SEGMENT_MESSAGE = 'This shift is part of a sleepover, which is applied for and retracted as a whole.';

    /**
     * The `meta.can` flags a manager gets on every shift.
     */
    private const array MANAGER_CAN = ['update' => true, 'delete' => true, 'duplicate' => true, 'assign' => true];

    /**
     * The entry types a shift can be edited to (`RotaEntryType::shiftTypes()`).
     */
    private const array SHIFT_TYPES = ['Contracted Hours', 'Overtime Hours', 'Contracted Sleeping Night', 'Overtime Sleeping Night'];

    private bool $manageable = false;

    /**
     * The `HH:MM` wall-clock time of a shift timestamp, as a shift application's
     * `start_time`/`end_time` carry it (ApplyForShiftAction stores `H:i`).
     */
    public static function clockTime(mixed $timestamp): ?string
    {
        return is_string($timestamp) && preg_match('/T(\d{2}:\d{2})/', $timestamp, $m) === 1 ? $m[1] : null;
    }

    public function path(): string
    {
        return 'rota/shifts';
    }

    public function type(): string
    {
        return 'rota_entries';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => $this->teamId(),
            'type' => 'Contracted Hours',
            'type_label' => 'Contracted Hours',
            'start' => '2026-06-06T09:00:00Z',
            'end' => '2026-06-06T17:00:00Z',
            'duration' => 8.0,
            'is_published' => true,
            'is_available' => false,
            'is_sleeping_night' => false,
            'is_overtime' => false,
            'is_for_toil' => false,
            'is_private' => false,
            'shift_block_id' => null,
            'tags' => [],
            'own_application_status' => null,
            'notes' => null,
            'pay_enhancement' => null,
            'user' => ['id' => 7, 'name' => "User {$number}", 'avatar' => 'https://example.test/avatar.png'],
            'created_at' => '2026-06-06T09:00:00Z',
            'updated_at' => '2026-06-06T09:00:00Z',
            'meta' => [
                'can' => ['apply' => false, 'request_amendment' => true, ...self::workerCan()],
                'cannot_apply_reason' => 'You cannot apply for this shift.',
            ],
        ];
    }

    /**
     * Build the records, with the manager's `meta.can` flags merged in when
     * {@see manageable()} asked for them, whatever other states set `meta`.
     */
    public function make(): array
    {
        $records = parent::make();

        if (! $this->manageable) {
            return $records;
        }

        return array_map(static function (array $record): array {
            $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];
            $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

            return [...$record, 'meta' => [...$meta, 'can' => [...$can, ...self::MANAGER_CAN]]];
        }, $records);
    }

    /**
     * Seen by an Admin or Manager: `canUpdate`, `canDelete`, `canDuplicate`
     * and `canAssign` are true, so the manager actions are open. Combines with
     * any other state.
     */
    public function manageable(): static
    {
        $clone = clone $this;
        $clone->manageable = true;

        return $clone;
    }

    /**
     * Give the shift a pay enhancement, as the rota entry's `pay_enhancement`
     * summary.
     */
    public function withPayEnhancement(int $id = 1, string $name = 'Enhancement 1', string $factor = '0.50', bool $isPrivate = false): static
    {
        return $this->state(['pay_enhancement' => ['id' => $id, 'name' => $name, 'factor' => $factor, 'is_private' => $isPrivate]]);
    }

    /**
     * Make the shift one of a repeating series, so a future-scoped update or
     * delete reaches the later shifts seeded into the same series.
     */
    public function recurring(int|string $seriesId = 1): static
    {
        return $this->state(['_series' => (string) $seriesId]);
    }

    /**
     * Put the shift on a completed payslip for `$month` (e.g. "October 2026"),
     * so an update is refused until it sends `reset_payslip`.
     */
    public function onCompletedPayslip(string $month = 'October 2026'): static
    {
        return $this->state(['_completed_payslip' => $month]);
    }

    /**
     * Mark the shift as today's date so {@see today()} returns it.
     */
    public function today(): static
    {
        return $this->state(['_today' => true]);
    }

    /**
     * Mark the shift as still to come so {@see upcoming()} returns it.
     */
    public function upcoming(): static
    {
        return $this->state(['_upcoming' => true]);
    }

    /**
     * Mark the shift as unassigned and open for application.
     */
    public function available(): static
    {
        return $this->state([
            'is_available' => true,
            'user' => null,
            '_applications_count' => 0,
            'meta' => [
                'can' => ['apply' => true, 'request_amendment' => false, ...self::workerCan()],
                'cannot_apply_reason' => null,
            ],
        ]);
    }

    /**
     * Make the shift an open segment of the given sleepover (shift block),
     * applied for as a whole through {@see ShiftBlockFactory}.
     */
    public function sleepoverSegment(int $shiftBlockId): static
    {
        return $this->available()->state(['shift_block_id' => $shiftBlockId]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('rota/shifts', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $input = $request->data();
                $missing = array_values(array_filter(
                    ['assignee_id', 'date', 'start_time'],
                    static fn (string $field): bool => ! isset($input[$field]) || $input[$field] === '',
                ));

                if ($missing !== []) {
                    return Json::validation(array_fill_keys($missing, 'This field is required.'), Json::INVALID);
                }

                $start = self::wallClock((string) $input['date'], (string) $input['start_time']);
                $end = isset($input['end_time'])
                    ? self::wallClock((string) $input['date'], (string) $input['end_time'])
                    : $start->modify('+1 hour');

                // "10pm to 7am" is an overnight shift, not a negative one.
                if ($end <= $start) {
                    $end = $end->modify('+1 day');
                }

                $payEnhancement = self::payEnhancement($store, $input, null);

                if ($payEnhancement instanceof PromiseInterface) {
                    return $payEnhancement;
                }

                $sleepingNight = (bool) ($input['is_sleeping_night'] ?? false);
                $type = $sleepingNight ? 'Overtime Sleeping Night' : 'Overtime Hours';

                $shift = $store->append('rota/shifts', [
                    'id' => $store->nextId('rota/shifts'),
                    'team_id' => $fake->teamId(),
                    'user_id' => (int) $input['assignee_id'],
                    'type' => $type,
                    'type_label' => $type,
                    'start' => $start->format(DATE_ATOM),
                    'end' => $end->format(DATE_ATOM),
                    'duration' => ($end->getTimestamp() - $start->getTimestamp()) / 3600,
                    'is_published' => true,
                    'is_available' => false,
                    'is_sleeping_night' => $sleepingNight,
                    'is_overtime' => true,
                    'is_private' => (bool) ($input['is_private'] ?? false),
                    'notes' => $input['notes'] ?? null,
                    'tags' => $input['tags'] ?? [],
                    'pay_enhancement' => $payEnhancement,
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                ]);

                return Http::response(Json::item('rota_entries', $shift), 201);
            }),

            FakeRoute::get('rota/shifts/upcoming', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $query = $fake->query($request);
                $limit = max(1, min((int) ($query['limit'] ?? 10), 50));

                $records = array_slice(array_values(array_filter(
                    $store->all('rota/shifts'),
                    static fn (array $r): bool => (bool) ($r['_upcoming'] ?? false),
                )), 0, $limit);

                // Never paginated: the whole answer is the one body.
                return Http::response(Json::flat(Json::collection('rota_entries', $records)['data'], [
                    'team_id' => $fake->teamId(),
                    'mode' => in_array($query['mode'] ?? null, ['scheduled', 'team', 'available'], true) ? $query['mode'] : 'scheduled',
                    'days' => max(1, min((int) ($query['days'] ?? 2), 30)),
                    'limit' => $limit,
                ]));
            }),

            FakeRoute::collection('rota/shifts/today', static fn (RecordStore $store): array => array_values(array_filter(
                $store->all('rota/shifts'),
                static fn (array $r): bool => (bool) ($r['_today'] ?? false),
            ))),

            // Only this endpoint counts applications (withCount for privileged users).
            FakeRoute::collection('rota/shifts/available', static fn (RecordStore $store): array => array_map(
                static fn (array $r): array => [...$r, 'shift_applications_count' => $r['_applications_count'] ?? 0],
                array_values(array_filter(
                    $store->all('rota/shifts'),
                    static fn (array $r): bool => (bool) ($r['is_available'] ?? false),
                )),
            )),

            FakeRoute::post('rota/shifts/{id}/apply', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $shift = $store->find('rota/shifts', $matches[1]);

                if ($shift === null) {
                    return Json::notFound();
                }

                if (($shift['shift_block_id'] ?? null) !== null) {
                    return Json::message(self::SLEEPOVER_SEGMENT_MESSAGE, 422);
                }

                $application = [
                    'id' => $store->nextId('rota/shift-applications'),
                    'team_id' => $fake->teamId(),
                    'status' => 'Pending',
                    'start_time' => self::clockTime($shift['start'] ?? null),
                    'end_time' => self::clockTime($shift['end'] ?? null),
                    'user' => ['id' => 7, 'name' => 'Applicant'],
                    'rota_entry' => Json::nested('rota_entries', $shift),
                    'reviewed_by' => null,
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                ];

                $store->append('rota/shift-applications', $application);
                $store->update('rota/shifts', (string) $shift['id'], ['own_application_status' => 'Pending']);

                return Http::response(Json::collection('shift_applications', [$application]), 201);
            }),

            FakeRoute::post('rota/shifts/{id}/amendment-requests', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $shift = $store->find('rota/shifts', $matches[1]);

                if ($shift === null) {
                    return Json::notFound();
                }

                $input = $request->data();
                $missing = array_values(array_filter(
                    ['proposed_start_time', 'proposed_end_time'],
                    static fn (string $field): bool => ! isset($input[$field]) || $input[$field] === '',
                ));

                if ($missing !== []) {
                    return Json::validation(array_fill_keys($missing, 'This field is required.'), Json::INVALID);
                }

                foreach ($store->all('rota/shift-amendment-requests') as $existing) {
                    if ((string) ($existing['rota_entry_id'] ?? '') === (string) $shift['id'] && ($existing['status'] ?? null) === 'Pending') {
                        return Json::forbidden();
                    }
                }

                // The proposed times land on the day the shift itself starts.
                $date = mb_substr((string) ($shift['start'] ?? '2026-06-06'), 0, 10);
                $start = self::wallClock($date, (string) $input['proposed_start_time']);
                $end = self::wallClock($date, (string) $input['proposed_end_time']);

                if ($end <= $start) {
                    $end = $end->modify('+1 day');
                }

                $amendment = $store->append('rota/shift-amendment-requests', [
                    'id' => $store->nextId('rota/shift-amendment-requests'),
                    'team_id' => $fake->teamId(),
                    'user_id' => 7,
                    'rota_entry_id' => (int) $shift['id'],
                    'proposed_start' => $start->format(DATE_ATOM),
                    'proposed_end' => $end->format(DATE_ATOM),
                    'requested_pay_enhancement_id' => $input['requested_pay_enhancement_id'] ?? null,
                    'requested_tags' => $input['requested_tags'] ?? [],
                    'reason' => $input['reason'] ?? null,
                    'status' => 'Pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'created_at' => '2026-06-06T09:00:00Z',
                    'updated_at' => '2026-06-06T09:00:00Z',
                ]);

                return Http::response(Json::item('shift_amendment_requests', $amendment), 201);
            }),

            FakeRoute::patch('rota/shifts/{id}', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $shift = $store->find('rota/shifts', $matches[1]);

                if ($shift === null) {
                    return Json::notFound();
                }

                if (! self::can($shift, 'update')) {
                    return Json::forbidden();
                }

                $input = $request->data();
                $errors = self::editErrors($input);

                if ($errors !== []) {
                    return Json::validation($errors);
                }

                $payEnhancement = self::payEnhancement($store, $input, $shift['pay_enhancement'] ?? null);

                if ($payEnhancement instanceof PromiseInterface) {
                    return $payEnhancement;
                }

                $payslip = $shift['_completed_payslip'] ?? null;
                $warnings = [];

                if (is_string($payslip)) {
                    if (($input['reset_payslip'] ?? false) !== true) {
                        return Json::validation(['reset_payslip' => "This shift is on the completed {$payslip} payslip. Confirm with reset_payslip to put that payslip back to draft."]);
                    }

                    $warnings[] = "{$payslip} payslip reset to draft - please regenerate and resubmit FPS.";
                }

                $future = ($input['scope'] ?? 'single') === 'future';

                // The later shifts keep their own days; only this one moves date.
                foreach ($future ? self::laterInSeries($store, $shift) : [] as $later) {
                    $store->update('rota/shifts', (string) $later['id'], self::edited($store, $later, [...$input, 'date' => null], self::payEnhancement($store, $input, $later['pay_enhancement'] ?? null)));
                }

                $updated = $store->update('rota/shifts', (string) $shift['id'], [
                    ...self::edited($store, $shift, $input, $payEnhancement),
                    '_completed_payslip' => null,
                    // A single-scope edit takes the shift out of its series.
                    '_series' => $future ? ($shift['_series'] ?? null) : null,
                ]) ?? $shift;

                return Http::response([
                    ...Json::item('rota_entries', $updated),
                    'meta' => ['team_id' => $fake->teamId(), 'warnings' => $warnings],
                ]);
            }),

            FakeRoute::delete('rota/shifts/{id}', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $shift = $store->find('rota/shifts', $matches[1]);

                if ($shift === null) {
                    return Json::notFound();
                }

                if (! self::can($shift, 'delete')) {
                    return Json::forbidden();
                }

                $scope = $fake->query($request)['scope'] ?? 'single';

                if (! in_array($scope, ['single', 'future'], true)) {
                    return Json::validation(['scope' => 'The selected scope is invalid.']);
                }

                $later = $scope === 'future' ? self::laterInSeries($store, $shift) : [];
                $deletesTheRestOfTheSeries = $scope === 'future' && ($shift['_series'] ?? null) !== null;

                foreach ([$shift, ...$later] as $doomed) {
                    $store->delete('rota/shifts', (string) $doomed['id']);
                }

                return Json::message($deletesTheRestOfTheSeries ? 'This and future shifts deleted.' : 'Shift deleted.', 200, ['team_id' => $fake->teamId()]);
            }),

            FakeRoute::post('rota/shifts/{id}/duplicate', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $shift = $store->find('rota/shifts', $matches[1]);

                if ($shift === null) {
                    return Json::notFound();
                }

                if (! self::can($shift, 'duplicate')) {
                    return Json::forbidden();
                }

                $date = $request->data()['date'] ?? self::dayOf($shift['start'] ?? null);

                if (! is_string($date) || ! self::isDate($date)) {
                    return Json::validation(['date' => 'The date field must be a valid date.']);
                }

                $days = self::daysBetween(self::dayOf($shift['start'] ?? null), $date);
                $blockId = $shift['shift_block_id'] ?? null;
                $newBlockId = $blockId === null ? null : (int) $store->nextId('rota/shift-blocks');
                $userId = self::userIdOf($shift);
                $copies = [];

                // A sleepover segment copies the whole sleepover, with the
                // person from the segment that was picked on every segment.
                foreach (self::segmentsOf($store, $shift) as $original) {
                    $copies[(string) $original['id']] = $store->append('rota/shifts', [
                        ...array_filter($original, static fn (string|int $key): bool => ! is_string($key) || ! str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY),
                        'id' => $store->nextId('rota/shifts'),
                        'user_id' => $userId,
                        'user' => $shift['user'] ?? null,
                        'start' => self::movedOn($original['start'] ?? null, $days),
                        'end' => self::movedOn($original['end'] ?? null, $days),
                        'shift_block_id' => $newBlockId,
                        'is_published' => true,
                        'is_available' => $userId === null && (bool) ($original['is_available'] ?? false),
                        'own_application_status' => null,
                        'created_at' => '2026-06-06T09:00:00Z',
                        'updated_at' => '2026-06-06T09:00:00Z',
                    ]);
                }

                $sleepingNight = array_values(array_filter($copies, static fn (array $copy): bool => (bool) ($copy['is_sleeping_night'] ?? false)));
                $answer = $blockId !== null && $sleepingNight !== [] ? $sleepingNight[0] : $copies[(string) $shift['id']];

                return Http::response([...Json::item('rota_entries', $answer), 'meta' => ['team_id' => $fake->teamId()]], 201);
            }),

            FakeRoute::post('rota/shifts/{id}/assignment', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $shift = $store->find('rota/shifts', $matches[1]);

                if ($shift === null) {
                    return Json::notFound();
                }

                if (! self::can($shift, 'assign')) {
                    return Json::forbidden();
                }

                $userId = $request->data()['user_id'] ?? null;

                if (! is_int($userId)) {
                    return Json::validation(['user_id' => 'The user id field is required.']);
                }

                $user = self::userSummary($store, $userId);
                $assigned = [];

                // A sleepover segment staffs the whole sleepover.
                foreach (self::segmentsOf($store, $shift) as $segment) {
                    $assigned[] = (string) $segment['id'];
                    $store->update('rota/shifts', (string) $segment['id'], ['user_id' => $userId, 'user' => $user, 'is_available' => false]);
                }

                foreach ($store->all('rota/shift-applications') as $application) {
                    $entry = is_array($application['rota_entry'] ?? null) ? NestedResource::flatten($application['rota_entry']) : [];
                    $applicant = is_array($application['user'] ?? null) ? ($application['user']['id'] ?? null) : null;

                    if (in_array((string) ($entry['id'] ?? ''), $assigned, true) && ($application['status'] ?? null) === 'Pending') {
                        $store->update('rota/shift-applications', (string) ($application['id'] ?? ''), [
                            'status' => $applicant === $userId ? 'Approved' : 'Rejected',
                        ]);
                    }
                }

                $staffed = $store->find('rota/shifts', (string) $shift['id']) ?? $shift;

                return Http::response([...Json::item('rota_entries', $staffed), 'meta' => ['team_id' => $fake->teamId()]]);
            }),
        ];
    }

    /**
     * The manager flags as a team member without admin rights sees them.
     *
     * @return array<string, bool>
     */
    private static function workerCan(): array
    {
        return array_map(static fn (): bool => false, self::MANAGER_CAN);
    }

    /**
     * Whether a stored shift's `meta.can` grants `$ability`.
     *
     * @param  array<string, mixed>  $shift
     */
    private static function can(array $shift, string $ability): bool
    {
        $meta = is_array($shift['meta'] ?? null) ? $shift['meta'] : [];
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        return ($can[$ability] ?? false) === true;
    }

    /**
     * The `UpdateShiftRequest` rule failures for an edit, by field.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private static function editErrors(array $input): array
    {
        $errors = [];

        if (array_key_exists('date', $input) && (! is_string($input['date']) || ! self::isDate($input['date']))) {
            $errors['date'] = 'The date field must be a valid date.';
        }

        foreach (['start_time' => 'start time', 'end_time' => 'end time'] as $field => $label) {
            if (array_key_exists($field, $input) && (! is_string($input[$field]) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $input[$field]) !== 1)) {
                $errors[$field] = "The {$label} field must match the format H:i.";
            }
        }

        if (array_key_exists('type', $input) && ! in_array($input['type'], self::SHIFT_TYPES, true)) {
            $errors['type'] = 'The selected type is invalid.';
        }

        if (array_key_exists('user_id', $input) && $input['user_id'] !== null && ! is_int($input['user_id'])) {
            $errors['user_id'] = 'The user id field must be an integer.';
        }

        if (array_key_exists('scope', $input) && ! in_array($input['scope'], ['single', 'future'], true)) {
            $errors['scope'] = 'The selected scope is invalid.';
        }

        return $errors;
    }

    /**
     * The attributes an edit leaves a stored shift with: whatever was sent,
     * over whatever it already holds.
     *
     * @param  array<string, mixed>  $shift
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|PromiseInterface|null  $payEnhancement
     * @return array<string, mixed>
     */
    private static function edited(RecordStore $store, array $shift, array $input, array|PromiseInterface|null $payEnhancement): array
    {
        $changes = [];

        if (isset($input['date']) || isset($input['start_time']) || isset($input['end_time'])) {
            $date = is_string($input['date'] ?? null) ? $input['date'] : self::dayOf($shift['start'] ?? null);
            $start = self::wallClock($date, (string) ($input['start_time'] ?? self::clockTime($shift['start'] ?? null) ?? '09:00'));
            $end = self::wallClock($date, (string) ($input['end_time'] ?? self::clockTime($shift['end'] ?? null) ?? '17:00'));

            // 22:00 to 06:00 is an overnight shift.
            if ($end <= $start) {
                $end = $end->modify('+1 day');
            }

            $changes['start'] = $start->format(DATE_ATOM);
            $changes['end'] = $end->format(DATE_ATOM);
            $changes['duration'] = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        }

        if (isset($input['type'])) {
            $changes['type'] = $input['type'];
            $changes['type_label'] = $input['type'];
        }

        $userId = self::userIdOf($shift);

        if (array_key_exists('user_id', $input)) {
            $userId = is_int($input['user_id']) ? $input['user_id'] : null;
            $changes['user_id'] = $userId;
            $changes['user'] = $userId === null ? null : self::userSummary($store, $userId);
        }

        foreach (['is_for_toil', 'is_published', 'is_private'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $changes[$flag] = (bool) $input[$flag];
            }
        }

        // Somebody on the shift takes it off the board.
        $changes['is_available'] = $userId === null && (bool) ($input['is_available'] ?? $shift['is_available'] ?? false);

        if (isset($input['tags']) && is_array($input['tags'])) {
            $changes['tags'] = array_values($input['tags']);
        }

        if (! $payEnhancement instanceof PromiseInterface) {
            $changes['pay_enhancement'] = $payEnhancement;
        }

        return [...$changes, 'updated_at' => '2026-06-06T10:00:00Z'];
    }

    /**
     * The `pay_enhancement` summary a create or edit leaves a shift with, or
     * the 422 for an enhancement that isn't seeded. Without a
     * `pay_enhancement_id` the shift keeps `$current`.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|PromiseInterface|null
     */
    private static function payEnhancement(RecordStore $store, array $input, mixed $current): array|PromiseInterface|null
    {
        $current = is_array($current) ? $current : null;
        $isPrivate = (bool) ($input['is_pay_enhancement_private'] ?? $current['is_private'] ?? false);

        if (! array_key_exists('pay_enhancement_id', $input)) {
            return $current === null ? null : [...$current, 'is_private' => $isPrivate];
        }

        if ($input['pay_enhancement_id'] === null) {
            return null;
        }

        $enhancement = is_int($input['pay_enhancement_id'])
            ? $store->find('payroll/pay-enhancements', (string) $input['pay_enhancement_id'])
            : null;

        if ($enhancement === null) {
            return Json::validation(['pay_enhancement_id' => 'The selected pay enhancement does not belong to this team.']);
        }

        return [
            'id' => (int) $enhancement['id'],
            'name' => (string) ($enhancement['name'] ?? ''),
            'factor' => $enhancement['enhancement_factor'] ?? null,
            'is_private' => $isPrivate,
        ];
    }

    /**
     * The shifts after this one in its repeating series, if it has one.
     *
     * @param  array<string, mixed>  $shift
     * @return list<array<string, mixed>>
     */
    private static function laterInSeries(RecordStore $store, array $shift): array
    {
        $series = $shift['_series'] ?? null;

        if ($series === null) {
            return [];
        }

        $from = strtotime((string) ($shift['start'] ?? '')) ?: 0;

        return array_values(array_filter(
            $store->all('rota/shifts'),
            static fn (array $other): bool => ($other['_series'] ?? null) === $series
                && (string) $other['id'] !== (string) $shift['id']
                && (strtotime((string) ($other['start'] ?? '')) ?: 0) > $from,
        ));
    }

    /**
     * Every segment of the shift's sleepover, or just the shift when it isn't
     * one.
     *
     * @param  array<string, mixed>  $shift
     * @return list<array<string, mixed>>
     */
    private static function segmentsOf(RecordStore $store, array $shift): array
    {
        $blockId = $shift['shift_block_id'] ?? null;

        if ($blockId === null) {
            return [$shift];
        }

        return array_values(array_filter(
            $store->all('rota/shifts'),
            static fn (array $other): bool => (string) ($other['shift_block_id'] ?? '') === (string) $blockId,
        ));
    }

    /**
     * @param  array<string, mixed>  $shift
     */
    private static function userIdOf(array $shift): ?int
    {
        $userId = $shift['user_id'] ?? (is_array($shift['user'] ?? null) ? ($shift['user']['id'] ?? null) : null);

        return $userId === null ? null : (int) $userId;
    }

    /**
     * The `user` summary for somebody put on a shift: the one another stored
     * shift already carries for them, or a stand-in.
     *
     * @return array<string, mixed>
     */
    private static function userSummary(RecordStore $store, int $userId): array
    {
        foreach ($store->all('rota/shifts') as $shift) {
            if (is_array($shift['user'] ?? null) && ($shift['user']['id'] ?? null) === $userId) {
                return $shift['user'];
            }
        }

        return ['id' => $userId, 'name' => "Team member {$userId}", 'avatar' => 'https://example.test/avatar.png'];
    }

    private static function isDate(string $date): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * The `Y-m-d` day a shift timestamp falls on.
     */
    private static function dayOf(mixed $timestamp): string
    {
        return is_string($timestamp) && mb_strlen($timestamp) >= 10 ? mb_substr($timestamp, 0, 10) : '2026-06-06';
    }

    private static function daysBetween(string $from, string $to): int
    {
        return (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%r%a');
    }

    private static function movedOn(mixed $timestamp, int $days): ?string
    {
        if (! is_string($timestamp)) {
            return null;
        }

        return (new DateTimeImmutable($timestamp))->modify(sprintf('%+d days', $days))->format(DATE_ATOM);
    }

    /**
     * A `Y-m-d` date and `H:i` time as one wall-clock instant.
     */
    private static function wallClock(string $date, string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("{$date} {$time}", new DateTimeZone('UTC'));
    }
}

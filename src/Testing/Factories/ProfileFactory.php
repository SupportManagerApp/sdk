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
 * Fake the authenticated user's own profile.
 *
 * The {@see \SupportManager\Sdk\Resources\Profile\ProfileResource} is a singleton
 * view spread across several JSON:API endpoints (`user`, `user/profile`,
 * `user/address`, `user/emergency-contacts`, `user/employment`,
 * `user/bank-details`), so this factory stores ONE record under the `user` path
 * and hand-routes every endpoint. All of them speak JSON:API — they hydrate via
 * `Document`/`ResourceObject` — so the `type()` is `users` and responses are
 * `{data: {type, id, attributes}}` documents. The exceptions are plain bodies:
 * `user/avatar`'s multipart upload answers `{data: {avatar_url}}` (and a 422
 * when no `avatar` part is sent) and its removal a bare 204; the address lookup
 * answers `{data: [...]}` / `{data: {...}}`; the confirmation letter is raw PDF
 * bytes. Every `user/*` route is tenant-scoped (X-Team-ID), but at the fake
 * layer each is just another route.
 *
 * The seeded record carries the full profile plus its nested `address`,
 * `emergency_contacts`, `employment`, `bank_details` and the canned
 * `address_suggestions` the lookup searches; each route projects the slice it
 * owns.
 *
 * ```php
 * SupportManager::fake()->seed(ProfileFactory::new());
 * SupportManager::profile()->show()->name;        // 'Ada Lovelace'
 * SupportManager::profile()->address()->town;     // 'London'
 * SupportManager::profile()->emergencyContacts();  // list<EmergencyContact>
 * ```
 */
final class ProfileFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'user';
    }

    public function type(): string
    {
        return 'users';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => 'Ada Lovelace',
            'email' => "user{$number}@example.com",
            'avatar' => 'https://cdn.test/ada.png',
            'mobile_number' => '+44 7700 900001',
            'date_of_birth' => '1990-12-10',
            'gender' => 'female',
            'ni_number' => 'AB123456C',
            'has_second_factor' => false,
            'created_at' => '2026-01-01T00:00:00Z',
            'teams' => [
                ['id' => 42, 'name' => 'Care Team', 'role' => 'employee'],
            ],
            'role' => 'employee',
            'address' => [
                'id' => '3',
                'address_line_one' => '12 King St',
                'address_line_two' => null,
                'address_line_three' => null,
                'town' => 'London',
                'county' => null,
                'postcode' => 'SW1A 1AA',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ],
            'emergency_contacts' => [
                [
                    'id' => '5',
                    'name' => 'Jane Doe',
                    'relationship' => 'Spouse',
                    'phone_number' => '+44 7700 900002',
                    'created_at' => '2026-01-01T00:00:00Z',
                    'updated_at' => '2026-01-01T00:00:00Z',
                ],
            ],
            'employment' => [
                'start_date' => '2020-01-06',
                'leaving_date' => null,
                'employment_length' => '6 years, 9 months',
                'contract_type' => 'Permanent Contracted Employee',
                'current_rate_of_pay' => [
                    'hourly_rate' => 12.5,
                    'sleeping_night_rate' => 40,
                    'start_date' => '2026-04-01',
                ],
                'working_pattern' => [
                    'is_bank_contract' => false,
                    'weekly_average_hours' => 37.5,
                    'weekly_sleeping_nights' => 2,
                ],
                'contracted_annual_salary' => 28535,
            ],
            'address_suggestions' => [
                [
                    'id' => '23747771',
                    'address' => '10 Downing Street, London, SW1A',
                    'fields' => [
                        'address_line_one' => '10 Downing Street',
                        'address_line_two' => '',
                        'address_line_three' => 'Westminster',
                        'town' => 'London',
                        'county' => 'Greater London',
                        'postcode' => 'SW1A 2AA',
                    ],
                ],
            ],
            'bank_details' => [
                'id' => '9',
                'name_on_account' => 'A Lovelace',
                'sort_code' => '12-34-56',
                'account_number' => '****5678',
                'payment_method' => 'bacs',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ],
        ];
    }

    /**
     * Seed a profile with no address, emergency contacts or bank details set.
     */
    public function withoutDetails(): static
    {
        return $this->state([
            'address' => null,
            'emergency_contacts' => [],
            'bank_details' => null,
        ]);
    }

    /**
     * Seed a user with no employment details (not a PA or manager), so
     * `user/employment` and the confirmation letter answer 403.
     */
    public function withoutEmployment(): static
    {
        return $this->state(['employment' => null]);
    }

    /**
     * Seed a former PA, whose profile reads as view-only.
     */
    public function viewOnly(): static
    {
        return $this->state(['is_view_only' => true]);
    }

    /**
     * Seed a profile with second-factor authentication enabled.
     */
    public function withSecondFactor(): static
    {
        return $this->state(['has_second_factor' => true]);
    }

    public function fakeRoutes(): array
    {
        return [
            // GET user — the identity card (a subset of the profile record).
            FakeRoute::get('user', static fn (RecordStore $store): mixed => Http::response(
                Json::item('users', self::identity(self::record($store))),
            )),

            // GET/PATCH user/profile — the full profile document.
            FakeRoute::get('user/profile', static fn (RecordStore $store): mixed => Http::response(
                Json::item('users', self::profile(self::record($store))),
            )),
            FakeRoute::patch('user/profile', static function (RecordStore $store, array $matches, Request $request): mixed {
                $record = [...self::record($store), ...$request->data()];
                $store->put('user', [$record]);

                return Http::response(Json::item('users', self::profile($record)));
            }),

            // user/address — read, replace, delete.
            FakeRoute::get('user/address', static fn (RecordStore $store): mixed => self::nestedResponse(
                'addresses', self::record($store)['address'] ?? null,
            )),
            FakeRoute::put('user/address', static function (RecordStore $store, array $matches, Request $request): mixed {
                $address = self::mergeNested(self::record($store)['address'] ?? null, '3', $request->data());
                self::putNested($store, 'address', $address);

                return Http::response(Json::item('addresses', $address));
            }),
            FakeRoute::delete('user/address', static function (RecordStore $store): mixed {
                self::putNested($store, 'address', null);

                return Http::response(['message' => 'Deleted']);
            }),

            // user/address/suggestions — the canned UK address lookup.
            FakeRoute::get('user/address/suggestions', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $search = $fake->query($request)['q'] ?? '';
                $search = is_string($search) ? $search : '';

                if (mb_strlen($search) < 3) {
                    return Http::response([
                        'message' => 'The q field must be at least 3 characters.',
                        'errors' => ['q' => ['The q field must be at least 3 characters.']],
                    ], 422);
                }

                $hits = array_filter(
                    self::suggestions($store),
                    static fn (array $suggestion): bool => mb_stripos((string) $suggestion['address'], $search) !== false
                        || mb_stripos((string) ($suggestion['fields']['postcode'] ?? ''), $search) !== false,
                );

                return Http::response(['data' => array_values(array_map(
                    static fn (array $suggestion): array => ['id' => $suggestion['id'], 'address' => $suggestion['address']],
                    $hits,
                ))]);
            }),
            FakeRoute::get('user/address/suggestions/{id}', static function (RecordStore $store, array $matches): mixed {
                foreach (self::suggestions($store) as $suggestion) {
                    if ($suggestion['id'] === rawurldecode($matches[1])) {
                        return Http::response(['data' => $suggestion['fields']]);
                    }
                }

                return Http::response(['message' => 'Address not found.'], 404);
            }),

            // user/emergency-contacts — list, save many, delete one.
            FakeRoute::get('user/emergency-contacts', static fn (RecordStore $store): mixed => Http::response(
                Json::collection('emergency_contacts', self::contacts($store)),
            )),
            FakeRoute::put('user/emergency-contacts', static function (RecordStore $store, array $matches, Request $request): mixed {
                $contacts = self::contacts($store);
                $inbound = $request->data()['contacts'] ?? [];

                foreach (is_array($inbound) ? array_values($inbound) : [] as $index => $contact) {
                    if (! is_array($contact)) {
                        continue;
                    }

                    $id = isset($contact['id']) ? (string) $contact['id'] : null;
                    $position = $id === null ? false : array_search($id, array_column($contacts, 'id'), true);

                    if ($id !== null && $position === false) {
                        return Http::response([
                            'message' => 'The emergency contact could not be found.',
                            'errors' => ["contacts.{$index}.id" => ['The emergency contact could not be found.']],
                        ], 422);
                    }

                    if ($position === false) {
                        $contacts[] = self::mergeNested(null, self::nextContactId($contacts), $contact);
                    } else {
                        $contacts[$position] = self::mergeNested($contacts[$position], $id, $contact);
                    }
                }

                self::putNested($store, 'emergency_contacts', $contacts);

                return Http::response(Json::collection('emergency_contacts', $contacts));
            }),
            FakeRoute::delete('user/emergency-contacts/{id}', static function (RecordStore $store, array $matches): mixed {
                $contacts = self::contacts($store);
                $remaining = array_values(array_filter(
                    $contacts,
                    static fn (array $contact): bool => (string) ($contact['id'] ?? '') !== $matches[1],
                ));

                if (count($remaining) === count($contacts)) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                self::putNested($store, 'emergency_contacts', $remaining);

                return Http::response('', 204);
            }),

            // user/employment — the summary and its PDF confirmation letter.
            FakeRoute::get('user/employment', static function (RecordStore $store): mixed {
                $record = self::record($store);
                $employment = $record['employment'] ?? null;

                return is_array($employment)
                    ? Http::response(Json::item('employment-summaries', [...$employment, 'id' => $record['id'] ?? '0']))
                    : Http::response(['message' => 'Only employees and former personal assistants have employment details.'], 403);
            }),
            FakeRoute::get('user/employment/confirmation-letter', static fn (RecordStore $store): mixed => is_array(self::record($store)['employment'] ?? null)
                ? Http::response('%PDF-1.4 confirmation-letter-bytes', 200, ['Content-Type' => 'application/pdf'])
                : Http::response(['message' => 'This action is unauthorized.'], 403)),

            // user/password — confirm-and-change.
            FakeRoute::put('user/password', static fn (): mixed => Http::response(
                ['message' => 'Password changed successfully.'],
            )),

            // user/avatar — multipart upload & removal. The upload stores a
            // fake signed URL on the record (so `me()` reflects it) and answers
            // the platform's plain `{data: {avatar_url}}` body.
            FakeRoute::post('user/avatar', static function (RecordStore $store, array $matches, Request $request): mixed {
                $part = self::avatarPart($request);

                if ($part === null) {
                    return Http::response([
                        'message' => 'An avatar image is required.',
                        'errors' => ['avatar' => ['An avatar image is required.']],
                    ], 422);
                }

                $url = 'https://cdn.test/avatars/'.rawurlencode($part['filename'] ?? 'avatar');
                $store->put('user', [[...self::record($store), 'avatar' => $url]]);

                return Http::response(['data' => ['avatar_url' => $url]]);
            }),
            FakeRoute::delete('user/avatar', static function (RecordStore $store): mixed {
                $store->put('user', [[...self::record($store), 'avatar' => null]]);

                return Http::response('', 204);
            }),

            // user/bank-details — tenant-scoped read & replace.
            FakeRoute::get('user/bank-details', static fn (RecordStore $store): mixed => self::nestedResponse(
                'bank-details', self::record($store)['bank_details'] ?? null,
            )),
            FakeRoute::put('user/bank-details', static function (RecordStore $store, array $matches, Request $request): mixed {
                $details = self::mergeNested(self::record($store)['bank_details'] ?? null, '9', $request->data());
                self::putNested($store, 'bank_details', $details);

                return Http::response(Json::item('bank-details', $details));
            }),
        ];
    }

    /**
     * The single seeded profile record, or an empty default.
     *
     * @return array<string, mixed>
     */
    private static function record(RecordStore $store): array
    {
        return $store->all('user')[0] ?? ['id' => '0', 'name' => ''];
    }

    /**
     * The seeded emergency contacts.
     *
     * @return list<array<string, mixed>>
     */
    private static function contacts(RecordStore $store): array
    {
        $contacts = self::record($store)['emergency_contacts'] ?? [];

        return is_array($contacts) ? array_values(array_filter($contacts, is_array(...))) : [];
    }

    /**
     * The id for a newly created emergency contact: one past the highest seeded.
     *
     * @param  list<array<string, mixed>>  $contacts
     */
    private static function nextContactId(array $contacts): string
    {
        $ids = array_map(static fn (array $contact): int => (int) ($contact['id'] ?? 0), $contacts);

        return (string) (max([0, ...$ids]) + 1);
    }

    /**
     * The canned address lookup results.
     *
     * @return list<array{id: string, address: string, fields: array<string, mixed>}>
     */
    private static function suggestions(RecordStore $store): array
    {
        $suggestions = self::record($store)['address_suggestions'] ?? [];

        /** @var list<array{id: string, address: string, fields: array<string, mixed>}> $suggestions */
        return is_array($suggestions) ? array_values($suggestions) : [];
    }

    /**
     * The multipart `avatar` file part of an upload request, or null when the
     * request is not multipart or carries no `avatar` part.
     *
     * @return array{name: string, contents: mixed, filename?: string}|null
     */
    private static function avatarPart(Request $request): ?array
    {
        if (! $request->isMultipart()) {
            return null;
        }

        foreach ($request->data() as $part) {
            if (is_array($part) && ($part['name'] ?? null) === 'avatar' && ($part['contents'] ?? '') !== '') {
                /** @var array{name: string, contents: mixed, filename?: string} $part */
                return $part;
            }
        }

        return null;
    }

    /**
     * The `GET user` identity card slice.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function identity(array $record): array
    {
        return [
            'id' => $record['id'] ?? '0',
            'name' => $record['name'] ?? '',
            'email' => $record['email'] ?? null,
            'avatar' => $record['avatar'] ?? null,
            'role' => $record['role'] ?? null,
        ];
    }

    /**
     * The `GET user/profile` document, nesting the address as the platform's
     * `{data: {id, attributes}}` envelope and the emergency contacts as a plain
     * list of `{type, id, attributes}` objects.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function profile(array $record): array
    {
        $profile = $record;
        unset($profile['bank_details'], $profile['role'], $profile['employment'], $profile['address_suggestions']);

        $profile['is_view_only'] = (bool) ($record['is_view_only'] ?? false);
        $profile['address'] = self::nestedEnvelope($record['address'] ?? null);
        $profile['emergency_contacts'] = Json::collection(
            'emergency_contacts',
            is_array($record['emergency_contacts'] ?? null) ? array_values($record['emergency_contacts']) : [],
        )['data'];

        return $profile;
    }

    /**
     * Wrap a nested record as a `{data: {id, attributes}}` envelope, or null.
     *
     * @param  array<string, mixed>|null  $nested
     * @return array<string, mixed>|null
     */
    private static function nestedEnvelope(?array $nested): ?array
    {
        if ($nested === null) {
            return null;
        }

        $attributes = $nested;
        unset($attributes['id']);

        return ['data' => ['id' => (string) ($nested['id'] ?? ''), 'attributes' => $attributes]];
    }

    /**
     * A JSON:API item response for a nested record, or `{data: null}` when absent.
     *
     * @param  array<string, mixed>|null  $nested
     */
    private static function nestedResponse(string $type, ?array $nested): mixed
    {
        return $nested === null
            ? Http::response(['data' => null])
            : Http::response(Json::item($type, $nested));
    }

    /**
     * Merge inbound attributes into a nested record, keeping a stable id.
     *
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function mergeNested(?array $existing, string $defaultId, array $attributes): array
    {
        $base = $existing ?? [
            'id' => $defaultId,
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
        ];

        return [...$base, ...$attributes, 'id' => $base['id'] ?? $defaultId];
    }

    /**
     * Persist a nested slice back onto the single profile record.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $nested
     */
    private static function putNested(RecordStore $store, string $key, ?array $nested): void
    {
        $record = self::record($store);
        $record[$key] = $nested;
        $store->put('user', [$record]);
    }
}

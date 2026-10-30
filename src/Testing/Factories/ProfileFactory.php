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
 * Fake the authenticated user's own profile.
 *
 * The {@see \SupportManager\Sdk\Resources\Profile\ProfileResource} is a singleton
 * view spread across several JSON:API endpoints (`user`, `user/profile`,
 * `user/address`, `user/emergency-contact`, `user/bank-details`), so this factory
 * stores ONE record under the `user` path and hand-routes every endpoint. All of
 * them speak JSON:API — they hydrate via `Document`/`ResourceObject` — so the
 * `type()` is `users` and responses are `{data: {type, id, attributes}}`
 * documents. The exception is `user/avatar`: its multipart upload answers a
 * plain `{data: {avatar_url}}` body (and a 422 when no `avatar` part is sent),
 * and its removal a bare 204. Every `user/*` route is tenant-scoped (X-Team-ID),
 * but at the fake layer each is just another route.
 *
 * The seeded record carries the full profile plus its nested `address`,
 * `emergency_contact` and `bank_details`; each route projects the slice it owns.
 *
 * ```php
 * SupportManager::fake()->seed(ProfileFactory::new());
 * SupportManager::profile()->show()->name;        // 'Ada Lovelace'
 * SupportManager::profile()->address()->town;     // 'London'
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
            'emergency_contact' => [
                'id' => '5',
                'name' => 'Jane Doe',
                'relationship' => 'Spouse',
                'phone_number' => '+44 7700 900002',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
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
     * Seed a profile with no address, emergency contact or bank details set.
     */
    public function withoutDetails(): static
    {
        return $this->state([
            'address' => null,
            'emergency_contact' => null,
            'bank_details' => null,
        ]);
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

            // user/emergency-contact — read, replace, delete.
            FakeRoute::get('user/emergency-contact', static fn (RecordStore $store): mixed => self::nestedResponse(
                'emergency-contacts', self::record($store)['emergency_contact'] ?? null,
            )),
            FakeRoute::put('user/emergency-contact', static function (RecordStore $store, array $matches, Request $request): mixed {
                $contact = self::mergeNested(self::record($store)['emergency_contact'] ?? null, '5', $request->data());
                self::putNested($store, 'emergency_contact', $contact);

                return Http::response(Json::item('emergency-contacts', $contact));
            }),
            FakeRoute::delete('user/emergency-contact', static function (RecordStore $store): mixed {
                self::putNested($store, 'emergency_contact', null);

                return Http::response(['message' => 'Deleted']);
            }),

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
     * The `GET user/profile` document, nesting address & emergency contact as the
     * platform's `{data: {id, attributes}}` envelopes.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function profile(array $record): array
    {
        $profile = $record;
        unset($profile['bank_details'], $profile['role']);

        $profile['address'] = self::nestedEnvelope($record['address'] ?? null);
        $profile['emergency_contact'] = self::nestedEnvelope($record['emergency_contact'] ?? null);

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
     * @param  array<string, mixed>|null  $nested
     */
    private static function putNested(RecordStore $store, string $key, ?array $nested): void
    {
        $record = self::record($store);
        $record[$key] = $nested;
        $store->put('user', [$record]);
    }
}

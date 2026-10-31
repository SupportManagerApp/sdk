<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Enums\Security\SocialProvider;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake linked social accounts (JSON:API type `social_accounts`, at
 * `user/social-accounts`), as SocialAccountController answers them.
 *
 * The fake treats every provider as configured, so the index's
 * `meta.available_providers` lists all three with `linked` worked out from the
 * store. Linking answers 201 with the new account, and refuses a second
 * account for a provider already linked (422 on `social`) and the token
 * {@see self::INVALID_TOKEN} (422 on `token`). Unlinking answers 204, or 404
 * when the provider isn't linked.
 *
 * ```php
 * SupportManager::fake()->seed(SocialAccountFactory::new()->provider(SocialProvider::Google));
 * SupportManager::socialAccounts()->link(SocialProvider::Apple, 'identity-token');
 * SupportManager::socialAccounts()->destroy(SocialProvider::Google);
 * ```
 */
final class SocialAccountFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * The provider token the fake cannot verify.
     */
    public const string INVALID_TOKEN = 'invalid-token';

    public function path(): string
    {
        return 'user/social-accounts';
    }

    public function type(): string
    {
        return 'social_accounts';
    }

    /**
     * One account per provider, in the enum's order, for a batch.
     */
    public function definition(int $index): array
    {
        $cases = SocialProvider::cases();

        return self::account($cases[$index % count($cases)]);
    }

    /**
     * Seed the account linked for this provider.
     */
    public function provider(SocialProvider $provider): static
    {
        return $this->state(self::account($provider));
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('user/social-accounts', static function (RecordStore $store): mixed {
                $accounts = $store->all('user/social-accounts');
                $linked = array_column($accounts, 'provider');

                return Http::response(Json::collection('social_accounts', $accounts, [
                    'available_providers' => array_map(static fn (SocialProvider $provider): array => [
                        'provider' => $provider->value,
                        'label' => self::label($provider),
                        'linked' => in_array($provider->value, $linked, true),
                    ], SocialProvider::cases()),
                ]));
            }),

            FakeRoute::post('user/social-accounts/{provider}', static function (RecordStore $store, array $matches, Request $request): mixed {
                $provider = SocialProvider::tryFrom($matches[1]);
                $token = $request->data()['token'] ?? null;

                if ($provider === null) {
                    return Json::notFound();
                }

                if (! is_string($token) || $token === '') {
                    return Json::validation(['token' => 'The token field is required.']);
                }

                if ($token === self::INVALID_TOKEN) {
                    return Json::validation(['token' => 'The provided '.self::label($provider).' token could not be verified.']);
                }

                if (self::linked($store, $provider) !== null) {
                    return Json::validation(['social' => 'You already have a '.self::label($provider).' account linked.']);
                }

                $account = $store->append('user/social-accounts', [
                    'id' => $store->nextId('user/social-accounts'),
                    ...self::account($provider),
                ]);

                return Http::response(Json::item('social_accounts', $account), 201);
            }),

            FakeRoute::delete('user/social-accounts/{provider}', static function (RecordStore $store, array $matches): mixed {
                $provider = SocialProvider::tryFrom($matches[1]);
                $account = $provider === null ? null : self::linked($store, $provider);

                if ($account === null) {
                    return Json::notFound();
                }

                $store->delete('user/social-accounts', (string) $account['id']);

                return Http::response(null, 204);
            }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function account(SocialProvider $provider): array
    {
        return [
            'provider' => $provider->value,
            'provider_label' => self::label($provider),
            'provider_email' => "sam@{$provider->value}.test",
            'provider_name' => 'Sam Taylor',
            'linked_at' => '2026-10-01T09:00:00+00:00',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function linked(RecordStore $store, SocialProvider $provider): ?array
    {
        foreach ($store->all('user/social-accounts') as $account) {
            if (($account['provider'] ?? null) === $provider->value) {
                return $account;
            }
        }

        return null;
    }

    private static function label(SocialProvider $provider): string
    {
        return ucfirst($provider->value);
    }
}

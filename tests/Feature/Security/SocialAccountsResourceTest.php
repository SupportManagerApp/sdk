<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Security\SocialAccount;
use SupportManager\Sdk\Data\Security\SocialProviderOption;
use SupportManager\Sdk\Enums\Security\SocialProvider;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

/*
 * Against src/Auth/Api/Http/Controllers/SocialAccountController.php and
 * SocialAccountResource on the platform.
 */

function social_account_object(int $id = 3, string $provider = 'google'): array
{
    return [
        'id' => (string) $id,
        'type' => 'social_accounts',
        'attributes' => [
            'provider' => $provider,
            'provider_label' => ucfirst($provider),
            'provider_email' => 'sam@gmail.com',
            'provider_name' => 'Sam Taylor',
            'linked_at' => '2026-10-31T08:00:00+00:00',
        ],
    ];
}

function social_accounts_index(): array
{
    return [
        'data' => [social_account_object()],
        'meta' => ['available_providers' => [
            ['provider' => 'google', 'label' => 'Google', 'linked' => true],
            ['provider' => 'apple', 'label' => 'Apple', 'linked' => false],
            ['provider' => 'myspace', 'label' => 'MySpace', 'linked' => false],
        ]],
    ];
}

it('lists the linked accounts without a team header', function () {
    fakeApi(['user/social-accounts' => social_accounts_index()]);

    $accounts = sdk()->socialAccounts()->all();

    expect($accounts)->toHaveCount(1)
        ->and($accounts[0])->toBeInstanceOf(SocialAccount::class)
        ->and($accounts[0]->id)->toBe('3')
        ->and($accounts[0]->provider)->toBe(SocialProvider::Google)
        ->and($accounts[0]->providerLabel)->toBe('Google')
        ->and($accounts[0]->providerEmail)->toBe('sam@gmail.com')
        ->and($accounts[0]->providerName)->toBe('Sam Taylor')
        ->and($accounts[0]->linkedAt)->toBe('2026-10-31T08:00:00+00:00');

    assertSentTo('GET', 'user/social-accounts', fn (Request $request): bool => ! $request->hasHeader('X-Team-ID'));
});

it('lists the configured providers from the index meta', function () {
    fakeApi(['user/social-accounts' => social_accounts_index()]);

    $providers = sdk()->socialAccounts()->providers();

    expect($providers)->toHaveCount(3)
        ->and($providers[0])->toBeInstanceOf(SocialProviderOption::class)
        ->and($providers[0]->provider)->toBe(SocialProvider::Google)
        ->and($providers[0]->label)->toBe('Google')
        ->and($providers[0]->linked)->toBeTrue()
        ->and($providers[1]->provider)->toBe(SocialProvider::Apple)
        ->and($providers[1]->linked)->toBeFalse()
        // A provider the SDK does not know yet keeps its label but no enum.
        ->and($providers[2]->provider)->toBeNull()
        ->and($providers[2]->label)->toBe('MySpace');
});

it('lists no providers when the meta is missing', function () {
    fakeApi(['user/social-accounts' => ['data' => []]]);

    expect(sdk()->socialAccounts()->providers())->toBe([]);
});

it('links an account from a provider token', function () {
    fakeApi(['user/social-accounts/google' => Http::response(['data' => social_account_object(4)], 201)]);

    $account = sdk()->socialAccounts()->link(SocialProvider::Google, 'ya29.token');

    expect($account)->toBeInstanceOf(SocialAccount::class)
        ->and($account?->id)->toBe('4')
        ->and($account?->provider)->toBe(SocialProvider::Google);

    assertSentTo('POST', 'user/social-accounts/google', fn (Request $request): bool => $request->data() === ['token' => 'ya29.token']
        && ! $request->hasHeader('X-Team-ID'));
});

it('links by the provider string too', function () {
    fakeApi(['user/social-accounts/apple' => Http::response(['data' => social_account_object(5, 'apple')], 201)]);

    expect(sdk()->socialAccounts()->link('apple', 'identity-token')?->provider)->toBe(SocialProvider::Apple);

    assertSentTo('POST', 'user/social-accounts/apple');
});

it('surfaces a token the provider cannot verify as a validation error', function () {
    fakeApi(['user/social-accounts/google' => Http::response([
        'message' => 'The provided Google token could not be verified.',
        'errors' => ['token' => ['The provided Google token could not be verified.']],
    ], 422)]);

    try {
        sdk()->socialAccounts()->link(SocialProvider::Google, 'bad-token');
        $this->fail('Expected an ApiValidationException.');
    } catch (ApiValidationException $e) {
        expect($e->errors)->toHaveKey('token');
    }
});

it('surfaces a provider account linked elsewhere as a validation error on social', function () {
    fakeApi(['user/social-accounts/google' => Http::response([
        'message' => 'This Google account is already linked to another user.',
        'errors' => ['social' => ['This Google account is already linked to another user.']],
    ], 422)]);

    try {
        sdk()->socialAccounts()->link(SocialProvider::Google, 'ya29.token');
        $this->fail('Expected an ApiValidationException.');
    } catch (ApiValidationException $e) {
        expect($e->getMessage())->toBe('This Google account is already linked to another user.')
            ->and($e->errors)->toHaveKey('social');
    }
});

it('surfaces an unconfigured provider as a bare validation message', function () {
    fakeApi(['user/social-accounts/microsoft' => Http::response(['message' => 'Microsoft sign-in is not available.'], 422)]);

    sdk()->socialAccounts()->link(SocialProvider::Microsoft, 'msal.token');
})->throws(ApiValidationException::class, 'Microsoft sign-in is not available.');

it('unlinks an account', function () {
    fakeApi(['user/social-accounts/google' => Http::response(null, 204)]);

    sdk()->socialAccounts()->destroy(SocialProvider::Google);

    assertSentTo('DELETE', 'user/social-accounts/google', fn (Request $request): bool => ! $request->hasHeader('X-Team-ID'));
});

it('throws when unlinking a provider that is not linked', function () {
    fakeApi(['user/social-accounts/apple' => Http::response(['message' => 'Not Found'], 404)]);

    sdk()->socialAccounts()->destroy('apple');
})->throws(SupportManagerApiException::class, 'Not Found');

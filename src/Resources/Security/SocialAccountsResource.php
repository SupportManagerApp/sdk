<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Security;

use SensitiveParameter;
use SupportManager\Sdk\Data\Security\SocialAccount;
use SupportManager\Sdk\Data\Security\SocialProviderOption;
use SupportManager\Sdk\Enums\Security\SocialProvider;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Untenanted;
use SupportManager\Sdk\Resources\Resource;

/**
 * The provider accounts (Google, Microsoft, Apple) linked to the authenticated
 * user for social sign-in: the web profile's Connected Accounts panel.
 * User-scoped, so no `X-Team-ID` goes out.
 *
 * To link, obtain a provider token natively (Google Sign-In, Sign in with
 * Apple, MSAL), the same token {@see \SupportManager\Sdk\Client\SupportManagerClient::loginWithProvider()}
 * takes, and pass it to {@see self::link()}; the platform verifies it with the
 * provider.
 *
 * ```php
 * SupportManager::socialAccounts()->providers();   // what to offer, with `linked`
 * SupportManager::socialAccounts()->all();         // the linked accounts, with their emails
 * SupportManager::socialAccounts()->link(SocialProvider::Google, $googleAccessToken);
 * SupportManager::socialAccounts()->destroy(SocialProvider::Google);
 * ```
 *
 * @extends Resource<SocialAccount>
 */
final class SocialAccountsResource extends Resource
{
    use Untenanted;

    /**
     * The user's linked accounts (`GET user/social-accounts`).
     *
     * @return list<SocialAccount>
     */
    public function all(): array
    {
        return $this->collection($this->get($this->path()));
    }

    /**
     * Every provider configured on the platform's environment, and whether the
     * user has linked it: the same `GET user/social-accounts`, read from its
     * `meta.available_providers`. Offer link or unlink from these; a provider
     * that is not configured is not listed, and linking it answers 422.
     *
     * @return list<SocialProviderOption>
     */
    public function providers(): array
    {
        $response = $this->get($this->path());

        $this->throwForErrorResponse($response);

        $rows = $response->json('meta.available_providers');

        return is_array($rows)
            ? array_values(array_map(SocialProviderOption::fromArray(...), array_filter($rows, is_array(...))))
            : [];
    }

    /**
     * Link the provider account behind a natively obtained provider token
     * (`POST user/social-accounts/{provider}`): the OAuth access token for
     * Google and Microsoft, the identity token for Apple.
     *
     * The platform answers 422, an {@see \SupportManager\Sdk\Exceptions\ApiValidationException},
     * when the token cannot be verified (`errors.token`), when that provider
     * account is linked to another user or the user already has one for this
     * provider (`errors.social`), and, with a bare message and no `errors`,
     * when the provider is not configured on its environment.
     */
    public function link(SocialProvider|string $provider, #[SensitiveParameter] string $token): ?SocialAccount
    {
        $provider = $provider instanceof SocialProvider ? $provider->value : $provider;

        return $this->item($this->post("{$this->path()}/{$provider}", ['token' => $token]));
    }

    /**
     * Unlink the user's account for a provider (`DELETE user/social-accounts/{provider}`).
     * The platform answers 404 when none is linked. As on the web, it allows
     * unlinking the last social account even when the user has no password:
     * they can still sign in by password reset, a passkey, or social sign-in
     * with a matching email.
     */
    public function destroy(SocialProvider|string $provider): void
    {
        $id = $provider instanceof SocialProvider ? $provider->value : $provider;

        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
    }

    protected function path(): string
    {
        return 'user/social-accounts';
    }

    protected function hydrate(ResourceObject $object): SocialAccount
    {
        return SocialAccount::fromArray($object->flat());
    }
}

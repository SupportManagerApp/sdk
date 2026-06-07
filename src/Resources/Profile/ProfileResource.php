<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Profile;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Profile\Address;
use SupportManager\Sdk\Data\Profile\BankDetails;
use SupportManager\Sdk\Data\Profile\EmergencyContact;
use SupportManager\Sdk\Data\Profile\Profile;
use SupportManager\Sdk\Data\Profile\User;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's own profile — identity, personal details, address,
 * emergency contact, avatar, password and payroll bank details.
 *
 * Unlike most resources this is a singleton view of the current user rather than
 * a queryable collection, so it exposes intent-named actions instead of
 * `query()`/`find()`. Every endpoint here is tenant-scoped: the platform's
 * Profile route group requires `X-Team-ID` (it returns 422 "Invalid or missing
 * team" without it), so each call goes through the tenant-scoped base helpers
 * ({@see Resource::get()} / {@see Resource::put()} / {@see Resource::tenant()}),
 * which resolve {@see \SupportManager\Sdk\Session\CurrentTeam} and forward the
 * header automatically.
 *
 * ```php
 * SupportManager::profile()->me();
 * SupportManager::profile()->update(['name' => 'Ada Lovelace']);
 * SupportManager::profile()->saveAddress([...]);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<Profile>
 */
final class ProfileResource extends Resource
{
    /**
     * The authenticated user's identity card (`GET user`), or null when absent.
     */
    public function me(): ?User
    {
        $response = $this->tenant(fn (PendingRequest $request): Response => $request->get('user'));

        $resource = $this->resourceFrom($response);

        return $resource === null ? null : User::fromArray($resource->flat());
    }

    /**
     * The authenticated user's full profile (`GET user/profile`).
     */
    public function show(): ?Profile
    {
        return $this->item($this->tenant(
            fn (PendingRequest $request): Response => $request->get('user/profile'),
        ));
    }

    /**
     * Update the user's profile (`PATCH user/profile`), returning the fresh copy.
     *
     * @param  array<string, mixed>  $data  e.g. name, mobile_number, date_of_birth, gender.
     */
    public function update(array $data): ?Profile
    {
        return $this->item($this->tenant(
            fn (PendingRequest $request): Response => $request->patch('user/profile', $data),
        ));
    }

    /**
     * The user's address (`GET user/address`), or null when none is set.
     */
    public function address(): ?Address
    {
        $response = $this->tenant(fn (PendingRequest $request): Response => $request->get('user/address'));

        $resource = $this->resourceFrom($response);

        return $resource === null ? null : Address::fromArray($resource->flat());
    }

    /**
     * Create or replace the user's address (`PUT user/address`).
     *
     * @param  array<string, mixed>  $data  address_line_one, town, postcode, …
     */
    public function saveAddress(array $data): ?Address
    {
        $response = $this->tenant(
            fn (PendingRequest $request): Response => $request->put('user/address', $data),
        );

        $resource = $this->resourceFrom($response);

        return $resource === null ? null : Address::fromArray($resource->flat());
    }

    /**
     * Delete the user's address (`DELETE user/address`).
     */
    public function deleteAddress(): void
    {
        $this->tenant(fn (PendingRequest $request): Response => $request->delete('user/address'));
    }

    /**
     * The user's emergency contact (`GET user/emergency-contact`), or null.
     */
    public function emergencyContact(): ?EmergencyContact
    {
        $response = $this->tenant(
            fn (PendingRequest $request): Response => $request->get('user/emergency-contact'),
        );

        $resource = $this->resourceFrom($response);

        return $resource === null ? null : EmergencyContact::fromArray($resource->flat());
    }

    /**
     * Create or replace the user's emergency contact (`PUT user/emergency-contact`).
     *
     * @param  array<string, mixed>  $data  name, relationship, phone_number.
     */
    public function saveEmergencyContact(array $data): ?EmergencyContact
    {
        $response = $this->tenant(
            fn (PendingRequest $request): Response => $request->put('user/emergency-contact', $data),
        );

        $resource = $this->resourceFrom($response);

        return $resource === null ? null : EmergencyContact::fromArray($resource->flat());
    }

    /**
     * Delete the user's emergency contact (`DELETE user/emergency-contact`).
     */
    public function deleteEmergencyContact(): void
    {
        $this->tenant(
            fn (PendingRequest $request): Response => $request->delete('user/emergency-contact'),
        );
    }

    /**
     * Change the user's password (`PUT user/password`).
     *
     * The server confirms the current password, so all three values are required.
     */
    public function changePassword(string $currentPassword, string $password, string $passwordConfirmation): void
    {
        $this->tenant(fn (PendingRequest $request): Response => $request->put('user/password', [
            'current_password' => $currentPassword,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]));
    }

    /**
     * Remove the user's avatar (`DELETE user/avatar`).
     *
     * Note: uploading an avatar (`POST user/avatar`) is a multipart file upload
     * and is intentionally out of scope for this typed surface — issue it through
     * the tenant-scoped {@see Resource::tenant()} escape hatch with an
     * `attach()`ed request when needed, so it still carries X-Team-ID.
     */
    public function deleteAvatar(): void
    {
        $this->tenant(fn (PendingRequest $request): Response => $request->delete('user/avatar'));
    }

    /**
     * The user's payroll bank details (`GET user/bank-details`), or null.
     *
     * Tenant-scoped: this call carries X-Team-ID via the base helper.
     */
    public function bankDetails(): ?BankDetails
    {
        $response = $this->get('user/bank-details');

        $resource = $this->resourceFrom($response);

        return $resource === null ? null : BankDetails::fromArray($resource->flat());
    }

    /**
     * Create or replace the user's payroll bank details (`PUT user/bank-details`).
     *
     * Tenant-scoped: this call carries X-Team-ID via the base helper.
     *
     * @param  array<string, mixed>  $data  name_on_account, sort_code, account_number, …
     */
    public function saveBankDetails(array $data): ?BankDetails
    {
        $response = $this->put('user/bank-details', $data);

        $resource = $this->resourceFrom($response);

        return $resource === null ? null : BankDetails::fromArray($resource->flat());
    }

    protected function path(): string
    {
        return 'user/profile';
    }

    protected function hydrate(ResourceObject $object): Profile
    {
        return Profile::fromArray($object->flat());
    }

    /**
     * Map error responses, then pull the single JSON:API resource object from the
     * body (null for an empty `{data: null}` payload).
     */
    private function resourceFrom(Response $response): ?ResourceObject
    {
        $this->throwForErrorResponse($response, $response->effectiveUri()?->getPath() ?? 'resource');

        return Document::fromArray($response->json() ?? [])->resource();
    }
}

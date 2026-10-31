<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Profile;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Profile\Address;
use SupportManager\Sdk\Data\Profile\AddressSuggestion;
use SupportManager\Sdk\Data\Profile\BankDetails;
use SupportManager\Sdk\Data\Profile\EmergencyContact;
use SupportManager\Sdk\Data\Profile\EmploymentSummary;
use SupportManager\Sdk\Data\Profile\Profile;
use SupportManager\Sdk\Data\Profile\SuggestedAddress;
use SupportManager\Sdk\Data\Profile\User;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's own profile — identity, personal details, address
 * (with UK address lookup), emergency contacts, employment summary and
 * confirmation letter, avatar (upload and removal), password and payroll bank
 * details.
 *
 * Unlike most resources this is a singleton view of the current user rather than
 * a queryable collection, so it exposes intent-named actions instead of
 * `query()`/`find()`. Every endpoint here is tenant-scoped: the platform's
 * Profile route group requires `X-Team-ID` (it returns 422 "Invalid or missing
 * team" without it), so each call goes through the tenant-scoped base helpers
 * ({@see Resource::get()} / {@see Resource::put()} / {@see Resource::tenant()}),
 * which resolve {@see \SupportManager\Sdk\Session\CurrentTeam} and forward the
 * header automatically. Most of the endpoints return a JSON:API resource of
 * their own type, read with {@see Resource::item()} or
 * {@see Resource::collection()} given that DTO's class.
 *
 * Former PAs can read their profile, address, emergency contacts, bank details
 * and employment summary, but every write answers 403;
 * {@see Profile::$isViewOnly} tells the client to hide edit controls.
 *
 * ```php
 * SupportManager::profile()->me();
 * SupportManager::profile()->update(['name' => 'Ada Lovelace']);
 * SupportManager::profile()->saveAddress([...]);
 * SupportManager::profile()->saveEmergencyContacts([['name' => 'Jane Doe', ...]]);
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
        return $this->item($this->get('user'), User::class);
    }

    /**
     * The authenticated user's full profile (`GET user/profile`).
     */
    public function show(): ?Profile
    {
        return $this->item($this->get('user/profile'));
    }

    /**
     * Update the user's profile (`PATCH user/profile`), returning the fresh copy.
     *
     * Send only the fields to change; omitted fields keep their stored values.
     * The email must not belong to another account. `show_birthday_on_roster`
     * is ignored while an admin is impersonating the user.
     *
     * @param  array<string, mixed>  $data  Any of title, name, email, mobile_number, ni_number, date_of_birth, gender, show_birthday_on_roster.
     */
    public function update(array $data): ?Profile
    {
        return $this->item($this->patch('user/profile', $data));
    }

    /**
     * The user's address (`GET user/address`), or null when none is set.
     */
    public function address(): ?Address
    {
        return $this->item($this->get('user/address'), Address::class);
    }

    /**
     * Create or replace the user's address (`PUT user/address`).
     *
     * @param  array<string, mixed>  $data  address_line_one, town, postcode, …
     */
    public function saveAddress(array $data): ?Address
    {
        return $this->item($this->put('user/address', $data), Address::class);
    }

    /**
     * Delete the user's address (`DELETE user/address`).
     */
    public function deleteAddress(): void
    {
        $this->delete('user/address');
    }

    /**
     * UK addresses matching a postcode or partial address
     * (`GET user/address/suggestions?q=`), the same lookup the web profile
     * uses. Empty when nothing matches or the lookup is unavailable; a search
     * shorter than three characters is a 422
     * ({@see \SupportManager\Sdk\Exceptions\ApiValidationException} on `q`).
     *
     * @return list<AddressSuggestion>
     */
    public function addressSuggestions(string $search): array
    {
        return $this->dataList($this->get('user/address/suggestions', ['q' => $search]), AddressSuggestion::class);
    }

    /**
     * The address fields for one suggestion (`GET user/address/suggestions/{id}`),
     * ready to pass to {@see self::saveAddress()} via
     * {@see SuggestedAddress::toArray()}, or null when the lookup no longer
     * knows the id (404).
     */
    public function addressSuggestion(string $id): ?SuggestedAddress
    {
        $response = $this->get('user/address/suggestions/'.rawurlencode($id));

        if ($response->status() === 404) {
            return null;
        }

        return $this->data($response, SuggestedAddress::class);
    }

    /**
     * Every emergency contact the user has in the current team
     * (`GET user/emergency-contacts`), oldest first; empty when none are set.
     *
     * @return list<EmergencyContact>
     */
    public function emergencyContacts(): array
    {
        return $this->collection($this->get('user/emergency-contacts'), EmergencyContact::class);
    }

    /**
     * Create or update several emergency contacts at once
     * (`PUT user/emergency-contacts`), returning the user's full list.
     *
     * A contact with an `id` updates that contact; one without is created.
     * Contacts left out of the list are kept, so remove them with
     * {@see self::deleteEmergencyContact()}. An `id` that is not one of the
     * user's own contacts in the current team is a 422 on `contacts.N.id`.
     *
     * @param  list<array<string, mixed>>  $contacts  Each: id (optional), name, relationship, phone_number.
     * @return list<EmergencyContact>
     */
    public function saveEmergencyContacts(array $contacts): array
    {
        return $this->collection($this->put('user/emergency-contacts', ['contacts' => $contacts]), EmergencyContact::class);
    }

    /**
     * Delete one of the user's emergency contacts
     * (`DELETE user/emergency-contacts/{contact}`).
     *
     * Unlike the other removals here this throws on failure: a contact that is
     * not the user's own in the current team answers 404, surfaced as a
     * {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}.
     */
    public function deleteEmergencyContact(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("user/emergency-contacts/{$id}"));
    }

    /**
     * The user's employment summary (`GET user/employment`): start and leaving
     * dates, length of employment, contract type, current rate of pay, working
     * pattern and contracted annual salary.
     *
     * Only PAs, managers and former PAs have one; anyone else gets a 403,
     * surfaced as a {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}.
     */
    public function employment(): ?EmploymentSummary
    {
        return $this->item($this->get('user/employment'), EmploymentSummary::class);
    }

    /**
     * Download an employment confirmation letter as a PDF
     * (`GET user/employment/confirmation-letter`), the same letter as the web
     * profile's button. The response body is the PDF itself, so it comes back
     * as a {@see Download} rather than a DTO. PAs and managers only; anyone
     * else's 403 throws a
     * {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}.
     *
     * @param  string|null  $recipientName  Who the letter is addressed to; the platform defaults to "To Whom It May Concern".
     * @param  bool  $includeGrossPay  Include gross pay from the last three finalised payslips.
     */
    public function employmentConfirmationLetter(?string $recipientName = null, bool $includeGrossPay = false): Download
    {
        $query = ['include_gross_pay' => $includeGrossPay ? 1 : 0];

        if ($recipientName !== null) {
            $query['recipient_name'] = $recipientName;
        }

        return $this->downloadFile('user/employment/confirmation-letter', $query);
    }

    /**
     * Change the user's password (`PUT user/password`).
     *
     * The server confirms the current password, so all three values are required.
     */
    public function changePassword(string $currentPassword, string $password, string $passwordConfirmation): void
    {
        $this->put('user/password', [
            'current_password' => $currentPassword,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]);
    }

    /**
     * Upload a new avatar image (`POST user/avatar`), returning its new URL.
     *
     * Sent as a multipart `avatar` file part through the tenant-scoped
     * {@see Resource::tenant()} helper, so it still carries X-Team-ID. The
     * server accepts any image type up to 15MB, replacing (and deleting) any
     * previous avatar, and answers a bad file with a 422
     * ({@see \SupportManager\Sdk\Exceptions\ApiValidationException} on
     * `avatar`). The response is a plain `{data: {avatar_url}}` body rather
     * than a JSON:API document; the URL is a short-lived signed link, so
     * re-read {@see self::me()} rather than caching it.
     *
     * Takes the raw bytes rather than a path or stream so a host app can pass
     * whatever it holds — `$upload->get()` and `$upload->getClientOriginalName()`
     * from an `UploadedFile`, `Storage::get(...)`, or `file_get_contents(...)` —
     * and so the body can be replayed when the request is retried after a
     * token refresh.
     *
     * @param  string  $contents  The image's raw bytes.
     * @param  string  $filename  The client filename, e.g. `avatar.png`; its extension sets the part's Content-Type.
     */
    public function uploadAvatar(string $contents, string $filename): ?string
    {
        $url = $this->raw($this->tenant(fn (PendingRequest $request): Response => $request
            ->attach('avatar', $contents, $filename)
            ->post('user/avatar')))['avatar_url'] ?? null;

        return is_string($url) ? $url : null;
    }

    /**
     * Remove the user's avatar (`DELETE user/avatar`). The avatar URL then falls
     * back to a generated initials image; succeeds whether or not one was set.
     */
    public function deleteAvatar(): void
    {
        $this->delete('user/avatar');
    }

    /**
     * The user's payroll bank details (`GET user/bank-details`), or null.
     *
     * Tenant-scoped: this call carries X-Team-ID via the base helper.
     */
    public function bankDetails(): ?BankDetails
    {
        return $this->item($this->get('user/bank-details'), BankDetails::class);
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
        return $this->item($this->put('user/bank-details', $data), BankDetails::class);
    }

    protected function path(): string
    {
        return 'user/profile';
    }

    protected function hydrate(ResourceObject $object): Profile
    {
        return Profile::fromArray($object->flat());
    }
}

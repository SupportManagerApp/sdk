<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Profile\Address;
use SupportManager\Sdk\Data\Profile\AddressSuggestion;
use SupportManager\Sdk\Data\Profile\BankDetails;
use SupportManager\Sdk\Data\Profile\EmergencyContact;
use SupportManager\Sdk\Data\Profile\EmploymentSummary;
use SupportManager\Sdk\Data\Profile\Profile;
use SupportManager\Sdk\Data\Profile\RateOfPay;
use SupportManager\Sdk\Data\Profile\SuggestedAddress;
use SupportManager\Sdk\Data\Profile\User;
use SupportManager\Sdk\Data\Profile\WorkingPattern;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Testing\Factories\ProfileFactory;

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function profileItem(string $type, string $id, array $attributes): array
{
    return ['data' => ['type' => $type, 'id' => $id, 'attributes' => $attributes]];
}

it('fetches the current user with a team header', function () {
    fakeApi(['user' => Http::response(profileItem('users', '7', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://cdn.test/ada.png',
        'role' => 'PA',
    ]))]);

    $user = sdk()->profile()->me();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->id)->toBe('7')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->role)->toBe('PA');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/user')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('shows the full profile with nested address and emergency contacts', function () {
    fakeApi(['user/profile' => Http::response(profileItem('users', '7', [
        'title' => 'DR',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'mobile_number' => '+44 7700 900001',
        'date_of_birth' => '1990-12-10',
        'gender' => 'female',
        'ni_number' => 'AB123456C',
        'show_birthday_on_roster' => true,
        'is_view_only' => false,
        'address' => ['data' => ['id' => '3', 'type' => 'addresses', 'attributes' => ['address_line_one' => '12 King St', 'town' => 'London', 'postcode' => 'SW1A 1AA']]],
        'emergency_contacts' => [
            ['id' => '5', 'type' => 'emergency_contacts', 'attributes' => ['name' => 'Jane', 'relationship' => 'Spouse', 'phone_number' => '+44 7700 900002']],
            ['id' => '6', 'type' => 'emergency_contacts', 'attributes' => ['name' => 'John', 'relationship' => 'Brother', 'phone_number' => '+44 7700 900003']],
        ],
        'teams' => [['id' => 42, 'name' => 'Care Team', 'role' => 'PA']],
        'has_second_factor' => true,
        'created_at' => '2026-01-01T00:00:00Z',
    ]))]);

    $profile = sdk()->profile()->show();

    expect($profile)->toBeInstanceOf(Profile::class)
        ->and($profile->title)->toBe('DR')
        ->and($profile->name)->toBe('Ada Lovelace')
        ->and($profile->niNumber)->toBe('AB123456C')
        ->and($profile->showBirthdayOnRoster)->toBeTrue()
        ->and($profile->isViewOnly)->toBeFalse()
        ->and($profile->hasSecondFactor)->toBeTrue()
        ->and($profile->address)->toBeInstanceOf(Address::class)
        ->and($profile->address->town)->toBe('London')
        ->and($profile->emergencyContacts)->toHaveCount(2)
        ->and($profile->emergencyContacts[0])->toBeInstanceOf(EmergencyContact::class)
        ->and($profile->emergencyContacts[0]->id)->toBe('5')
        ->and($profile->emergencyContacts[0]->name)->toBe('Jane')
        ->and($profile->emergencyContacts[1]->relationship)->toBe('Brother')
        ->and($profile->teams)->toBe([['id' => 42, 'name' => 'Care Team', 'role' => 'PA']]);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('leaves nested relations null when absent', function () {
    fakeApi(['user/profile' => Http::response(profileItem('users', '7', [
        'name' => 'Ada',
        'teams' => [],
        'has_second_factor' => false,
    ]))]);

    $profile = sdk()->profile()->show();

    expect($profile->address)->toBeNull()
        ->and($profile->emergencyContacts)->toBe([])
        ->and($profile->title)->toBeNull()
        ->and($profile->showBirthdayOnRoster)->toBeFalse()
        ->and($profile->isViewOnly)->toBeFalse()
        ->and($profile->teams)->toBe([]);
});

it('updates the profile via PATCH', function () {
    fakeApi(['user/profile' => Http::response(profileItem('users', '7', [
        'name' => 'Grace Hopper',
    ]))]);

    $profile = sdk()->profile()->update(['name' => 'Grace Hopper']);

    expect($profile->name)->toBe('Grace Hopper');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['name'] === 'Grace Hopper'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('sends the web profile fields on update', function () {
    fakeApi(['user/profile' => Http::response(profileItem('users', '7', [
        'title' => 'DR',
        'name' => 'Ada Lovelace',
        'email' => 'ada@new.example.com',
        'ni_number' => 'AB123456C',
        'show_birthday_on_roster' => true,
    ]))]);

    $profile = sdk()->profile()->update([
        'title' => 'DR',
        'email' => 'ada@new.example.com',
        'ni_number' => 'AB123456C',
        'show_birthday_on_roster' => true,
    ]);

    expect($profile->title)->toBe('DR')
        ->and($profile->email)->toBe('ada@new.example.com')
        ->and($profile->showBirthdayOnRoster)->toBeTrue();
    Http::assertSent(fn ($request) => $request['title'] === 'DR'
        && $request['email'] === 'ada@new.example.com'
        && $request['ni_number'] === 'AB123456C'
        && $request['show_birthday_on_roster'] === true);
});

it('reads, saves and deletes the address with a team header', function () {
    fakeApi([
        'user/address' => fn ($request) => $request->method() === 'DELETE'
            ? Http::response('', 204)
            : Http::response(profileItem('addresses', '3', [
                'address_line_one' => '12 King St',
                'town' => 'London',
                'postcode' => 'SW1A 1AA',
            ])),
    ]);

    $address = sdk()->profile()->saveAddress([
        'address_line_one' => '12 King St',
        'town' => 'London',
        'postcode' => 'SW1A 1AA',
    ]);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($address->postcode)->toBe('SW1A 1AA');

    sdk()->profile()->deleteAddress();

    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->hasHeader('X-Team-ID', '42'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->hasHeader('X-Team-ID', '42'));
});

it('returns null address when the server reports none', function () {
    fakeApi(['user/address' => ['data' => null]]);

    expect(sdk()->profile()->address())->toBeNull();
});

it('searches address suggestions with a team header', function () {
    fakeApi(['user/address/suggestions*' => ['data' => [
        ['id' => '23747771', 'address' => '10 Downing Street, London, SW1A'],
    ]]]);

    $suggestions = sdk()->profile()->addressSuggestions('SW1A 2AA');

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0])->toBeInstanceOf(AddressSuggestion::class)
        ->and($suggestions[0]->id)->toBe('23747771')
        ->and($suggestions[0]->address)->toBe('10 Downing Street, London, SW1A');

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), '/api/v1/user/address/suggestions?q=SW1A 2AA')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('fetches a suggested address ready to save', function () {
    fakeApi(['user/address/suggestions/23747771' => ['data' => [
        'address_line_one' => 'Prime Minister & First Lord of the Treasury',
        'address_line_two' => '10 Downing Street',
        'address_line_three' => 'Westminster, Whitehall',
        'town' => 'London',
        'county' => 'Greater London',
        'postcode' => 'SW1A 2AA',
    ]]]);

    $address = sdk()->profile()->addressSuggestion('23747771');

    expect($address)->toBeInstanceOf(SuggestedAddress::class)
        ->and($address->addressLineTwo)->toBe('10 Downing Street')
        ->and($address->postcode)->toBe('SW1A 2AA')
        ->and($address->toArray())->toBe([
            'address_line_one' => 'Prime Minister & First Lord of the Treasury',
            'address_line_two' => '10 Downing Street',
            'address_line_three' => 'Westminster, Whitehall',
            'town' => 'London',
            'county' => 'Greater London',
            'postcode' => 'SW1A 2AA',
        ]);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('returns null for an unknown address suggestion (404)', function () {
    fakeApi(['user/address/suggestions/nope' => Http::response(['message' => 'Address not found.'], 404)]);

    expect(sdk()->profile()->addressSuggestion('nope'))->toBeNull();
});

it('lists the emergency contacts with a team header', function () {
    fakeApi(['user/emergency-contacts' => ['data' => [
        ['type' => 'emergency_contacts', 'id' => '5', 'attributes' => ['name' => 'Jane', 'relationship' => 'Spouse', 'phone_number' => '+44 7700 900002']],
        ['type' => 'emergency_contacts', 'id' => '6', 'attributes' => ['name' => 'John', 'relationship' => 'Brother', 'phone_number' => '+44 7700 900003']],
    ]]]);

    $contacts = sdk()->profile()->emergencyContacts();

    expect($contacts)->toHaveCount(2)
        ->and($contacts[0])->toBeInstanceOf(EmergencyContact::class)
        ->and($contacts[0]->id)->toBe('5')
        ->and($contacts[1]->name)->toBe('John');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/api/v1/user/emergency-contacts')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('returns no emergency contacts when none are set', function () {
    fakeApi(['user/emergency-contacts' => ['data' => []]]);

    expect(sdk()->profile()->emergencyContacts())->toBe([]);
});

it('saves several emergency contacts and returns the full list', function () {
    fakeApi(['user/emergency-contacts' => ['data' => [
        ['type' => 'emergency_contacts', 'id' => '5', 'attributes' => ['name' => 'Jane Roe', 'relationship' => 'Spouse', 'phone_number' => '+44 7700 900002']],
        ['type' => 'emergency_contacts', 'id' => '7', 'attributes' => ['name' => 'John Smith', 'relationship' => 'Brother', 'phone_number' => '+44 7700 900004']],
    ]]]);

    $contacts = sdk()->profile()->saveEmergencyContacts([
        ['id' => 5, 'name' => 'Jane Roe', 'relationship' => 'Spouse', 'phone_number' => '+44 7700 900002'],
        ['name' => 'John Smith', 'relationship' => 'Brother', 'phone_number' => '+44 7700 900004'],
    ]);

    expect($contacts)->toHaveCount(2)
        ->and($contacts[0]->name)->toBe('Jane Roe')
        ->and($contacts[1]->id)->toBe('7');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/v1/user/emergency-contacts')
        && $request['contacts'][0]['id'] === 5
        && ! isset($request['contacts'][1]['id'])
        && $request['contacts'][1]['name'] === 'John Smith'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('deletes one emergency contact by id', function () {
    fakeApi(['user/emergency-contacts/5' => Http::response('', 204)]);

    sdk()->profile()->deleteEmergencyContact(5);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/user/emergency-contacts/5')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('reads the employment summary with a team header', function () {
    fakeApi(['user/employment' => Http::response(profileItem('employment-summaries', '7', [
        'start_date' => '2020-01-06',
        'leaving_date' => null,
        'employment_length' => '6 years, 9 months',
        'contract_type' => 'Permanent Contracted Employee',
        'current_rate_of_pay' => ['hourly_rate' => 12.5, 'sleeping_night_rate' => 40, 'start_date' => '2026-04-01'],
        'working_pattern' => ['is_bank_contract' => false, 'weekly_average_hours' => 37.5, 'weekly_sleeping_nights' => 2],
        'contracted_annual_salary' => 28535,
    ]))]);

    $employment = sdk()->profile()->employment();

    expect($employment)->toBeInstanceOf(EmploymentSummary::class)
        ->and($employment->id)->toBe('7')
        ->and($employment->startDate)->toBe('2020-01-06')
        ->and($employment->leavingDate)->toBeNull()
        ->and($employment->employmentLength)->toBe('6 years, 9 months')
        ->and($employment->contractType)->toBe('Permanent Contracted Employee')
        ->and($employment->currentRateOfPay)->toBeInstanceOf(RateOfPay::class)
        ->and($employment->currentRateOfPay?->hourlyRate)->toBe(12.5)
        ->and($employment->currentRateOfPay?->sleepingNightRate)->toBe(40.0)
        ->and($employment->workingPattern)->toBeInstanceOf(WorkingPattern::class)
        ->and($employment->workingPattern?->isBankContract)->toBeFalse()
        ->and($employment->workingPattern?->weeklyAverageHours)->toBe(37.5)
        ->and($employment->contractedAnnualSalary)->toBe(28535.0);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('leaves employment pay and pattern null when the server has none', function () {
    fakeApi(['user/employment' => Http::response(profileItem('employment-summaries', '7', [
        'start_date' => null,
        'leaving_date' => null,
        'employment_length' => 'N/A',
        'contract_type' => 'Not set',
        'current_rate_of_pay' => null,
        'working_pattern' => null,
        'contracted_annual_salary' => null,
    ]))]);

    $employment = sdk()->profile()->employment();

    expect($employment?->startDate)->toBeNull()
        ->and($employment?->employmentLength)->toBe('N/A')
        ->and($employment?->currentRateOfPay)->toBeNull()
        ->and($employment?->workingPattern)->toBeNull()
        ->and($employment?->contractedAnnualSalary)->toBeNull();
});

it('throws when a non-employee reads employment (403)', function () {
    fakeApi(['user/employment' => Http::response(['message' => 'Only employees and former personal assistants have employment details.'], 403)]);

    sdk()->profile()->employment();
})->throws(SupportManagerApiException::class, 'Only employees and former personal assistants have employment details.');

it('downloads the employment confirmation letter as a raw response', function () {
    fakeApi(['user/employment/confirmation-letter*' => Http::response('%PDF-1.4 letter-bytes', 200, ['Content-Type' => 'application/pdf'])]);

    $response = sdk()->profile()->downloadEmploymentConfirmationLetter('ABC Mortgages Ltd', includeGrossPay: true);

    expect($response)->toBeInstanceOf(Download::class)
        ->and($response->body())->toBe('%PDF-1.4 letter-bytes')
        ->and($response->mimeType())->toBe('application/pdf');

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, '/api/v1/user/employment/confirmation-letter?')
            && str_contains($url, 'recipient_name=ABC Mortgages Ltd')
            && str_contains($url, 'include_gross_pay=1')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('omits the recipient and gross pay from the letter by default', function () {
    fakeApi(['user/employment/confirmation-letter*' => Http::response('%PDF-1.4 letter-bytes', 200, ['Content-Type' => 'application/pdf'])]);

    sdk()->profile()->downloadEmploymentConfirmationLetter();

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'recipient_name')
        && str_contains($request->url(), 'include_gross_pay=0'));
});

it('changes the password', function () {
    fakeApi(['user/password' => ['message' => 'Password changed successfully.']]);

    sdk()->profile()->changePassword('old-secret', 'new-secret', 'new-secret');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['current_password'] === 'old-secret'
        && $request['password'] === 'new-secret'
        && $request['password_confirmation'] === 'new-secret'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('throws when the current password is wrong, rather than failing silently', function () {
    // ChangePasswordRequest validates current_password with Laravel's current_password rule.
    fakeApi(['user/password' => Http::response([
        'message' => 'The current password is incorrect.',
        'errors' => ['current_password' => ['The current password is incorrect.']],
    ], 422)]);

    expect(fn () => sdk()->profile()->changePassword('wrong', 'new-secret', 'new-secret'))
        ->toThrow(fn (ApiValidationException $e) => expect($e->first('current_password'))->toBe('The current password is incorrect.'));
});

it('throws when a profile write without a body is refused', function (Closure $call, string $method, string $path) {
    fakeApi([$path => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    expect(fn () => $call(sdk()->profile()))->toThrow(SupportManagerApiException::class, 'This action is unauthorized.');
    assertSentTo($method, $path);
})->with([
    'deleteAddress()' => [fn ($profile) => $profile->deleteAddress(), 'DELETE', 'user/address'],
    'deleteAvatar()' => [fn ($profile) => $profile->deleteAvatar(), 'DELETE', 'user/avatar'],
    'changePassword()' => [fn ($profile) => $profile->changePassword('old-secret', 'new-secret', 'new-secret'), 'PUT', 'user/password'],
]);

it('uploads the avatar as a multipart file with a team header', function () {
    fakeApi(['user/avatar' => ['data' => ['avatar_url' => 'https://cdn.test/avatars/new.png']]]);

    $url = sdk()->profile()->uploadAvatar('PNG-BYTES', 'me.png');

    expect($url)->toBe('https://cdn.test/avatars/new.png');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/user/avatar')
        && $request->hasHeader('X-Team-ID', '42')
        && str_starts_with($request->header('Content-Type')[0], 'multipart/form-data; boundary=')
        && $request->hasFile('avatar', 'PNG-BYTES', 'me.png')
        && str_contains($request->body(), 'name="avatar"; filename="me.png"'));
});

it('uploads and removes the avatar through the fake', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $url = SupportManager::profile()->uploadAvatar('PNG-BYTES', 'me.png');

    expect($url)->toBe('https://cdn.test/avatars/me.png')
        ->and(SupportManager::profile()->me()?->avatar)->toBe('https://cdn.test/avatars/me.png');

    SupportManager::profile()->deleteAvatar();

    // The platform falls back to a generated initials avatar.
    expect(SupportManager::profile()->me()?->avatar)->toBe('https://ui-avatars.com/api/?name=Ada+Lovelace&background=random');
});

it('rejects an empty avatar upload through the fake', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    expect(fn () => SupportManager::profile()->uploadAvatar('', 'me.png'))
        ->toThrow(ApiValidationException::class, 'An avatar image is required.');
});

it('deletes the avatar', function () {
    fakeApi(['user/avatar' => Http::response('', 204)]);

    sdk()->profile()->deleteAvatar();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/user/avatar')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('reads bank details WITH a team header (tenant-scoped)', function () {
    fakeApi(['user/bank-details' => Http::response(profileItem('bank_details', '9', [
        'name_on_account' => 'A Lovelace',
        'sort_code' => '12-34-56',
        'account_number' => '****5678',
        'payment_method' => 'bacs',
    ]))]);

    $details = sdk()->profile()->bankDetails();

    expect($details)->toBeInstanceOf(BankDetails::class)
        ->and($details->accountNumber)->toBe('****5678');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('saves bank details WITH a team header (tenant-scoped)', function () {
    fakeApi(['user/bank-details' => Http::response(profileItem('bank_details', '9', [
        'name_on_account' => 'A Lovelace',
        'sort_code' => '12-34-56',
        'account_number' => '****5678',
    ]))]);

    $details = sdk()->profile()->saveBankDetails([
        'name_on_account' => 'A Lovelace',
        'sort_code' => '12-34-56',
        'account_number' => '12345678',
    ]);

    expect($details->nameOnAccount)->toBe('A Lovelace');
    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->hasHeader('X-Team-ID', '42'));
});

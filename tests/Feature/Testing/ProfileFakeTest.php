<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Profile\Address;
use SupportManager\Sdk\Data\Profile\AddressSuggestion;
use SupportManager\Sdk\Data\Profile\BankDetails;
use SupportManager\Sdk\Data\Profile\DirectoryUser;
use SupportManager\Sdk\Data\Profile\EmergencyContact;
use SupportManager\Sdk\Data\Profile\EmploymentSummary;
use SupportManager\Sdk\Data\Profile\Profile;
use SupportManager\Sdk\Data\Profile\SuggestedAddress;
use SupportManager\Sdk\Data\Profile\User;
use SupportManager\Sdk\Data\Profile\UserOption;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Testing\Factories\DirectoryUserFactory;
use SupportManager\Sdk\Testing\Factories\ProfileFactory;

// Host-style tests: seed factories, call the SDK normally, assert on DTOs.
// No Http::fake(), no JSON:API envelopes by hand. Profile is non-tenant but
// flows through the fake the same way every other resource does.

it('returns the identity card via GET user', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $user = SupportManager::profile()->me();

    expect($user)->toBeInstanceOf(User::class);
    assert($user instanceof User);

    expect($user->id)->toBe('1')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->role)->toBe('PA');
});

it('shows the full profile with nested address and emergency contacts', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $profile = SupportManager::profile()->show();

    expect($profile)->toBeInstanceOf(Profile::class);
    assert($profile instanceof Profile);

    expect($profile->name)->toBe('Ada Lovelace')
        ->and($profile->niNumber)->toBe('AB123456C')
        ->and($profile->hasSecondFactor)->toBeFalse()
        ->and($profile->address)->toBeInstanceOf(Address::class)
        ->and($profile->address?->town)->toBe('London')
        ->and($profile->isViewOnly)->toBeFalse()
        ->and($profile->emergencyContacts)->toHaveCount(1)
        ->and($profile->emergencyContacts[0])->toBeInstanceOf(EmergencyContact::class)
        ->and($profile->emergencyContacts[0]->name)->toBe('Jane Doe')
        ->and($profile->teams)->toBe([['id' => 42, 'name' => 'Care Team', 'role' => 'PA']]);
});

it('leaves nested relations null when seeded without details', function () {
    SupportManager::fake()->seed(ProfileFactory::new()->withoutDetails());

    $profile = SupportManager::profile()->show();

    expect($profile)->toBeInstanceOf(Profile::class);
    assert($profile instanceof Profile);

    expect($profile->address)->toBeNull()
        ->and($profile->emergencyContacts)->toBe([])
        ->and(SupportManager::profile()->emergencyContacts())->toBe([])
        ->and(SupportManager::profile()->address())->toBeNull()
        ->and(SupportManager::profile()->bankDetails())->toBeNull();
});

it('updates the profile via PATCH and persists', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $updated = SupportManager::profile()->update(['name' => 'Grace Hopper']);

    expect($updated)->toBeInstanceOf(Profile::class);
    assert($updated instanceof Profile);

    expect($updated->name)->toBe('Grace Hopper')
        ->and(SupportManager::profile()->show()?->name)->toBe('Grace Hopper');
});

it('reads a former PA profile as view-only', function () {
    SupportManager::fake()->seed(ProfileFactory::new()->viewOnly());

    expect(SupportManager::profile()->show()?->isViewOnly)->toBeTrue();
});

it('honours withSecondFactor state', function () {
    SupportManager::fake()->seed(ProfileFactory::new()->withSecondFactor());

    expect(SupportManager::profile()->show()?->hasSecondFactor)->toBeTrue();
});

it('reads, saves and deletes the address', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $address = SupportManager::profile()->address();
    expect($address)->toBeInstanceOf(Address::class);
    assert($address instanceof Address);
    expect($address->postcode)->toBe('SW1A 1AA');

    $saved = SupportManager::profile()->saveAddress([
        'address_line_one' => '99 Queen St',
        'town' => 'Bristol',
        'postcode' => 'BS1 4DJ',
    ]);

    expect($saved)->toBeInstanceOf(Address::class);
    assert($saved instanceof Address);
    expect($saved->town)->toBe('Bristol')
        ->and(SupportManager::profile()->address()?->postcode)->toBe('BS1 4DJ');

    SupportManager::profile()->deleteAddress();

    expect(SupportManager::profile()->address())->toBeNull();
});

it('searches address suggestions and saves the chosen one', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $suggestions = SupportManager::profile()->addressSuggestions('downing');

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0])->toBeInstanceOf(AddressSuggestion::class)
        ->and($suggestions[0]->id)->toBe('23747771')
        ->and(SupportManager::profile()->addressSuggestions('SW1A 2AA'))->toHaveCount(1)
        ->and(SupportManager::profile()->addressSuggestions('nowhere'))->toBe([]);

    $suggested = SupportManager::profile()->addressSuggestion($suggestions[0]->id);
    expect($suggested)->toBeInstanceOf(SuggestedAddress::class);
    assert($suggested instanceof SuggestedAddress);
    expect($suggested->addressLineTwo)->toBeNull()
        ->and($suggested->postcode)->toBe('SW1A 2AA');

    SupportManager::profile()->saveAddress($suggested->toArray());

    expect(SupportManager::profile()->address()?->addressLineOne)->toBe('10 Downing Street')
        ->and(SupportManager::profile()->addressSuggestion('missing'))->toBeNull();
});

it('rejects an address search shorter than three characters', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    expect(fn () => SupportManager::profile()->addressSuggestions('SW'))
        ->toThrow(ApiValidationException::class, 'The q field must be at least 3 characters.');
});

it('lists, saves and deletes emergency contacts', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $contacts = SupportManager::profile()->emergencyContacts();
    expect($contacts)->toHaveCount(1)
        ->and($contacts[0])->toBeInstanceOf(EmergencyContact::class)
        ->and($contacts[0]->id)->toBe('5')
        ->and($contacts[0]->relationship)->toBe('Spouse');

    $saved = SupportManager::profile()->saveEmergencyContacts([
        ['id' => 5, 'name' => 'Jane Roe', 'relationship' => 'Spouse', 'phone_number' => '+44 7700 900002'],
        ['name' => 'John Roe', 'relationship' => 'Brother', 'phone_number' => '+44 7700 900099'],
    ]);

    expect($saved)->toHaveCount(2)
        ->and($saved[0]->id)->toBe('5')
        ->and($saved[0]->name)->toBe('Jane Roe')
        ->and($saved[1]->id)->toBe('6')
        ->and($saved[1]->name)->toBe('John Roe')
        ->and(SupportManager::profile()->show()?->emergencyContacts)->toHaveCount(2);

    SupportManager::profile()->deleteEmergencyContact(5);

    $remaining = SupportManager::profile()->emergencyContacts();
    expect($remaining)->toHaveCount(1)
        ->and($remaining[0]->name)->toBe('John Roe');
});

it('rejects saving an emergency contact id the user does not own', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    expect(fn () => SupportManager::profile()->saveEmergencyContacts([
        ['id' => 999, 'name' => 'Jane', 'relationship' => 'Spouse', 'phone_number' => '+44 7700 900002'],
    ]))->toThrow(ApiValidationException::class, 'The emergency contact could not be found.');
});

it('throws when deleting an unknown emergency contact', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    expect(fn () => SupportManager::profile()->deleteEmergencyContact(999))
        ->toThrow(SupportManagerApiException::class);
});

it('reads the employment summary and downloads the confirmation letter', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $employment = SupportManager::profile()->employment();

    expect($employment)->toBeInstanceOf(EmploymentSummary::class);
    assert($employment instanceof EmploymentSummary);
    expect($employment->id)->toBe('1')
        ->and($employment->contractType)->toBe('Permanent Contracted Employee')
        ->and($employment->currentRateOfPay?->hourlyRate)->toBe(12.5)
        ->and($employment->workingPattern?->weeklyAverageHours)->toBe(37.5)
        ->and($employment->contractedAnnualSalary)->toBe(28535.0);

    $letter = SupportManager::profile()->employmentConfirmationLetter('ABC Mortgages Ltd');

    expect($letter)->toBeInstanceOf(Download::class)
        ->and($letter->body())->toBe('%PDF-1.4 confirmation-letter-bytes')
        ->and($letter->mimeType())->toBe('application/pdf');
});

it('refuses employment details to a user without any', function () {
    SupportManager::fake()->seed(ProfileFactory::new()->withoutEmployment());

    expect(fn () => SupportManager::profile()->employment())->toThrow(SupportManagerApiException::class)
        ->and(fn () => SupportManager::profile()->employmentConfirmationLetter())->toThrow(SupportManagerApiException::class);
});

it('changes the password and removes the avatar without error', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    SupportManager::profile()->changePassword('old-secret', 'new-secret', 'new-secret');
    SupportManager::profile()->deleteAvatar();

    // No exception means the routes answered with 2xx responses.
    expect(SupportManager::profile()->me())->toBeInstanceOf(User::class);
});

it('reads and saves tenant-scoped bank details', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $details = SupportManager::profile()->bankDetails();
    expect($details)->toBeInstanceOf(BankDetails::class);
    assert($details instanceof BankDetails);
    expect($details->accountNumber)->toBe('****5678');

    $saved = SupportManager::profile()->saveBankDetails([
        'name_on_account' => 'G Hopper',
        'sort_code' => '00-11-22',
        'account_number' => '87659999',
    ]);

    expect($saved)->toBeInstanceOf(BankDetails::class);
    assert($saved instanceof BankDetails);
    expect($saved->nameOnAccount)->toBe('G Hopper')
        ->and($saved->accountNumber)->toBe('****9999')
        ->and(SupportManager::profile()->bankDetails()?->sortCode)->toBe('00-11-22');
});

it('lists directory users and finds one by id', function () {
    SupportManager::fake()->seed(
        DirectoryUserFactory::new()->count(2),
        // The directory is sorted by name, so name the manager to sort last.
        DirectoryUserFactory::new()->manager()->state(['name' => 'Zara Manager']),
    );

    $users = SupportManager::users()->list();

    expect($users)->toHaveCount(3)
        ->and($users[0])->toBeInstanceOf(DirectoryUser::class)
        ->and($users[0]->name)->toBe('Teammate 1')
        ->and($users[2]->role)->toBe('Manager');

    $found = SupportManager::users()->find(2);
    expect($found)->toBeInstanceOf(DirectoryUser::class);
    assert($found instanceof DirectoryUser);
    expect($found->id)->toBe('2')
        ->and($found->name)->toBe('Teammate 2');
});

it('lists picker options for the seeded directory users, by name', function () {
    SupportManager::fake()->seed(
        DirectoryUserFactory::new()->state(['name' => 'Zoe']),
        DirectoryUserFactory::new()->state(['name' => 'Ada', 'avatar' => 'https://cdn.test/ada.png']),
    );

    $options = SupportManager::users()->options();

    expect($options)->toHaveCount(2)
        ->and($options[0])->toBeInstanceOf(UserOption::class)
        ->and($options[0]->id)->toBe('2')
        ->and($options[0]->name)->toBe('Ada')
        ->and($options[0]->avatar)->toBe('https://cdn.test/ada.png')
        ->and($options[1]->name)->toBe('Zoe');
});

it('returns null for a directory user miss', function () {
    SupportManager::fake()->seed(DirectoryUserFactory::new()->count(1));

    expect(SupportManager::users()->find(999))->toBeNull();
});

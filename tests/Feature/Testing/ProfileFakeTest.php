<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Profile\Address;
use SupportManager\Sdk\Data\Profile\BankDetails;
use SupportManager\Sdk\Data\Profile\DirectoryUser;
use SupportManager\Sdk\Data\Profile\EmergencyContact;
use SupportManager\Sdk\Data\Profile\Profile;
use SupportManager\Sdk\Data\Profile\User;
use SupportManager\Sdk\Facades\SupportManager;
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
        ->and($user->role)->toBe('employee');
});

it('shows the full profile with nested address and emergency contact', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $profile = SupportManager::profile()->show();

    expect($profile)->toBeInstanceOf(Profile::class);
    assert($profile instanceof Profile);

    expect($profile->name)->toBe('Ada Lovelace')
        ->and($profile->niNumber)->toBe('AB123456C')
        ->and($profile->hasSecondFactor)->toBeFalse()
        ->and($profile->address)->toBeInstanceOf(Address::class)
        ->and($profile->address?->town)->toBe('London')
        ->and($profile->emergencyContact)->toBeInstanceOf(EmergencyContact::class)
        ->and($profile->emergencyContact?->name)->toBe('Jane Doe')
        ->and($profile->teams)->toBe([['id' => 42, 'name' => 'Care Team', 'role' => 'employee']]);
});

it('leaves nested relations null when seeded without details', function () {
    SupportManager::fake()->seed(ProfileFactory::new()->withoutDetails());

    $profile = SupportManager::profile()->show();

    expect($profile)->toBeInstanceOf(Profile::class);
    assert($profile instanceof Profile);

    expect($profile->address)->toBeNull()
        ->and($profile->emergencyContact)->toBeNull()
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

it('reads and saves the emergency contact', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $contact = SupportManager::profile()->emergencyContact();
    expect($contact)->toBeInstanceOf(EmergencyContact::class);
    assert($contact instanceof EmergencyContact);
    expect($contact->relationship)->toBe('Spouse');

    $saved = SupportManager::profile()->saveEmergencyContact([
        'name' => 'John Roe',
        'relationship' => 'Brother',
        'phone_number' => '+44 7700 900099',
    ]);

    expect($saved)->toBeInstanceOf(EmergencyContact::class);
    assert($saved instanceof EmergencyContact);
    expect($saved->name)->toBe('John Roe')
        ->and($saved->relationship)->toBe('Brother')
        ->and(SupportManager::profile()->emergencyContact()?->name)->toBe('John Roe');
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
        'account_number' => '****9999',
    ]);

    expect($saved)->toBeInstanceOf(BankDetails::class);
    assert($saved instanceof BankDetails);
    expect($saved->nameOnAccount)->toBe('G Hopper')
        ->and(SupportManager::profile()->bankDetails()?->sortCode)->toBe('00-11-22');
});

it('lists directory users and finds one by id', function () {
    SupportManager::fake()->seed(
        DirectoryUserFactory::new()->count(2),
        DirectoryUserFactory::new()->manager(),
    );

    $users = SupportManager::users()->list();

    expect($users)->toHaveCount(3)
        ->and($users[0])->toBeInstanceOf(DirectoryUser::class)
        ->and($users[0]->name)->toBe('Teammate 1')
        ->and($users[2]->role)->toBe('manager');

    $found = SupportManager::users()->find(2);
    expect($found)->toBeInstanceOf(DirectoryUser::class);
    assert($found instanceof DirectoryUser);
    expect($found->id)->toBe('2')
        ->and($found->name)->toBe('Teammate 2');
});

it('returns null for a directory user miss', function () {
    SupportManager::fake()->seed(DirectoryUserFactory::new()->count(1));

    expect(SupportManager::users()->find(999))->toBeNull();
});

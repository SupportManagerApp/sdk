<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Profile\Address;
use SupportManager\Sdk\Data\Profile\BankDetails;
use SupportManager\Sdk\Data\Profile\EmergencyContact;
use SupportManager\Sdk\Data\Profile\Profile;
use SupportManager\Sdk\Data\Profile\User;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Resources\Profile\ProfileResource;
use SupportManager\Sdk\Testing\Factories\ProfileFactory;

function profileResource(): ProfileResource
{
    return app(ProfileResource::class);
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function profileItem(string $type, string $id, array $attributes): array
{
    return ['data' => ['type' => $type, 'id' => $id, 'attributes' => $attributes]];
}

it('fetches the current user with a team header', function () {
    Http::fake(['api.test/api/v1/user' => Http::response(profileItem('users', '7', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://cdn.test/ada.png',
        'role' => 'employee',
    ]))]);
    bootTenantSession();

    $user = profileResource()->me();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->id)->toBe('7')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->role)->toBe('employee');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/user')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('shows the full profile with nested address and emergency contact', function () {
    Http::fake(['api.test/api/v1/user/profile' => Http::response(profileItem('users', '7', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'mobile_number' => '+44 7700 900001',
        'date_of_birth' => '1990-12-10',
        'gender' => 'female',
        'ni_number' => 'AB123456C',
        'address' => ['data' => ['id' => '3', 'attributes' => ['address_line_one' => '12 King St', 'town' => 'London', 'postcode' => 'SW1A 1AA']]],
        'emergency_contact' => ['data' => ['id' => '5', 'attributes' => ['name' => 'Jane', 'relationship' => 'Spouse', 'phone_number' => '+44 7700 900002']]],
        'teams' => [['id' => 42, 'name' => 'Care Team', 'role' => 'employee']],
        'has_second_factor' => true,
        'created_at' => '2026-01-01T00:00:00Z',
    ]))]);
    bootTenantSession();

    $profile = profileResource()->show();

    expect($profile)->toBeInstanceOf(Profile::class)
        ->and($profile->name)->toBe('Ada Lovelace')
        ->and($profile->niNumber)->toBe('AB123456C')
        ->and($profile->hasSecondFactor)->toBeTrue()
        ->and($profile->address)->toBeInstanceOf(Address::class)
        ->and($profile->address->town)->toBe('London')
        ->and($profile->emergencyContact)->toBeInstanceOf(EmergencyContact::class)
        ->and($profile->emergencyContact->name)->toBe('Jane')
        ->and($profile->teams)->toBe([['id' => 42, 'name' => 'Care Team', 'role' => 'employee']]);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('leaves nested relations null when absent', function () {
    Http::fake(['api.test/api/v1/user/profile' => Http::response(profileItem('users', '7', [
        'name' => 'Ada',
        'teams' => [],
        'has_second_factor' => false,
    ]))]);
    bootTenantSession();

    $profile = profileResource()->show();

    expect($profile->address)->toBeNull()
        ->and($profile->emergencyContact)->toBeNull()
        ->and($profile->teams)->toBe([]);
});

it('updates the profile via PATCH', function () {
    Http::fake(['api.test/api/v1/user/profile' => Http::response(profileItem('users', '7', [
        'name' => 'Grace Hopper',
    ]))]);
    bootTenantSession();

    $profile = profileResource()->update(['name' => 'Grace Hopper']);

    expect($profile->name)->toBe('Grace Hopper');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['name'] === 'Grace Hopper'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('maps a 422 on profile update to a validation exception', function () {
    Http::fake(['api.test/api/v1/user/profile' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['name' => ['The name field is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        profileResource()->update(['name' => '']);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('name'))->toBe('The name field is required.');
    }
});

it('reads, saves and deletes the address with a team header', function () {
    Http::fake([
        'api.test/api/v1/user/address' => Http::response(profileItem('addresses', '3', [
            'address_line_one' => '12 King St',
            'town' => 'London',
            'postcode' => 'SW1A 1AA',
        ])),
    ]);
    bootTenantSession();

    $address = profileResource()->saveAddress([
        'address_line_one' => '12 King St',
        'town' => 'London',
        'postcode' => 'SW1A 1AA',
    ]);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($address->postcode)->toBe('SW1A 1AA');

    profileResource()->deleteAddress();

    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->hasHeader('X-Team-ID', '42'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->hasHeader('X-Team-ID', '42'));
});

it('returns null address when the server reports none', function () {
    Http::fake(['api.test/api/v1/user/address' => Http::response(['data' => null])]);
    bootTenantSession();

    expect(profileResource()->address())->toBeNull();
});

it('saves the emergency contact', function () {
    Http::fake(['api.test/api/v1/user/emergency-contact' => Http::response(profileItem('emergency-contacts', '5', [
        'name' => 'Jane',
        'relationship' => 'Spouse',
        'phone_number' => '+44 7700 900002',
    ]))]);
    bootTenantSession();

    $contact = profileResource()->saveEmergencyContact([
        'name' => 'Jane',
        'relationship' => 'Spouse',
        'phone_number' => '+44 7700 900002',
    ]);

    expect($contact)->toBeInstanceOf(EmergencyContact::class)
        ->and($contact->relationship)->toBe('Spouse');
    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('changes the password', function () {
    Http::fake(['api.test/api/v1/user/password' => Http::response(['message' => 'Password changed successfully.'])]);
    bootTenantSession();

    profileResource()->changePassword('old-secret', 'new-secret', 'new-secret');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['current_password'] === 'old-secret'
        && $request['password'] === 'new-secret'
        && $request['password_confirmation'] === 'new-secret'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('uploads the avatar as a multipart file with a team header', function () {
    Http::fake(['api.test/api/v1/user/avatar' => Http::response(['data' => ['avatar_url' => 'https://cdn.test/avatars/new.png']])]);
    bootTenantSession();

    $url = profileResource()->uploadAvatar('PNG-BYTES', 'me.png');

    expect($url)->toBe('https://cdn.test/avatars/new.png');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/user/avatar')
        && $request->hasHeader('X-Team-ID', '42')
        && str_starts_with($request->header('Content-Type')[0], 'multipart/form-data; boundary=')
        && $request->hasFile('avatar', 'PNG-BYTES', 'me.png')
        && str_contains($request->body(), 'name="avatar"; filename="me.png"'));
});

it('maps a 422 on avatar upload to a validation exception', function () {
    Http::fake(['api.test/api/v1/user/avatar' => Http::response([
        'message' => 'The avatar must be a JPG, JPEG, or PNG file.',
        'errors' => ['avatar' => ['The avatar must be a JPG, JPEG, or PNG file.']],
    ], 422)]);
    bootTenantSession();

    try {
        profileResource()->uploadAvatar('GIF89a', 'me.gif');
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('avatar'))->toBe('The avatar must be a JPG, JPEG, or PNG file.');
    }
});

it('uploads and removes the avatar through the fake', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    $url = SupportManager::profile()->uploadAvatar('PNG-BYTES', 'me.png');

    expect($url)->toBe('https://cdn.test/avatars/me.png')
        ->and(SupportManager::profile()->me()?->avatar)->toBe('https://cdn.test/avatars/me.png');

    SupportManager::profile()->deleteAvatar();

    expect(SupportManager::profile()->me()?->avatar)->toBeNull();
});

it('rejects an empty avatar upload through the fake', function () {
    SupportManager::fake()->seed(ProfileFactory::new());

    expect(fn () => SupportManager::profile()->uploadAvatar('', 'me.png'))
        ->toThrow(ApiValidationException::class, 'An avatar image is required.');
});

it('deletes the avatar', function () {
    Http::fake(['api.test/api/v1/user/avatar' => Http::response('', 204)]);
    bootTenantSession();

    profileResource()->deleteAvatar();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/v1/user/avatar')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('reads bank details WITH a team header (tenant-scoped)', function () {
    Http::fake(['api.test/api/v1/user/bank-details' => Http::response(profileItem('bank-details', '9', [
        'name_on_account' => 'A Lovelace',
        'sort_code' => '12-34-56',
        'account_number' => '****5678',
        'payment_method' => 'bacs',
    ]))]);
    bootTenantSession();

    $details = profileResource()->bankDetails();

    expect($details)->toBeInstanceOf(BankDetails::class)
        ->and($details->accountNumber)->toBe('****5678');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('saves bank details WITH a team header (tenant-scoped)', function () {
    Http::fake(['api.test/api/v1/user/bank-details' => Http::response(profileItem('bank-details', '9', [
        'name_on_account' => 'A Lovelace',
        'sort_code' => '12-34-56',
        'account_number' => '****5678',
    ]))]);
    bootTenantSession();

    $details = profileResource()->saveBankDetails([
        'name_on_account' => 'A Lovelace',
        'sort_code' => '12-34-56',
        'account_number' => '12345678',
    ]);

    expect($details->nameOnAccount)->toBe('A Lovelace');
    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->hasHeader('X-Team-ID', '42'));
});

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

/**
 * The authenticated user's full profile, as returned by `GET user/profile`.
 *
 * Mirrors the platform's `FullProfileResource`: identity, contact and personal
 * details, plus the eager-loaded {@see Address} (null when not set), every
 * {@see EmergencyContact} for the current team (empty when none) and the teams
 * the user belongs to.
 *
 * `title` is one of `MR`, `MRS`, `MISS`, `MS`, `MX`, `DR`, kept as a string
 * because the platform treats the list as open. `isViewOnly` is true for former
 * PAs, who can read their profile but get a 403 on every write; hide edit
 * controls when it is set.
 */
final readonly class Profile
{
    /**
     * @param  list<EmergencyContact>  $emergencyContacts
     * @param  list<array{id: int|null, name: string|null, role: string|null}>  $teams
     */
    public function __construct(
        public string $id,
        public ?string $title,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public ?string $mobileNumber,
        public ?string $dateOfBirth,
        public ?string $gender,
        public ?string $niNumber,
        public bool $showBirthdayOnRoster,
        public bool $isViewOnly,
        public ?Address $address,
        public array $emergencyContacts,
        public array $teams,
        public bool $hasSecondFactor,
        public ?string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            title: isset($data['title']) ? (string) $data['title'] : null,
            name: (string) ($data['name'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            avatar: isset($data['avatar']) ? (string) $data['avatar'] : null,
            mobileNumber: isset($data['mobile_number']) ? (string) $data['mobile_number'] : null,
            dateOfBirth: isset($data['date_of_birth']) ? (string) $data['date_of_birth'] : null,
            gender: isset($data['gender']) ? (string) $data['gender'] : null,
            niNumber: isset($data['ni_number']) ? (string) $data['ni_number'] : null,
            showBirthdayOnRoster: (bool) ($data['show_birthday_on_roster'] ?? false),
            isViewOnly: (bool) ($data['is_view_only'] ?? false),
            address: self::nested($data['address'] ?? null, Address::fromArray(...)),
            emergencyContacts: self::emergencyContacts($data['emergency_contacts'] ?? null),
            teams: self::teams($data['teams'] ?? null),
            hasSecondFactor: (bool) ($data['has_second_factor'] ?? false),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }

    /**
     * Hydrate a nested resource (the platform nests these as a plain JSON:API
     * `{data: {...}}` envelope), or null when absent.
     *
     * @template TNested
     *
     * @param  callable(array<string, mixed>): TNested  $factory
     * @return TNested|null
     */
    private static function nested(mixed $value, callable $factory): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $resource = is_array($value['data'] ?? null) ? $value['data'] : $value;
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];

        $flat = [
            'id' => $resource['id'] ?? null,
            ...$attributes,
        ];

        if ($flat['id'] === null && $attributes === []) {
            return null;
        }

        return $factory($flat);
    }

    /**
     * Hydrate the `emergency_contacts` list, a plain array of `{id, type,
     * attributes}` resource objects (no `data` envelope).
     *
     * @return list<EmergencyContact>
     */
    private static function emergencyContacts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $contacts = [];

        foreach ($value as $contact) {
            $hydrated = self::nested($contact, EmergencyContact::fromArray(...));

            if ($hydrated !== null) {
                $contacts[] = $hydrated;
            }
        }

        return $contacts;
    }

    /**
     * @return list<array{id: int|null, name: string|null, role: string|null}>
     */
    private static function teams(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $teams = [];

        foreach ($value as $team) {
            if (! is_array($team)) {
                continue;
            }

            $teams[] = [
                'id' => isset($team['id']) ? (int) $team['id'] : null,
                'name' => isset($team['name']) ? (string) $team['name'] : null,
                'role' => isset($team['role']) ? (string) $team['role'] : null,
            ];
        }

        return $teams;
    }
}

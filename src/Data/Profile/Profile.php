<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

/**
 * The authenticated user's full profile, as returned by `GET user/profile`.
 *
 * Mirrors the platform's `FullProfileResource`: identity, contact and personal
 * details, plus the eager-loaded {@see Address} and {@see EmergencyContact} (each
 * null when not set) and the teams the user belongs to.
 */
final readonly class Profile
{
    /**
     * @param  list<array{id: int|null, name: string|null, role: string|null}>  $teams
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public ?string $mobileNumber,
        public ?string $dateOfBirth,
        public ?string $gender,
        public ?string $niNumber,
        public ?Address $address,
        public ?EmergencyContact $emergencyContact,
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
            name: (string) ($data['name'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            avatar: isset($data['avatar']) ? (string) $data['avatar'] : null,
            mobileNumber: isset($data['mobile_number']) ? (string) $data['mobile_number'] : null,
            dateOfBirth: isset($data['date_of_birth']) ? (string) $data['date_of_birth'] : null,
            gender: isset($data['gender']) ? (string) $data['gender'] : null,
            niNumber: isset($data['ni_number']) ? (string) $data['ni_number'] : null,
            address: self::nested($data['address'] ?? null, Address::fromArray(...)),
            emergencyContact: self::nested($data['emergency_contact'] ?? null, EmergencyContact::fromArray(...)),
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

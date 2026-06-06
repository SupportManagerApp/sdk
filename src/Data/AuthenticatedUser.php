<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

/**
 * The authenticated user returned alongside an issued token pair.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}

    /**
     * @param  array{id: int, name: string, email: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: (string) $data['email'],
        );
    }
}

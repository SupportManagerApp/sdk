<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Signing;

use SupportManager\Sdk\Enums\Signing\FieldType;

/**
 * One field drawn over a document out for signing, from a signature request's
 * `fields` attribute.
 *
 * `id` is the key its value is saved under (see
 * {@see \SupportManager\Sdk\Resources\Signing\SignatureRequestsResource::saveValues()}).
 * `page` starts at 1; `x`, `y`, `width` and `height` are fractions of the
 * page's width and height, measured from its top left.
 */
final readonly class SigningField
{
    public function __construct(
        public string $id,
        public ?FieldType $type,
        public bool $isMine,
        public bool $isRequired,
        public int $page,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?string $value,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  A field entry from the request's `fields`.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: isset($data['type']) ? FieldType::tryFrom((string) $data['type']) : null,
            isMine: (bool) ($data['is_mine'] ?? false),
            isRequired: (bool) ($data['is_required'] ?? false),
            page: (int) ($data['page'] ?? 1),
            x: (float) ($data['x'] ?? 0),
            y: (float) ($data['y'] ?? 0),
            width: (float) ($data['width'] ?? 0),
            height: (float) ($data['height'] ?? 0),
            value: isset($data['value']) ? (string) $data['value'] : null,
        );
    }
}

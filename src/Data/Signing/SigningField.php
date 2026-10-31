<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Signing;

use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Hydrator;
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
        #[Defaults(1)]
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
        return Hydrator::hydrate(self::class, $data);
    }
}

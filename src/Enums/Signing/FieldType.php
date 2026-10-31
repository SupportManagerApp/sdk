<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Signing;

/**
 * The kind of a field on a document out for signing, mirroring the platform's
 * `FieldType`. It decides what a saved value must look like: `signature` and
 * `initials` take a `data:image/png;base64,` image, `date` takes `YYYY-MM-DD`,
 * `checkbox` takes `"1"` when ticked, and the rest take text.
 */
enum FieldType: string
{
    case Text = 'text';
    case Name = 'name';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Signature = 'signature';
    case Initials = 'initials';
    case Date = 'date';
    case Checkbox = 'checkbox';
}

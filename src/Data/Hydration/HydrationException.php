<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration;

use LogicException;

/**
 * A DTO the {@see Hydrator} cannot map: an untyped or union-typed parameter,
 * an unsupported `#[ListOf]` type, conflicting attributes, or an override for
 * a parameter that does not exist. Always a bug in the DTO, never in the
 * payload.
 */
final class HydrationException extends LogicException {}

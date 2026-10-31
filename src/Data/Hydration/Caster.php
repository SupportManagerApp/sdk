<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration;

/**
 * A custom cast for one constructor parameter, attached with
 * {@see Attributes\CastWith}. The caster must have a no-argument constructor;
 * the hydrator keeps one instance per class.
 */
interface Caster
{
    /**
     * @param  mixed  $value  The raw value at the parameter's key or path (null when missing).
     * @param  array<array-key, mixed>  $data  The whole input array.
     * @return mixed The value passed to the constructor as-is.
     */
    public function cast(mixed $value, array $data): mixed;
}

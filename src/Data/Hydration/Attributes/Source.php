<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Attributes;

/**
 * An attribute that tells the {@see \SupportManager\Sdk\Data\Hydration\Hydrator}
 * where in the input array a constructor parameter's value lives, in place of
 * the default snake_case key.
 *
 * At most one source attribute may sit on a parameter.
 */
interface Source
{
    /**
     * Dot-paths to try in order; the first that resolves to a non-null value
     * wins.
     *
     * @return non-empty-list<string>
     */
    public function paths(): array;
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

use Closure;

/**
 * A deterministic data factory for one SDK resource — the source of truth for
 * fake records.
 *
 * A factory mirrors a resource's wire contract: {@see self::path()} is its
 * endpoint, {@see self::type()} its JSON:API type (or null for the flat
 * singleton/plain endpoints), and {@see self::definition()} the default
 * attributes for one record. Subclasses add expressive states
 * (`->ongoing()`, `->read()`) on top.
 *
 * Values are deterministic — fixed defaults plus a 1-based `$index` for
 * sequences — so seeded data is identical run to run and tests stay
 * reproducible; ids are assigned by the {@see RecordStore} as records are
 * seeded. Hand a configured factory to {@see Fake::seed()}:
 *
 * ```php
 * SupportManager::fake()->seed(
 *     CarJourneyFactory::new()->count(3),
 *     CarJourneyFactory::new()->ongoing(),
 * );
 * ```
 *
 * @phpstan-consistent-constructor
 */
abstract class Factory
{
    /** @var list<array<string, mixed>|Closure(int): array<string, mixed>> */
    private array $states = [];

    private int $count = 1;

    /**
     * The default attributes for a single record. Called once per record, with
     * the 0-based position so subclasses can vary fields across a batch.
     *
     * @return array<string, mixed>
     */
    abstract public function definition(int $index): array;

    /**
     * The resource's collection path, relative to /api/v1 (e.g. `car-journeys`).
     */
    abstract public function path(): string;

    public static function new(): static
    {
        return new static;
    }

    /**
     * The JSON:API resource type, or null for a flat (non-JSON:API) endpoint.
     */
    public function type(): ?string
    {
        return null;
    }

    /**
     * Produce this many records.
     */
    public function count(int $count): static
    {
        $clone = clone $this;
        $clone->count = $count;

        return $clone;
    }

    /**
     * Override attributes for every record produced, either as a fixed array or
     * a closure of the 0-based index.
     *
     * @param  array<string, mixed>|Closure(int): array<string, mixed>  $state
     */
    public function state(array|Closure $state): static
    {
        $clone = clone $this;
        $clone->states[] = $state;

        return $clone;
    }

    /**
     * Build the records as plain attribute arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function make(): array
    {
        $records = [];

        for ($index = 0; $index < $this->count; $index++) {
            $attributes = $this->definition($index);

            foreach ($this->states as $state) {
                $overrides = $state instanceof Closure ? $state($index) : $state;
                $attributes = [...$attributes, ...$overrides];
            }

            $records[] = $attributes;
        }

        return $records;
    }

    /**
     * Build a single record.
     *
     * @return array<string, mixed>
     */
    public function makeOne(): array
    {
        return $this->count(1)->make()[0];
    }
}

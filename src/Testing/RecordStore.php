<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

/**
 * The fake's in-memory dataset: a list of records per collection path, with a
 * per-path id sequence.
 *
 * Records are plain attribute arrays — the same shape a {@see Factory} produces
 * and a DTO's `fromArray()` consumes — keyed only by their endpoint path
 * (`car-journeys`, `notifications`, `household/keysafe`, …). The fake transport
 * and any custom route handlers mutate the store through these methods so a host
 * test can seed records once and then exercise the real SDK against them,
 * including writes.
 */
final class RecordStore
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $records = [];

    /** @var array<string, int> */
    private array $sequence = [];

    /**
     * The next sequential id for a path, as the platform's auto-increment would
     * hand out (1, 2, 3, …).
     */
    public function nextId(string $path): string
    {
        $this->sequence[$path] = ($this->sequence[$path] ?? 0) + 1;

        return (string) $this->sequence[$path];
    }

    /**
     * Replace every record at a path.
     *
     * @param  list<array<string, mixed>>  $records
     */
    public function put(string $path, array $records): void
    {
        $this->records[$path] = array_values($records);
    }

    /**
     * Append one record, returning it.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function append(string $path, array $record): array
    {
        $this->records[$path] = [...$this->all($path), $record];

        return $record;
    }

    /**
     * Every record at a path.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $path): array
    {
        return $this->records[$path] ?? [];
    }

    /**
     * The record with the given id, or null when absent.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $path, string $id): ?array
    {
        foreach ($this->all($path) as $record) {
            if ((string) ($record['id'] ?? '') === $id) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Merge attributes into the record with the given id, returning the updated
     * record, or null when no record matches.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function update(string $path, string $id, array $attributes): ?array
    {
        foreach ($this->records[$path] ?? [] as $index => $record) {
            if ((string) ($record['id'] ?? '') === $id) {
                $this->records[$path][$index] = [...$record, ...$attributes];

                return $this->records[$path][$index];
            }
        }

        return null;
    }

    /**
     * Remove the record with the given id, returning whether one was removed.
     */
    public function delete(string $path, string $id): bool
    {
        $records = $this->all($path);
        $remaining = array_values(array_filter(
            $records,
            static fn (array $record): bool => (string) ($record['id'] ?? '') !== $id,
        ));

        if (count($remaining) === count($records)) {
            return false;
        }

        $this->records[$path] = $remaining;

        return true;
    }

    /**
     * Remove every record at a path.
     */
    public function truncate(string $path): void
    {
        $this->records[$path] = [];
    }
}

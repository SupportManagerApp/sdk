<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Data\Rota\SleepoverStartOption;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Sleepovers (the platform's shift blocks) — an evening, a sleeping night and
 * a morning, applied for and withdrawn as a whole.
 *
 * The segments of a sleepover are ordinary shifts carrying its id as
 * `shiftBlockId`; applying for or withdrawing one of them on its own is
 * rejected, so a client routes those actions here instead. `startOptions()`
 * lists the evening start times on offer, `apply()` applies for every open
 * segment at once (optionally starting later), and `cancel()` withdraws them.
 * There is no collection to list.
 *
 * ```php
 * $options = SupportManager::shiftBlocks()->startOptions($shift->shiftBlockId);
 * SupportManager::shiftBlocks()->apply($shift->shiftBlockId, '20:00');
 * SupportManager::shiftBlocks()->cancel($shift->shiftBlockId);
 * ```
 *
 * @extends resource<ShiftApplication>
 */
final class ShiftBlocksResource extends Resource
{
    /**
     * The standard evening start times within the sleepover, before its
     * sleeping night, each checked against the caller's Working Time rest.
     * Empty when there are no evening hours to trim. Like the calendar feeds,
     * the rows are flat, not JSON:API.
     *
     * @return list<SleepoverStartOption>
     */
    public function startOptions(string|int $id): array
    {
        $uri = "{$this->path()}/{$id}/start-options";
        $response = $this->get($uri);

        $this->throwForErrorResponse($response, $uri);

        /** @var array<array-key, mixed> $data */
        $data = $response->json('data') ?? [];

        $options = [];

        foreach ($data as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $options[] = SleepoverStartOption::fromArray($row);
            }
        }

        return $options;
    }

    /**
     * Apply for every open segment of the sleepover at once, returning one
     * application per segment. Every segment must be open to the caller, or
     * nothing is applied for; eligibility failures (already applied, Working
     * Time rest, a start outside the sleepover, …) are a 422.
     *
     * @param  string|null  $startTime  A later start (`H:i`) — one of the {@see self::startOptions()} or a custom time. Null starts at the sleepover's natural start.
     * @return list<ShiftApplication>
     */
    public function apply(string|int $id, ?string $startTime = null): array
    {
        $uri = "{$this->path()}/{$id}/application";
        $response = $this->post($uri, $startTime === null ? [] : ['start_time' => $startTime]);

        $this->throwForErrorResponse($response, $uri);

        return array_map($this->hydrate(...), Document::fromArray($response->json() ?? [])->resources());
    }

    /**
     * Withdraw the caller's pending applications for every segment of the
     * sleepover. A 422 when nothing is pending on it. The platform returns
     * only a confirmation message, so nothing is hydrated.
     */
    public function cancel(string|int $id): void
    {
        $uri = "{$this->path()}/{$id}/application";

        $this->throwForErrorResponse($this->delete($uri), $uri);
    }

    protected function path(): string
    {
        return 'rota/shift-blocks';
    }

    protected function hydrate(ResourceObject $object): ShiftApplication
    {
        return ShiftApplication::fromArray($object->flat());
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Rota\Anniversary;
use SupportManager\Sdk\Data\Rota\BankHoliday;
use SupportManager\Sdk\Data\Rota\Birthday;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The read-only rota calendar feeds: bank holidays, birthdays and work
 * anniversaries for a date range.
 *
 * Unlike the rest of the module these endpoints return flat `{data: [...]}`
 * rows rather than JSON:API resource objects, so each feed reads the raw `data`
 * array directly. Every feed requires a `filter[start_date]` and
 * `filter[end_date]` (`Y-m-d`).
 *
 * ```php
 * SupportManager::rotaCalendar()->bankHolidays(['filter' => ['start_date' => '2026-01-01', 'end_date' => '2026-12-31']]);
 * ```
 *
 * @extends resource<BankHoliday>
 */
final class CalendarResource extends Resource
{
    /**
     * Bank holidays for the team's region within the requested range.
     *
     * @param  array<string, mixed>  $query  Requires `filter[start_date]` and `filter[end_date]`.
     * @return list<BankHoliday>
     */
    public function bankHolidays(array $query = []): array
    {
        return $this->rows($this->get('rota/bank-holidays', $query), BankHoliday::fromArray(...));
    }

    /**
     * Team member birthdays within the requested range.
     *
     * @param  array<string, mixed>  $query  Requires `filter[start_date]` and `filter[end_date]`.
     * @return list<Birthday>
     */
    public function birthdays(array $query = []): array
    {
        return $this->rows($this->get('rota/birthdays', $query), Birthday::fromArray(...));
    }

    /**
     * Team member work anniversaries within the requested range.
     *
     * @param  array<string, mixed>  $query  Requires `filter[start_date]` and `filter[end_date]`.
     * @return list<Anniversary>
     */
    public function anniversaries(array $query = []): array
    {
        return $this->rows($this->get('rota/anniversaries', $query), Anniversary::fromArray(...));
    }

    protected function path(): string
    {
        return 'rota/bank-holidays';
    }

    protected function hydrate(ResourceObject $object): BankHoliday
    {
        return BankHoliday::fromArray($object->flat());
    }

    /**
     * Hydrate a flat calendar feed's `data` rows into DTOs.
     *
     * @template TRow
     *
     * @param  callable(array<string, mixed>): TRow  $into
     * @return list<TRow>
     */
    private function rows(Response $response, callable $into): array
    {
        $this->throwForErrorResponse($response, $response->effectiveUri()?->getPath() ?? 'rota/calendar');

        /** @var array<array-key, mixed> $data */
        $data = $response->json('data') ?? [];

        $rows = [];

        foreach ($data as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $into($row);
            }
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\Anniversary;
use SupportManager\Sdk\Data\Rota\BankHoliday;
use SupportManager\Sdk\Data\Rota\Birthday;
use SupportManager\Sdk\Data\Rota\CalendarFeed;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The rota calendar feeds: bank holidays, birthdays and work anniversaries for
 * a date range, plus the address of the caller's iCalendar subscription feed.
 *
 * Unlike the rest of the module these endpoints return flat `{data: ...}`
 * bodies rather than JSON:API resource objects, so each reads `data` with
 * {@see Resource::dataList()} or {@see Resource::raw()}. Every date feed requires a `filter[start_date]` and
 * `filter[end_date]` (`Y-m-d`).
 *
 * ```php
 * SupportManager::rotaCalendar()->bankHolidays(['filter' => ['start_date' => '2026-01-01', 'end_date' => '2026-12-31']]);
 * SupportManager::rotaCalendar()->feed()->url;
 * SupportManager::rotaCalendar()->regenerateFeed();
 * ```
 *
 * @extends Resource<BankHoliday>
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
        return $this->dataList($this->get('rota/bank-holidays', $query), BankHoliday::class);
    }

    /**
     * Team member birthdays within the requested range.
     *
     * @param  array<string, mixed>  $query  Requires `filter[start_date]` and `filter[end_date]`.
     * @return list<Birthday>
     */
    public function birthdays(array $query = []): array
    {
        return $this->dataList($this->get('rota/birthdays', $query), Birthday::class);
    }

    /**
     * Team member work anniversaries within the requested range.
     *
     * @param  array<string, mixed>  $query  Requires `filter[start_date]` and `filter[end_date]`.
     * @return list<Anniversary>
     */
    public function anniversaries(array $query = []): array
    {
        return $this->dataList($this->get('rota/anniversaries', $query), Anniversary::class);
    }

    /**
     * The address of the caller's iCalendar feed of the team appointments they
     * can see; `url` is null until one is issued with {@see self::regenerateFeed()}.
     */
    public function feed(): CalendarFeed
    {
        return CalendarFeed::fromArray($this->raw($this->get('rota/calendar-feed')));
    }

    /**
     * Issue a new feed address. The old one stops working, so calendars
     * subscribed to it must subscribe again.
     */
    public function regenerateFeed(): CalendarFeed
    {
        return CalendarFeed::fromArray($this->raw($this->post('rota/calendar-feed/token')));
    }

    protected function path(): string
    {
        return 'rota/bank-holidays';
    }

    protected function hydrate(ResourceObject $object): BankHoliday
    {
        return BankHoliday::fromArray($object->flat());
    }
}

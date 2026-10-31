<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use DateTimeInterface;
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
 * {@see Resource::dataList()} or {@see Resource::raw()}. Every date feed takes
 * the range as two required arguments, a `Y-m-d` string or a
 * `DateTimeInterface` each, both inclusive; the platform 422s without them, or
 * when `$to` is before `$from`.
 *
 * ```php
 * SupportManager::rotaCalendar()->bankHolidays('2026-01-01', '2026-12-31');
 * SupportManager::rotaCalendar()->birthdays(now()->startOfMonth(), now()->endOfMonth());
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
     * @param  string|DateTimeInterface  $from  The first day, `Y-m-d` if a string.
     * @param  string|DateTimeInterface  $to  The last day, on or after `$from`.
     * @return list<BankHoliday>
     */
    public function bankHolidays(string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        return $this->dataList($this->get('rota/bank-holidays', self::range($from, $to)), BankHoliday::class);
    }

    /**
     * Team member birthdays within the requested range.
     *
     * @param  string|DateTimeInterface  $from  The first day, `Y-m-d` if a string.
     * @param  string|DateTimeInterface  $to  The last day, on or after `$from`.
     * @return list<Birthday>
     */
    public function birthdays(string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        return $this->dataList($this->get('rota/birthdays', self::range($from, $to)), Birthday::class);
    }

    /**
     * Team member work anniversaries within the requested range.
     *
     * @param  string|DateTimeInterface  $from  The first day, `Y-m-d` if a string.
     * @param  string|DateTimeInterface  $to  The last day, on or after `$from`.
     * @return list<Anniversary>
     */
    public function anniversaries(string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        return $this->dataList($this->get('rota/anniversaries', self::range($from, $to)), Anniversary::class);
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

    /**
     * The `filter[start_date]`/`filter[end_date]` pair every date feed requires.
     *
     * @return array{filter: array{start_date: string, end_date: string}}
     */
    private static function range(string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        return ['filter' => [
            'start_date' => $from instanceof DateTimeInterface ? $from->format('Y-m-d') : $from,
            'end_date' => $to instanceof DateTimeInterface ? $to->format('Y-m-d') : $to,
        ]];
    }
}

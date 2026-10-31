<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use SupportManager\Sdk\Data\Leave\LeaveBalance;
use SupportManager\Sdk\Data\Leave\LeaveCalculation;
use SupportManager\Sdk\Data\Leave\LeaveYears;
use SupportManager\Sdk\Data\Leave\OvertimeLog;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * A user's leave balance for a leave year, the years they can browse, and their
 * overtime (TOIL) log.
 *
 * This is a singleton-style endpoint, not a JSON:API collection: there is no
 * `query()`/`find()`. The balance body is a plain `{data: {year, hours, sleeps}}`
 * envelope (no resource `id`/`type`), so it is read with the base
 * {@see Resource::data()} helper rather than `item()`.
 *
 * ```php
 * SupportManager::leaveBalance()->show();
 * SupportManager::leaveBalance()->show(['year' => 2025]);
 * SupportManager::leaveBalance()->show(['include' => 'breakdown'])->breakdown;
 * SupportManager::leaveBalance()->forUser(3);
 * SupportManager::leaveBalance()->availableYears();
 * SupportManager::leaveBalance()->overtime(['year' => 2026]);
 * SupportManager::leaveBalance()->calculate(['start_date' => '2026-07-01', 'type' => 'Whole Days'])->balanceAfterRequest->hours;
 * ```
 *
 * @extends resource<LeaveBalance>
 */
final class LeaveBalanceResource extends Resource
{
    /**
     * The current user's balance, for the current leave year or the given one.
     * Pass `include=breakdown` to add where the entitlement comes from
     * ({@see LeaveBalance::$breakdown}).
     *
     * @param  array<string, mixed>  $query  Optional `year` and `include`.
     */
    public function show(array $query = []): ?LeaveBalance
    {
        return $this->data($this->get($this->path(), $query), LeaveBalance::class);
    }

    /**
     * Another user's balance (privileged), by user id, for the current leave
     * year or the given one.
     *
     * @param  array<string, mixed>  $query  Optional `year`.
     */
    public function forUser(int|string $userId, array $query = []): ?LeaveBalance
    {
        return $this->data($this->get("{$this->path()}/{$userId}", $query), LeaveBalance::class);
    }

    /**
     * The leave years the current user can browse, from the year their
     * employment started to the later of the current year and their latest
     * entitlement, with display labels. Years locked at year end are included
     * and also listed in {@see LeaveYears::$locked}.
     */
    public function availableYears(): ?LeaveYears
    {
        return $this->data($this->get("{$this->path()}/years"), LeaveYears::class);
    }

    /**
     * The current user's monthly overtime log for the current leave year or the
     * given one: hours and sleeps worked, and the leave accrued from them.
     *
     * @param  array<string, mixed>  $query  Optional `year`.
     */
    public function overtime(array $query = []): ?OvertimeLog
    {
        return $this->data($this->get('leave/overtime', $query), OvertimeLog::class);
    }

    /**
     * Calculate the balance impact of a prospective request: the hours and
     * sleeps it needs, the balance for its leave year, and what would remain.
     *
     * @param  array<string, mixed>  $data  `start_date` and `type` (`Whole Days` or `Custom Timings`), plus optional `end_date`, `start_time`, `end_time` and (privileged) `user_id`.
     */
    public function calculate(array $data): ?LeaveCalculation
    {
        return $this->data($this->post("{$this->path()}/calculate", $data), LeaveCalculation::class);
    }

    protected function path(): string
    {
        return 'leave/balance';
    }

    protected function hydrate(ResourceObject $object): LeaveBalance
    {
        return LeaveBalance::fromArray($object->flat());
    }
}

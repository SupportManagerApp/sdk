<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Leave\LeaveBalance;
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
 * envelope (no resource `id`/`type`), so it is hydrated straight from the
 * response `data` block rather than the base `item()` helper.
 *
 * ```php
 * SupportManager::leaveBalance()->show();
 * SupportManager::leaveBalance()->show(['year' => 2025]);
 * SupportManager::leaveBalance()->show(['include' => 'breakdown'])->breakdown;
 * SupportManager::leaveBalance()->forUser(3);
 * SupportManager::leaveBalance()->availableYears();
 * SupportManager::leaveBalance()->overtime(['year' => 2026]);
 * SupportManager::leaveBalance()->calculate(['start_date' => '2026-07-01', 'type' => 'Whole Days']);
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
        return $this->balanceFromResponse($this->get('leave/balance', $query));
    }

    /**
     * Another user's balance (privileged), by user id, for the current leave
     * year or the given one.
     *
     * @param  array<string, mixed>  $query  Optional `year`.
     */
    public function forUser(int|string $userId, array $query = []): ?LeaveBalance
    {
        return $this->balanceFromResponse($this->get("leave/balance/{$userId}", $query));
    }

    /**
     * The leave years the current user can browse, from the year their
     * employment started to the later of the current year and their latest
     * entitlement, with display labels. Years locked at year end are included
     * and also listed in {@see LeaveYears::$locked}.
     */
    public function availableYears(): ?LeaveYears
    {
        $response = $this->get('leave/balance/years');

        $this->throwForErrorResponse($response, 'leave/balance/years');

        $data = $response->json('data');

        return is_array($data) ? LeaveYears::fromArray($data) : null;
    }

    /**
     * The current user's monthly overtime log for the current leave year or the
     * given one: hours and sleeps worked, and the leave accrued from them.
     *
     * @param  array<string, mixed>  $query  Optional `year`.
     */
    public function overtime(array $query = []): ?OvertimeLog
    {
        $response = $this->get('leave/overtime', $query);

        $this->throwForErrorResponse($response, 'leave/overtime');

        $data = $response->json('data');

        return is_array($data) ? OvertimeLog::fromArray($data) : null;
    }

    /**
     * Calculate the balance impact of a prospective request.
     *
     * @param  array<string, mixed>  $data
     */
    public function calculate(array $data): ?LeaveBalance
    {
        return $this->balanceFromResponse($this->post('leave/balance/calculate', $data));
    }

    protected function path(): string
    {
        return 'leave/balance';
    }

    protected function hydrate(ResourceObject $object): LeaveBalance
    {
        return LeaveBalance::fromArray($object->flat());
    }

    /**
     * Hydrate a {@see LeaveBalance} from a balance response's `data` block,
     * which is a plain object rather than a JSON:API resource object.
     */
    private function balanceFromResponse(Response $response): ?LeaveBalance
    {
        $this->throwForErrorResponse($response, 'leave/balance');

        $data = $response->json('data');

        return is_array($data) ? LeaveBalance::fromArray($data) : null;
    }
}

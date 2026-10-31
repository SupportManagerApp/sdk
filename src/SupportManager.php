<?php

declare(strict_types=1);

namespace SupportManager\Sdk;

use BadMethodCallException;
use Illuminate\Contracts\Container\Container;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Resources\CarJourneys\CarJourneysResource;
use SupportManager\Sdk\Resources\Compliance\DBSChecksResource;
use SupportManager\Sdk\Resources\Compliance\DriversLicenceChecksResource;
use SupportManager\Sdk\Resources\Compliance\RightToWorkResource;
use SupportManager\Sdk\Resources\Documents\DocumentFoldersResource;
use SupportManager\Sdk\Resources\Documents\DocumentsResource;
use SupportManager\Sdk\Resources\Documents\PoliciesResource;
use SupportManager\Sdk\Resources\Household\ChoreCategoriesResource;
use SupportManager\Sdk\Resources\Household\ChoresResource;
use SupportManager\Sdk\Resources\Household\KeysafeResource;
use SupportManager\Sdk\Resources\Household\MealPlanResource;
use SupportManager\Sdk\Resources\Household\ShoppingListItemsResource;
use SupportManager\Sdk\Resources\Leave\AdminLeaveResource;
use SupportManager\Sdk\Resources\Leave\LeaveBalanceResource;
use SupportManager\Sdk\Resources\Leave\LeaveEntitlementsResource;
use SupportManager\Sdk\Resources\Leave\LeaveRequestsResource;
use SupportManager\Sdk\Resources\Leave\ManualAdjustmentsResource;
use SupportManager\Sdk\Resources\Notifications\NotificationsResource;
use SupportManager\Sdk\Resources\Payroll\AutomaticPayEnhancementsResource;
use SupportManager\Sdk\Resources\Payroll\EstimatedPayResource;
use SupportManager\Sdk\Resources\Payroll\P45sResource;
use SupportManager\Sdk\Resources\Payroll\P60sResource;
use SupportManager\Sdk\Resources\Payroll\PayEnhancementsResource;
use SupportManager\Sdk\Resources\Payroll\PayRunsResource;
use SupportManager\Sdk\Resources\Payroll\PayslipsResource;
use SupportManager\Sdk\Resources\Payroll\TimesheetsResource;
use SupportManager\Sdk\Resources\Profile\ProfileResource;
use SupportManager\Sdk\Resources\Profile\UsersResource;
use SupportManager\Sdk\Resources\PushNotifications\DeviceTokensResource;
use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\Resources\Rota\AppointmentsResource;
use SupportManager\Sdk\Resources\Rota\CalendarResource;
use SupportManager\Sdk\Resources\Rota\QuickAddResource;
use SupportManager\Sdk\Resources\Rota\ShiftAmendmentRequestsResource;
use SupportManager\Sdk\Resources\Rota\ShiftApplicationsResource;
use SupportManager\Sdk\Resources\Rota\ShiftBlocksResource;
use SupportManager\Sdk\Resources\Rota\ShiftRequestsResource;
use SupportManager\Sdk\Resources\Rota\ShiftsResource;
use SupportManager\Sdk\Resources\Tasks\TasksResource;
use SupportManager\Sdk\Resources\Teams\TeamsResource;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;
use SupportManager\Sdk\Testing\Fake;

/**
 * The single fluent entry point to the SDK, surfaced as the `SupportManager`
 * facade.
 *
 * It gathers the moving parts a host app would otherwise wire by hand —
 * authentication, the session, the current team, and tenant-scoped domain
 * resources — behind one object:
 *
 * ```php
 * SupportManager::auth()->login($email, $password);
 * SupportManager::team()->id();
 * SupportManager::journeys()->query()->filter('ongoing', true)->get();
 * ```
 *
 * Domain resources are registered by name (typically from a module's service
 * provider via {@see self::register()}) and then resolved fluently through
 * `__call`, so the core SDK stays free of domain code while host apps still get
 * the `SupportManager::journeys()` ergonomics.
 */
final class SupportManager
{
    /** @var array<string, class-string<Resource<*>>> */
    private array $resources = [];

    private ?Fake $fake = null;

    public function __construct(
        private readonly SupportManagerClient $client,
        private readonly AuthSession $session,
        private readonly CurrentTeam $team,
        private readonly Container $container,
    ) {}

    /**
     * Resolve a registered resource by its fluent name.
     *
     * @param  array<int, mixed>  $arguments
     * @return Resource<*>
     */
    public function __call(string $name, array $arguments): Resource
    {
        if (! isset($this->resources[$name])) {
            throw new BadMethodCallException(
                "No SupportManager resource is registered as [{$name}]. Register one with SupportManager::register('{$name}', …)."
            );
        }

        return $this->resource($this->resources[$name]);
    }

    /**
     * The authentication client: login, social login, refresh, logout, device
     * verification, and password reset.
     */
    public function auth(): SupportManagerClient
    {
        return $this->client;
    }

    /**
     * The persisted device session (tokens, cached user, current team).
     */
    public function session(): AuthSession
    {
        return $this->session;
    }

    /**
     * The current tenant team resolver.
     */
    public function team(): CurrentTeam
    {
        return $this->team;
    }

    /**
     * Swap the SDK onto an in-memory fake of the API, for tests and local
     * development.
     *
     * Seed it with {@see Testing\Factory factories} and every
     * SDK call runs through the real client pipeline into the fake instead of the
     * network — so the host app never hand-rolls HTTP mocks of the JSON:API wire
     * format. Installing also primes a fake authenticated session, so
     * tenant-scoped calls work without a login. Repeated calls return the same
     * fake, so seeding accumulates:
     *
     * ```php
     * SupportManager::fake()->seed(CarJourneyFactory::new()->count(3));
     * SupportManager::journeys()->all();
     * ```
     */
    public function fake(): Fake
    {
        return $this->fake ??= (new Fake(
            $this->session,
            (string) $this->container->make('config')->get('supportmanager.base_url'),
        ))->install();
    }

    // ──────────────────────────── Domain resources ────────────────────────────
    //
    // First-party module resources get an explicit typed accessor so the concrete
    // class (and its DTO types) flow through to IDE autocomplete and PHPStan. Each
    // is just sugar over resource(); host apps can still register their own under
    // a fluent name via register()/__call.

    /** Car journeys — team vehicle trip tracking. */
    public function journeys(): CarJourneysResource
    {
        return $this->resource(CarJourneysResource::class);
    }

    /** Compliance — DBS checks (read-only). */
    public function dbsChecks(): DBSChecksResource
    {
        return $this->resource(DBSChecksResource::class);
    }

    /** Compliance — right to work records (read-only). */
    public function rightToWork(): RightToWorkResource
    {
        return $this->resource(RightToWorkResource::class);
    }

    /** Compliance — drivers licence checks (read-only). */
    public function driversLicenceChecks(): DriversLicenceChecksResource
    {
        return $this->resource(DriversLicenceChecksResource::class);
    }

    /** Documents — stored documents, with download. */
    public function documents(): DocumentsResource
    {
        return $this->resource(DocumentsResource::class);
    }

    /** Documents — document folders (read-only). */
    public function documentFolders(): DocumentFoldersResource
    {
        return $this->resource(DocumentFoldersResource::class);
    }

    /** Documents — policies, with acknowledgement. */
    public function policies(): PoliciesResource
    {
        return $this->resource(PoliciesResource::class);
    }

    /** Profile — the current user's profile, addresses, contacts, bank details. */
    public function profile(): ProfileResource
    {
        return $this->resource(ProfileResource::class);
    }

    /** Profile — the team user directory. */
    public function users(): UsersResource
    {
        return $this->resource(UsersResource::class);
    }

    /** Household — shopping list items. */
    public function shoppingList(): ShoppingListItemsResource
    {
        return $this->resource(ShoppingListItemsResource::class);
    }

    /** Household — chores. */
    public function chores(): ChoresResource
    {
        return $this->resource(ChoresResource::class);
    }

    /** Household — chore categories (read-only). */
    public function choreCategories(): ChoreCategoriesResource
    {
        return $this->resource(ChoreCategoriesResource::class);
    }

    /** Household — the weekly meal plan (singleton). */
    public function mealPlan(): MealPlanResource
    {
        return $this->resource(MealPlanResource::class);
    }

    /** Household — the keysafe (singleton). */
    public function keysafe(): KeysafeResource
    {
        return $this->resource(KeysafeResource::class);
    }

    /** Leave — leave requests, with the approval workflow. */
    public function leaveRequests(): LeaveRequestsResource
    {
        return $this->resource(LeaveRequestsResource::class);
    }

    /** Leave — manual balance adjustments (privileged). */
    public function leaveAdjustments(): ManualAdjustmentsResource
    {
        return $this->resource(ManualAdjustmentsResource::class);
    }

    /** Leave — entitlements (read-only). */
    public function leaveEntitlements(): LeaveEntitlementsResource
    {
        return $this->resource(LeaveEntitlementsResource::class);
    }

    /** Leave — balance lookups and calculation. */
    public function leaveBalance(): LeaveBalanceResource
    {
        return $this->resource(LeaveBalanceResource::class);
    }

    /** Leave — the privileged admin surface (pending queues, bulk actions, summary). */
    public function leaveAdmin(): AdminLeaveResource
    {
        return $this->resource(AdminLeaveResource::class);
    }

    /** Notifications — the current user's database notifications. */
    public function notifications(): NotificationsResource
    {
        return $this->resource(NotificationsResource::class);
    }

    /** Push notifications — the current user's device (FCM) tokens and prompt state. */
    public function deviceTokens(): DeviceTokensResource
    {
        return $this->resource(DeviceTokensResource::class);
    }

    /** Teams — the teams the current user belongs to (non-tenant). */
    public function teams(): TeamsResource
    {
        return $this->resource(TeamsResource::class);
    }

    /** Tasks — the dashboard onboarding/compliance task list. */
    public function tasks(): TasksResource
    {
        return $this->resource(TasksResource::class);
    }

    /** Rota — shifts, including today/available and applications. */
    public function shifts(): ShiftsResource
    {
        return $this->resource(ShiftsResource::class);
    }

    /** Rota — the current user's requests to change one of their shifts. */
    public function shiftAmendmentRequests(): ShiftAmendmentRequestsResource
    {
        return $this->resource(ShiftAmendmentRequestsResource::class);
    }

    /** Rota — the current user's shift applications. */
    public function shiftApplications(): ShiftApplicationsResource
    {
        return $this->resource(ShiftApplicationsResource::class);
    }

    /** Rota — sleepovers (shift blocks), applied for and withdrawn as a whole. */
    public function shiftBlocks(): ShiftBlocksResource
    {
        return $this->resource(ShiftBlocksResource::class);
    }

    /** Rota — the current user's requests for extra hours. */
    public function shiftRequests(): ShiftRequestsResource
    {
        return $this->resource(ShiftRequestsResource::class);
    }

    /** Rota — appointments, including upcoming. */
    public function appointments(): AppointmentsResource
    {
        return $this->resource(AppointmentsResource::class);
    }

    /** Rota — read-only calendar feeds (bank holidays, birthdays, anniversaries). */
    public function rotaCalendar(): CalendarResource
    {
        return $this->resource(CalendarResource::class);
    }

    /** Rota — quick-add text parsing (submit, then poll by token). */
    public function quickAdd(): QuickAddResource
    {
        return $this->resource(QuickAddResource::class);
    }

    /** Payroll — payslips, with latest and download. */
    public function payslips(): PayslipsResource
    {
        return $this->resource(PayslipsResource::class);
    }

    /** Payroll — P60 documents, with download. */
    public function p60s(): P60sResource
    {
        return $this->resource(P60sResource::class);
    }

    /** Payroll — P45 documents, with download. */
    public function p45s(): P45sResource
    {
        return $this->resource(P45sResource::class);
    }

    /** Payroll — timesheets, with download. */
    public function timesheets(): TimesheetsResource
    {
        return $this->resource(TimesheetsResource::class);
    }

    /** Payroll — pay enhancements (full CRUD). */
    public function payEnhancements(): PayEnhancementsResource
    {
        return $this->resource(PayEnhancementsResource::class);
    }

    /** Payroll — the current user's estimated pay (singleton). */
    public function estimatedPay(): EstimatedPayResource
    {
        return $this->resource(EstimatedPayResource::class);
    }

    /** Payroll — pay runs (read-only). */
    public function payRuns(): PayRunsResource
    {
        return $this->resource(PayRunsResource::class);
    }

    /** Payroll — automatic pay enhancement settings (singleton). */
    public function automaticPayEnhancements(): AutomaticPayEnhancementsResource
    {
        return $this->resource(AutomaticPayEnhancementsResource::class);
    }

    /**
     * Register a domain resource under a fluent name, e.g.
     * `register('journeys', CarJourneysResource::class)` enables
     * `SupportManager::journeys()`.
     *
     * @param  class-string<Resource<*>>  $resourceClass
     */
    public function register(string $name, string $resourceClass): void
    {
        $this->resources[$name] = $resourceClass;
    }

    /**
     * Resolve a domain resource by its class, fully wired through the container.
     *
     * @template TResource of Resource<*>
     *
     * @param  class-string<TResource>  $resourceClass
     * @return TResource
     */
    public function resource(string $resourceClass): Resource
    {
        /** @var TResource $resource */
        $resource = $this->container->make($resourceClass);

        return $resource;
    }
}

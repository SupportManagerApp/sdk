<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Facades;

use Illuminate\Support\Facades\Facade;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;

/**
 * Facade for the {@see \SupportManager\Sdk\SupportManager} entry point.
 *
 * Auto-aliased as `SupportManager` via package discovery, so host apps can write
 * `SupportManager::auth()->login(...)` straight away.
 *
 * @method static SupportManagerClient auth()
 * @method static AuthSession session()
 * @method static CurrentTeam team()
 * @method static \SupportManager\Sdk\Testing\Fake fake()
 * @method static void register(string $name, class-string<Resource<*>> $resourceClass)
 * @method static T resource<T of Resource>(class-string<T> $resourceClass)
 * @method static \SupportManager\Sdk\Resources\Accidents\AccidentsResource accidents()
 * @method static \SupportManager\Sdk\Resources\CarJourneys\CarJourneysResource journeys()
 * @method static \SupportManager\Sdk\Resources\Compliance\DBSChecksResource dbsChecks()
 * @method static \SupportManager\Sdk\Resources\Compliance\RightToWorkResource rightToWork()
 * @method static \SupportManager\Sdk\Resources\Compliance\DriversLicenceChecksResource driversLicenceChecks()
 * @method static \SupportManager\Sdk\Resources\Dashboard\DashboardResource dashboard()
 * @method static \SupportManager\Sdk\Resources\Documents\DocumentsResource documents()
 * @method static \SupportManager\Sdk\Resources\Documents\DocumentFoldersResource documentFolders()
 * @method static \SupportManager\Sdk\Resources\Documents\PoliciesResource policies()
 * @method static \SupportManager\Sdk\Resources\Profile\ProfileResource profile()
 * @method static \SupportManager\Sdk\Resources\Profile\UsersResource users()
 * @method static \SupportManager\Sdk\Resources\Notifications\NotificationsResource notifications()
 * @method static \SupportManager\Sdk\Resources\PushNotifications\DeviceTokensResource deviceTokens()
 * @method static \SupportManager\Sdk\Resources\Teams\TeamsResource teams()
 * @method static \SupportManager\Sdk\Resources\Signing\SignatureRequestsResource signatureRequests()
 * @method static \SupportManager\Sdk\Resources\Tasks\TasksResource tasks()
 * @method static \SupportManager\Sdk\Resources\Household\ShoppingListItemsResource shoppingList()
 * @method static \SupportManager\Sdk\Resources\Household\ChoresResource chores()
 * @method static \SupportManager\Sdk\Resources\Household\ChoreCategoriesResource choreCategories()
 * @method static \SupportManager\Sdk\Resources\Household\MealPlanResource mealPlan()
 * @method static \SupportManager\Sdk\Resources\Household\KeysafeResource keysafe()
 * @method static \SupportManager\Sdk\Resources\Leave\LeaveRequestsResource leaveRequests()
 * @method static \SupportManager\Sdk\Resources\Leave\ManualAdjustmentsResource leaveAdjustments()
 * @method static \SupportManager\Sdk\Resources\Leave\LeaveEntitlementsResource leaveEntitlements()
 * @method static \SupportManager\Sdk\Resources\Leave\LeaveBalanceResource leaveBalance()
 * @method static \SupportManager\Sdk\Resources\Leave\AdminLeaveResource leaveAdmin()
 * @method static \SupportManager\Sdk\Resources\Rota\ShiftsResource shifts()
 * @method static \SupportManager\Sdk\Resources\Rota\ShiftApplicationsResource shiftApplications()
 * @method static \SupportManager\Sdk\Resources\Rota\AppointmentsResource appointments()
 * @method static \SupportManager\Sdk\Resources\Rota\CalendarResource rotaCalendar()
 * @method static \SupportManager\Sdk\Resources\Rota\QuickAddResource quickAdd()
 * @method static \SupportManager\Sdk\Resources\Payroll\PayslipsResource payslips()
 * @method static \SupportManager\Sdk\Resources\Payroll\P60sResource p60s()
 * @method static \SupportManager\Sdk\Resources\Payroll\P45sResource p45s()
 * @method static \SupportManager\Sdk\Resources\Payroll\TimesheetsResource timesheets()
 * @method static \SupportManager\Sdk\Resources\Payroll\PayEnhancementsResource payEnhancements()
 * @method static \SupportManager\Sdk\Resources\Payroll\EstimatedPayResource estimatedPay()
 * @method static \SupportManager\Sdk\Resources\Payroll\PayRunsResource payRuns()
 * @method static \SupportManager\Sdk\Resources\Payroll\AutomaticPayEnhancementsResource automaticPayEnhancements()
 *
 * @see \SupportManager\Sdk\SupportManager
 */
final class SupportManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SupportManager\Sdk\SupportManager::class;
    }
}

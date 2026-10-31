<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SessionExpiredException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Accidents\AccidentsResource;
use SupportManager\Sdk\Resources\CarJourneys\CarJourneysResource;
use SupportManager\Sdk\Resources\Compliance\DBSChecksResource;
use SupportManager\Sdk\Resources\Compliance\DriversLicenceChecksResource;
use SupportManager\Sdk\Resources\Compliance\RightToWorkResource;
use SupportManager\Sdk\Resources\Dashboard\DashboardResource;
use SupportManager\Sdk\Resources\Documents\DocumentFoldersResource;
use SupportManager\Sdk\Resources\Documents\DocumentsResource;
use SupportManager\Sdk\Resources\Documents\PoliciesResource;
use SupportManager\Sdk\Resources\Household\BinDayResource;
use SupportManager\Sdk\Resources\Household\ChoreCategoriesResource;
use SupportManager\Sdk\Resources\Household\ChoresResource;
use SupportManager\Sdk\Resources\Household\ConsumablesResource;
use SupportManager\Sdk\Resources\Household\KeysafeResource;
use SupportManager\Sdk\Resources\Household\MealPlanResource;
use SupportManager\Sdk\Resources\Household\ShoppingListItemsResource;
use SupportManager\Sdk\Resources\Leave\AdminLeaveResource;
use SupportManager\Sdk\Resources\Leave\LeaveBalanceResource;
use SupportManager\Sdk\Resources\Leave\LeaveEntitlementsResource;
use SupportManager\Sdk\Resources\Leave\LeaveRequestsResource;
use SupportManager\Sdk\Resources\Leave\ManualAdjustmentsResource;
use SupportManager\Sdk\Resources\Leave\NotWorkingNoticesResource;
use SupportManager\Sdk\Resources\Notifications\NotificationsResource;
use SupportManager\Sdk\Resources\Payroll\AutomaticPayEnhancementsResource;
use SupportManager\Sdk\Resources\Payroll\EstimatedPayResource;
use SupportManager\Sdk\Resources\Payroll\P45sResource;
use SupportManager\Sdk\Resources\Payroll\P60sResource;
use SupportManager\Sdk\Resources\Payroll\PayEnhancementsResource;
use SupportManager\Sdk\Resources\Payroll\PayRunsResource;
use SupportManager\Sdk\Resources\Payroll\PayslipsResource;
use SupportManager\Sdk\Resources\Payroll\StarterDeclarationResource;
use SupportManager\Sdk\Resources\Payroll\TimesheetsResource;
use SupportManager\Sdk\Resources\Profile\ProfileResource;
use SupportManager\Sdk\Resources\Profile\UsersResource;
use SupportManager\Sdk\Resources\PushNotifications\DeviceTokensResource;
use SupportManager\Sdk\Resources\Rota\AppointmentsResource;
use SupportManager\Sdk\Resources\Rota\CalendarResource;
use SupportManager\Sdk\Resources\Rota\QuickAddResource;
use SupportManager\Sdk\Resources\Rota\ShiftAmendmentRequestsResource;
use SupportManager\Sdk\Resources\Rota\ShiftApplicationsResource;
use SupportManager\Sdk\Resources\Rota\ShiftBlocksResource;
use SupportManager\Sdk\Resources\Rota\ShiftRequestsResource;
use SupportManager\Sdk\Resources\Rota\ShiftsResource;
use SupportManager\Sdk\Resources\Security\SocialAccountsResource;
use SupportManager\Sdk\Resources\Security\TrustedDevicesResource;
use SupportManager\Sdk\Resources\Security\TwoFactorResource;
use SupportManager\Sdk\Resources\Signing\SignatureRequestsResource;
use SupportManager\Sdk\Resources\Tasks\TasksResource;
use SupportManager\Sdk\Resources\Teams\TeamsResource;
use SupportManager\Sdk\Session\AuthSession;

/*
 * Behaviour every resource shares, tested once per resource instead of once
 * per file: the bearer token and X-Team-ID go out, and error responses map to
 * the SDK's typed exceptions (see MapsApiErrors) or, on a 401, to a refresh
 * and SessionExpiredException. Each row is one cheap call on one resource,
 * reached through its SupportManager accessor; error bodies are the plain
 * Laravel `{message, errors?}` the contract check expects.
 *
 * A new resource needs a row here: the last test fails until it has one.
 * Per-resource tests keep only what is specific to that resource, such as a
 * 404 that reads as null or a 422 whose message the platform documents.
 */

/**
 * accessor, resource class, call, method, path (relative to /api/v1), options.
 *
 * Options: `tenant` (default true) is whether the call carries X-Team-ID;
 * `nullOn404` is whether a 404 reads as null rather than throwing;
 * `fieldErrors` (default true) is false where the spec documents this call's
 * 422 as a bare message (the platform's invalid-team response) with no
 * `errors`, so only the message-only 422 is faked for it.
 *
 * @return array<string, array{string, class-string, Closure(object): mixed, string, string, array{tenant?: bool, nullOn404?: bool, fieldErrors?: bool}}>
 */
function sharedResourceCalls(): array
{
    return [
        'accidents' => ['accidents', AccidentsResource::class, fn (AccidentsResource $r) => $r->all(), 'GET', 'accidents', []],
        'car journeys' => ['journeys', CarJourneysResource::class, fn (CarJourneysResource $r) => $r->query()->get(), 'GET', 'car-journeys', ['fieldErrors' => false]],
        'DBS checks' => ['dbsChecks', DBSChecksResource::class, fn (DBSChecksResource $r) => $r->submit('2026-03-14', '001234567890', 'PDF-BYTES', 'dbs.pdf'), 'POST', 'compliance/dbs-checks', []],
        'drivers licence checks' => ['driversLicenceChecks', DriversLicenceChecksResource::class, fn (DriversLicenceChecksResource $r) => $r->submit('HARBO801234AB9CD', 'Xy7Kp2Qm', declarationAccepted: true), 'POST', 'compliance/drivers-licence-checks', []],
        'right to work' => ['rightToWork', RightToWorkResource::class, fn (RightToWorkResource $r) => $r->submit('Immigration Share Code', shareCode: 'W4X 7YZ 9AB'), 'POST', 'compliance/right-to-work', []],
        'dashboard' => ['dashboard', DashboardResource::class, fn (DashboardResource $r) => $r->layout(), 'GET', 'dashboard', []],
        'document folders' => ['documentFolders', DocumentFoldersResource::class, fn (DocumentFoldersResource $r) => $r->find(9), 'GET', 'documents/document-folders/9', ['nullOn404' => true]],
        'documents' => ['documents', DocumentsResource::class, fn (DocumentsResource $r) => $r->query()->get(), 'GET', 'documents/documents', []],
        'policies' => ['policies', PoliciesResource::class, fn (PoliciesResource $r) => $r->query()->get(), 'GET', 'documents/policies', ['fieldErrors' => false]],
        'bin day' => ['binDay', BinDayResource::class, fn (BinDayResource $r) => $r->show(), 'GET', 'household/bin-day', ['fieldErrors' => false]],
        'chore categories' => ['choreCategories', ChoreCategoriesResource::class, fn (ChoreCategoriesResource $r) => $r->query()->get(), 'GET', 'household/chore-categories', ['fieldErrors' => false]],
        'chores' => ['chores', ChoresResource::class, fn (ChoresResource $r) => $r->markNotNeeded([1]), 'POST', 'household/chores/not-needed', []],
        'consumables' => ['consumables', ConsumablesResource::class, fn (ConsumablesResource $r) => $r->adjustStock(1, 1), 'PATCH', 'household/consumable-variants/1/stock', []],
        'keysafe' => ['keysafe', KeysafeResource::class, fn (KeysafeResource $r) => $r->update('5678'), 'PATCH', 'household/keysafe', []],
        'meal plan' => ['mealPlan', MealPlanResource::class, fn (MealPlanResource $r) => $r->removeDay('monday'), 'DELETE', 'household/meal-plan/days/monday', ['fieldErrors' => false]],
        'shopping list' => ['shoppingList', ShoppingListItemsResource::class, fn (ShoppingListItemsResource $r) => $r->create(['name' => 'Milk', 'quantity' => 1]), 'POST', 'household/shopping-list', []],
        'leave admin' => ['leaveAdmin', AdminLeaveResource::class, fn (AdminLeaveResource $r) => $r->pendingRequests(), 'GET', 'leave/admin/requests/pending', []],
        'leave balance' => ['leaveBalance', LeaveBalanceResource::class, fn (LeaveBalanceResource $r) => $r->overtime(['year' => 2025]), 'GET', 'leave/overtime', []],
        'leave entitlements' => ['leaveEntitlements', LeaveEntitlementsResource::class, fn (LeaveEntitlementsResource $r) => $r->query()->get(), 'GET', 'leave/entitlements', ['fieldErrors' => false]],
        'leave requests' => ['leaveRequests', LeaveRequestsResource::class, fn (LeaveRequestsResource $r) => $r->create(['start_date' => '2026-07-01', 'end_date' => '2026-07-03', 'type' => 'Whole Days']), 'POST', 'leave/requests', []],
        'leave adjustments' => ['leaveAdjustments', ManualAdjustmentsResource::class, fn (ManualAdjustmentsResource $r) => $r->mine(), 'GET', 'leave/adjustments/mine', []],
        'not working notices' => ['leaveNotWorkingNotices', NotWorkingNoticesResource::class, fn (NotWorkingNoticesResource $r) => $r->withdraw(12), 'PATCH', 'leave/not-working/12/withdraw', []],
        'notifications' => ['notifications', NotificationsResource::class, fn (NotificationsResource $r) => $r->markAsRead('9d8f1a2b-1c3d-4e5f-8a9b-000000000999'), 'PATCH', 'notifications/9d8f1a2b-1c3d-4e5f-8a9b-000000000999/read', []],
        'automatic pay enhancements' => ['automaticPayEnhancements', AutomaticPayEnhancementsResource::class, fn (AutomaticPayEnhancementsResource $r) => $r->update(['bank_holiday_enhancement_id' => 9]), 'PUT', 'payroll/automatic-pay-enhancements', []],
        'estimated pay' => ['estimatedPay', EstimatedPayResource::class, fn (EstimatedPayResource $r) => $r->show(), 'GET', 'payroll/estimated-pay', []],
        'P45s' => ['p45s', P45sResource::class, fn (P45sResource $r) => $r->query()->get(), 'GET', 'payroll/p45s', ['fieldErrors' => false]],
        'P60s' => ['p60s', P60sResource::class, fn (P60sResource $r) => $r->query()->get(), 'GET', 'payroll/p60s', ['fieldErrors' => false]],
        'pay enhancements' => ['payEnhancements', PayEnhancementsResource::class, fn (PayEnhancementsResource $r) => $r->create(['name' => 'Bank Holiday', 'enhancement_factor' => '1']), 'POST', 'payroll/pay-enhancements', []],
        'pay runs' => ['payRuns', PayRunsResource::class, fn (PayRunsResource $r) => $r->all(), 'GET', 'payroll/pay-runs', ['fieldErrors' => false]],
        'payslips' => ['payslips', PayslipsResource::class, fn (PayslipsResource $r) => $r->query()->get(), 'GET', 'payroll/payslips', ['fieldErrors' => false]],
        'starter declaration' => ['starterDeclaration', StarterDeclarationResource::class, fn (StarterDeclarationResource $r) => $r->show(), 'GET', 'payroll/starter-declaration', []],
        'timesheets' => ['timesheets', TimesheetsResource::class, fn (TimesheetsResource $r) => $r->query()->get(), 'GET', 'payroll/timesheets', ['fieldErrors' => false]],
        'profile' => ['profile', ProfileResource::class, fn (ProfileResource $r) => $r->deleteEmergencyContact(99), 'DELETE', 'user/emergency-contacts/99', []],
        'users' => ['users', UsersResource::class, fn (UsersResource $r) => $r->options(), 'GET', 'users/options', []],
        'device tokens' => ['deviceTokens', DeviceTokensResource::class, fn (DeviceTokensResource $r) => $r->destroy(999), 'DELETE', 'push-notifications/tokens/999', []],
        'social accounts' => ['socialAccounts', SocialAccountsResource::class, fn (SocialAccountsResource $r) => $r->link('google', 'ya29.token'), 'POST', 'user/social-accounts/google', ['tenant' => false]],
        'trusted devices' => ['trustedDevices', TrustedDevicesResource::class, fn (TrustedDevicesResource $r) => $r->destroy(999), 'DELETE', 'user/trusted-devices/999', ['tenant' => false]],
        'two-factor' => ['twoFactor', TwoFactorResource::class, fn (TwoFactorResource $r) => $r->confirmTotpSetup('123456'), 'POST', 'user/two-factor/totp/confirm', ['tenant' => false]],
        'appointments' => ['appointments', AppointmentsResource::class, fn (AppointmentsResource $r) => $r->update(4, ['title' => 'Mine now']), 'PATCH', 'rota/appointments/4', []],
        'rota calendar' => ['rotaCalendar', CalendarResource::class, fn (CalendarResource $r) => $r->regenerateFeed(), 'POST', 'rota/calendar-feed/token', []],
        'quick add' => ['quickAdd', QuickAddResource::class, fn (QuickAddResource $r) => $r->parse('dentist tuesday'), 'POST', 'rota/quick-add/parse', []],
        'shift amendment requests' => ['shiftAmendmentRequests', ShiftAmendmentRequestsResource::class, fn (ShiftAmendmentRequestsResource $r) => $r->all(), 'GET', 'rota/shift-amendment-requests', []],
        'shift applications' => ['shiftApplications', ShiftApplicationsResource::class, fn (ShiftApplicationsResource $r) => $r->query()->get(), 'GET', 'rota/shift-applications', []],
        'shift blocks' => ['shiftBlocks', ShiftBlocksResource::class, fn (ShiftBlocksResource $r) => $r->startOptions(9), 'GET', 'rota/shift-blocks/9/start-options', []],
        'shift requests' => ['shiftRequests', ShiftRequestsResource::class, fn (ShiftRequestsResource $r) => $r->destroy(12), 'DELETE', 'rota/shift-requests/12', []],
        'shifts' => ['shifts', ShiftsResource::class, fn (ShiftsResource $r) => $r->requestAmendment(51, ['proposed_start_time' => '15:00', 'proposed_end_time' => '23:00']), 'POST', 'rota/shifts/51/amendment-requests', []],
        'signature requests' => ['signatureRequests', SignatureRequestsResource::class, fn (SignatureRequestsResource $r) => $r->saveValues(12, ['sig-1' => null]), 'PUT', 'signing/requests/12/values', []],
        'tasks' => ['tasks', TasksResource::class, fn (TasksResource $r) => $r->complete('nope'), 'POST', 'tasks/nope/complete', []],
        'teams' => ['teams', TeamsResource::class, fn (TeamsResource $r) => $r->count(), 'GET', 'user/teams/count', ['tenant' => false]],
    ];
}

dataset('resource calls', fn (): array => sharedResourceCalls());

dataset('resource calls with field errors', fn (): array => array_filter(
    sharedResourceCalls(),
    static fn (array $row): bool => $row[5]['fieldErrors'] ?? true,
));

/**
 * Fake one status for the row's path and make the call through the facade
 * accessor. Returns what the call returned and what it threw.
 *
 * @param  class-string  $class
 * @return array{mixed, Throwable|null}
 */
function callWithResponse(string $accessor, string $class, Closure $call, string $path, mixed $body, int $status): array
{
    fakeApi([$path.'*' => Http::response($body, $status)]);

    $resource = sdk()->{$accessor}();

    expect($resource)->toBeInstanceOf($class);

    try {
        return [$call($resource), null];
    } catch (Throwable $e) {
        return [null, $e];
    }
}

it('sends the bearer token, and the team header when tenant-scoped', function (string $accessor, string $class, Closure $call, string $method, string $path, array $options) {
    callWithResponse($accessor, $class, $call, $path, ['message' => 'This action is unauthorized.'], 403);

    $tenant = $options['tenant'] ?? true;

    assertSentTo($method, $path, fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-1')
        && ($tenant ? $request->hasHeader('X-Team-ID', '42') : ! $request->hasHeader('X-Team-ID')));
})->with('resource calls');

it('maps a 422 with field errors to a validation exception', function (string $accessor, string $class, Closure $call, string $method, string $path) {
    [, $e] = callWithResponse($accessor, $class, $call, $path, [
        'message' => 'The name field is required.',
        'errors' => ['name' => ['The name field is required.', 'The name must be a string.']],
    ], 422);

    expect($e)->toBeInstanceOf(ApiValidationException::class)
        ->and($e->getCode())->toBe(422)
        ->and($e->getMessage())->toBe('The name field is required.')
        ->and($e->first('name'))->toBe('The name field is required.')
        ->and($e->first('email'))->toBeNull()
        ->and($e->errors)->toBe(['name' => ['The name field is required.', 'The name must be a string.']]);
})->with('resource calls with field errors');

it('maps the invalid-team 422, which has no field errors, to a validation exception', function (string $accessor, string $class, Closure $call, string $method, string $path) {
    [, $e] = callWithResponse($accessor, $class, $call, $path, [
        'message' => 'Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.',
    ], 422);

    expect($e)->toBeInstanceOf(ApiValidationException::class)
        ->and($e->getMessage())->toBe('Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.')
        ->and($e->errors)->toBe([]);
})->with('resource calls');

it('maps a 403 to an api exception carrying the status and message', function (string $accessor, string $class, Closure $call, string $method, string $path) {
    [, $e] = callWithResponse($accessor, $class, $call, $path, ['message' => 'This action is unauthorized.'], 403);

    expect($e)->not->toBeNull()
        ->and($e::class)->toBe(SupportManagerApiException::class)
        ->and($e->getCode())->toBe(403)
        ->and($e->getMessage())->toBe('This action is unauthorized.');
})->with('resource calls');

it('maps a 404 to an api exception, or to null where the call documents it', function (string $accessor, string $class, Closure $call, string $method, string $path, array $options) {
    [$result, $e] = callWithResponse($accessor, $class, $call, $path, ['message' => 'Not Found'], 404);

    if ($options['nullOn404'] ?? false) {
        expect($e)->toBeNull()
            ->and($result)->toBeNull();

        return;
    }

    expect($e)->not->toBeNull()
        ->and($e::class)->toBe(SupportManagerApiException::class)
        ->and($e->getCode())->toBe(404)
        ->and($e->getMessage())->toBe('Not Found');
})->with('resource calls');

it('maps a JSON 500 to an api exception carrying the status and message', function (string $accessor, string $class, Closure $call, string $method, string $path) {
    [, $e] = callWithResponse($accessor, $class, $call, $path, ['message' => 'Server Error'], 500);

    expect($e)->not->toBeNull()
        ->and($e::class)->toBe(SupportManagerApiException::class)
        ->and($e->getCode())->toBe(500)
        ->and($e->getMessage())->toBe('Server Error');
})->with('resource calls');

it('maps an HTML 500 page to an api exception instead of an empty result', function (string $accessor, string $class, Closure $call, string $method, string $path) {
    [, $e] = callWithResponse($accessor, $class, $call, $path, '<html>boom</html>', 500);

    expect($e)->not->toBeNull()
        ->and($e::class)->toBe(SupportManagerApiException::class)
        ->and($e->getCode())->toBe(500)
        ->and($e->getMessage())->toBe('SupportManager is having problems right now (HTTP 500). Please try again shortly.');
})->with('resource calls');

it('refreshes on a 401 and, when the refresh is rejected, ends the session', function (string $accessor, string $class, Closure $call, string $method, string $path) {
    fakeApi([
        'auth/refresh' => Http::response(['message' => 'The refresh token is invalid.'], 401),
        $path.'*' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    expect(fn () => $call(sdk()->{$accessor}()))->toThrow(SessionExpiredException::class)
        ->and(app(AuthSession::class)->check())->toBeFalse();

    assertSentTo('POST', 'auth/refresh', fn (Request $request): bool => $request['refresh_token'] === 'refresh-1');
    Http::assertSentCount(2);
})->with('resource calls');

it('has a row for every resource in src/Resources', function () {
    $root = dirname(__DIR__, 2).'/src/Resources';
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file instanceof SplFileInfo || ! str_ends_with($file->getFilename(), 'Resource.php')) {
            continue;
        }

        $class = 'SupportManager\\Sdk\\Resources\\'.str_replace('/', '\\', mb_substr($file->getPathname(), mb_strlen($root) + 1, -4));

        if (! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    $covered = array_map(static fn (array $row): string => $row[1], sharedResourceCalls());

    sort($classes);
    sort($covered);

    expect($classes)->not->toBeEmpty()
        ->and(array_values(array_diff($classes, $covered)))->toBe([], 'Add a row to sharedResourceCalls() in '.basename(__FILE__).' for each of these resources.')
        ->and($covered)->toBe(array_values(array_unique($covered)));
});

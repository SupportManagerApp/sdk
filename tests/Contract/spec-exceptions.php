<?php

declare(strict_types=1);

/*
 * Places where the vendored OpenAPI spec is wrong and the platform code is
 * right. Each entry patches one operation's schema at one JSON pointer, so it
 * cannot hide drift elsewhere. See the README's "Contract tests" section.
 *
 *   operation  'METHOD /template', exactly as in the spec
 *   in         'request', or the response status code
 *   pointer    JSON pointer into the body; `*` means every array item
 *   schema     the real schema at that pointer (adds a missing property), and/or
 *   nullable   true: the value may be null
 *   optional   true: the value may be absent
 *   reason     one line: what the platform really does, and where
 *
 * Fix the platform's spec and drop the entry once it is synced; the suite
 * reports entries that are no longer needed.
 */

// ── Shared schema fragments ──────────────────────────────────────────────────

$dateTime = ['type' => 'string', 'format' => 'date-time'];
$nullableDateTime = ['type' => 'string', 'format' => 'date-time', 'nullable' => true];
$nullableDate = ['type' => 'string', 'format' => 'date', 'nullable' => true];
$nullableString = ['type' => 'string', 'nullable' => true];
$money = ['type' => 'number'];
$userRef = [
    'type' => 'object',
    'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
    'required' => ['id', 'name'],
];

$rows = static fn (array $properties): array => [
    'type' => 'array',
    'items' => ['type' => 'object', 'properties' => $properties],
];

$payslipAttributes = static fn (bool $show): array => [
    'type' => 'object',
    'properties' => [
        'team_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer'],
        'user' => $userRef,
        'process_date' => $dateTime,
        'pay_period_start' => $nullableDate,
        'pay_period_end' => $nullableDate,
        'tax_code' => $nullableString,
        'ni_letter' => $nullableString,
        'total_gross' => $money,
        'taxable_gross' => $money,
        'niable_gross' => $money,
        'net_pay_formatted' => $nullableString,
        'deductions_total_formatted' => $nullableString,
        'status' => ['type' => 'string'],
        'published_at' => $nullableDateTime,
        'first_opened_at' => $nullableDateTime,
        'created_at' => $dateTime,
        'updated_at' => $dateTime,
        ...($show ? [
            'payments' => $rows([
                'description' => ['type' => 'string'],
                'hours' => $money,
                'rate' => $money,
                'total' => $money,
            ]),
            'deductions' => $rows(['description' => ['type' => 'string'], 'total' => $money]),
            'period_totals' => $rows(['description' => ['type' => 'string'], 'amount' => $money]),
            'ytd_totals' => $rows(['description' => ['type' => 'string'], 'amount' => $money]),
            'employer' => [
                'type' => 'object',
                'properties' => ['name' => $nullableString, 'paye_reference' => $nullableString],
            ],
        ] : []),
    ],
];

$p60Attributes = static fn (bool $show): array => [
    'type' => 'object',
    'properties' => [
        'team_id' => ['type' => 'integer'],
        'employee_id' => ['type' => 'integer'],
        'employee' => $userRef,
        'tax_year' => ['type' => 'string'],
        'payroll_number' => $nullableString,
        'download_available' => ['type' => 'boolean'],
        'published_at' => $nullableDateTime,
        'viewed' => ['type' => 'boolean'],
        'created_at' => $dateTime,
        'updated_at' => $dateTime,
        ...($show ? [
            'pay_details' => $rows(['id' => ['type' => 'integer'], 'description' => ['type' => 'string'], 'amount' => $money]),
            'ni_contributions' => $rows([
                'id' => ['type' => 'integer'],
                'ni_letter' => ['type' => 'string'],
                'earnings_at_lel' => $money,
                'earnings_above_lel' => $money,
                'earnings_above_pt' => $money,
                'earnings_above_uel' => $money,
                'employee_contributions' => $money,
                'employer_contributions' => $money,
            ]),
            'statutory_payments' => $rows(['id' => ['type' => 'integer'], 'type' => ['type' => 'string'], 'amount' => $money]),
            'other_details' => $rows(['id' => ['type' => 'integer'], 'description' => ['type' => 'string'], 'value' => $nullableString]),
        ] : []),
    ],
];

$wallClockReason = static fn (string $resource): string => "Platform sends process_date via ApiResource::wallClock() (toIso8601String, e.g. 2026-06-30T00:00:00+01:00), not a bare date; see src/Payroll/Http/Resources/{$resource}.php.";

$nestedAddress = [
    'type' => 'object',
    'properties' => [
        'data' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'type' => ['type' => 'string'],
                'attributes' => [
                    'type' => 'object',
                    'properties' => [
                        'address_line_one' => ['type' => 'string'],
                        'address_line_two' => ['type' => 'string', 'nullable' => true],
                        'address_line_three' => ['type' => 'string', 'nullable' => true],
                        'town' => ['type' => 'string'],
                        'county' => ['type' => 'string', 'nullable' => true],
                        'postcode' => ['type' => 'string'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
            ],
        ],
    ],
];

$workingPatternReason = 'An entitlement need not have a working pattern: StoreEntitlementRequest takes working_pattern_id as nullable and LeaveEntitlementResource sends it as-is (null); the spec types it as a non-null integer (src/Leave/Http/Resources/LeaveEntitlementResource.php).';

$effectiveUntilReason = 'An entitlement can be open-ended: StoreEntitlementRequest takes leave_year_end as nullable, stored as effective_until and sent as null by LeaveEntitlementResource and AdminBalanceController; the spec types it as a non-null date.';

$percentages = ['type' => 'object', 'properties' => ['approved' => ['type' => 'number'], 'pending' => ['type' => 'number'], 'remaining' => ['type' => 'number']], 'required' => ['approved', 'pending', 'remaining']];
$percentagesReason = 'The summary lines are LeaveBalanceMetric::toArray(), which includes percentages {approved, pending, remaining}; the spec leaves it out (src/Leave/ValueObjects/LeaveBalanceMetric.php:52, AdminBalanceController::show).';

$nestedRotaEntry = ['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/RotaEntry']], 'required' => ['data']];
$nestedRotaEntryReason = 'ShiftApplicationResource nests `new RotaEntryResource(...)`, which serialises with its own {data: {id, type, attributes}} wrapper; the spec documents the bare resource object (src/Rota/Http/Resources/ShiftApplicationResource.php).';

$plannedFor = ['type' => 'string', 'format' => 'date', 'nullable' => true];
$plannedForReason = 'ChoreResource sends planned_for (the date a recurring chore is next planned, toDateString() or null); the spec omits it (src/Household/Http/Resources/ChoreResource.php:42).';

return [
    // ── Payroll ──────────────────────────────────────────────────────────────
    // Where the YAML's attributes object is wrong in many places at once, the
    // entry replaces that one attributes object with what the resource emits.
    [
        'operation' => 'GET /payroll/pay-runs',
        'in' => 200,
        'pointer' => '/data/*',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'month_key' => ['type' => 'string'],
                'month_label' => ['type' => 'string'],
                'process_date' => $nullableDate,
                'payslip_count' => ['type' => 'integer'],
                'published_count' => ['type' => 'integer'],
                'status' => ['type' => 'string', 'enum' => ['draft', 'partial', 'published']],
                'total_gross' => $money,
                'total_net' => $money,
            ],
            'required' => ['month_key', 'month_label', 'process_date', 'payslip_count', 'published_count', 'status', 'total_gross', 'total_net'],
        ],
        'reason' => 'PayRunController::index returns flat rows (no id/type/attributes envelope) from PayRunResource::toArray(): month_key, month_label, process_date (toDateString), payslip_count, published_count, status, and total_gross/total_net as rounded floats; see src/Payroll/Http/Resources/PayRunResource.php.',
    ],
    [
        'operation' => 'GET /payroll/payslips',
        'in' => 200,
        'pointer' => '/data/*/attributes',
        'schema' => $payslipAttributes(false),
        'reason' => "PayslipResource::toAttributes() sends user (index eager-loads 'user:id,name'), pay period/tax fields, float total_gross/taxable_gross/niable_gross, wall-clock process_date, published_at/first_opened_at/created_at/updated_at; the YAML lists only six fields with total_gross as a string. See src/Payroll/Http/Resources/PayslipResource.php.",
    ],
    [
        'operation' => 'GET /payroll/payslips/{id}',
        'in' => 200,
        'pointer' => '/data/attributes',
        'schema' => $payslipAttributes(true),
        'reason' => 'PayslipResource::toAttributes() (show loads user, payments, deductions, periodTotals, ytdTotals) sends float money everywhere, period/ytd totals as {description, amount} (PayslipPeriodTotalResource/PayslipYtdTotalResource), wall-clock process_date, plus user/deductions_total_formatted/published_at/created_at/updated_at the YAML omits. See src/Payroll/Http/Resources/Payslip*Resource.php.',
    ],
    [
        'operation' => 'GET /payroll/p60s',
        'in' => 200,
        'pointer' => '/data/*/attributes',
        'schema' => $p60Attributes(false),
        'reason' => "P60Resource::toAttributes() sends employee (index eager-loads 'employee:id,name'), published_at, viewed, created_at and updated_at on the index too; see src/Payroll/Http/Resources/P60Resource.php and P60Controller::index.",
    ],
    [
        'operation' => 'GET /payroll/p60s/{id}',
        'in' => 200,
        'pointer' => '/data/attributes',
        'schema' => $p60Attributes(true),
        'reason' => 'P60Resource::toAttributes() sends pay_details/ni_contributions/statutory_payments/other_details as lists of rows (each ->map()ed with an id and float amounts), not the totals objects the YAML describes, plus employee/created_at/updated_at; see src/Payroll/Http/Resources/P60Resource.php.',
    ],
    [
        'operation' => 'POST /tasks/{taskKey}/complete',
        'in' => 422,
        'pointer' => '/errors',
        'schema' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
        'reason' => "CompleteTaskRequest validates user_id (nullable|integer|exists:team_user), so the FormRequest's 422 carries Laravel's errors bag, e.g. errors.user_id; see src/App/Tasks/Http/Requests/CompleteTaskRequest.php.",
    ],
    [
        'operation' => 'GET /payroll/timesheets',
        'in' => 200,
        'pointer' => '/data/*/attributes/process_date',
        'schema' => $dateTime,
        'reason' => $wallClockReason('TimesheetResource'),
    ],
    [
        'operation' => 'GET /payroll/timesheets/{id}',
        'in' => 200,
        'pointer' => '/data/attributes/process_date',
        'schema' => $dateTime,
        'reason' => $wallClockReason('TimesheetResource'),
    ],

    // ── Profile and teams ────────────────────────────────────────────────────
    [
        'operation' => 'GET /user/profile',
        'in' => 200,
        'pointer' => '/data/attributes/address',
        'schema' => $nestedAddress,
        'nullable' => true,
        'reason' => 'FullProfileResource nests `new AddressResource(...)`, which JSON-serialises via JsonApiResource::resolve() as {data: {id, type, attributes}}, not the bare resource object the spec documents (src/Profile/Http/Resources/FullProfileResource.php; src/Profile/Tests/Feature/Api/ProfileControllerTest.php asserts data.attributes.address.data.attributes.*).',
    ],
    [
        'operation' => 'PATCH /user/profile',
        'in' => 200,
        'pointer' => '/data/attributes/address',
        'schema' => $nestedAddress,
        'nullable' => true,
        'reason' => 'ProfileController::update returns the same FullProfileResource, nesting the address as {data: {id, type, attributes}} (src/Profile/Http/Resources/FullProfileResource.php).',
    ],
    [
        'operation' => 'GET /user/teams',
        'in' => 200,
        'pointer' => '/data/*/attributes/display_name',
        'schema' => ['type' => 'string'],
        'reason' => 'TeamJsonResource::toAttributes() also emits display_name (Team::display_name), which the spec omits (src/App/Teams/Http/Resources/TeamJsonResource.php).',
    ],

    // ── Documents and car journeys ───────────────────────────────────────────
    [
        'operation' => 'GET /documents/documents',
        'in' => 422,
        'pointer' => '/errors',
        'schema' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
        'optional' => true,
        'reason' => 'An unknown filter[scope] fails $request->validate() (src/Documents/Http/Controllers/Api/DocumentController.php:28), a standard Laravel ValidationException body with errors.filter.scope; the spec documents only the Error schema.',
    ],
    [
        'operation' => 'GET /documents/documents',
        'in' => 200,
        'pointer' => '/data/*/attributes/user',
        'nullable' => true,
        'reason' => 'A team-scoped document has no user_id; the eager-loaded user relation is null and whenLoaded() then yields null (src/Documents/Http/Resources/DocumentResource.php:25), so team documents carry user: null.',
    ],
    [
        'operation' => 'GET /car-journeys',
        'in' => 'query',
        'pointer' => '/sort',
        'schema' => ['type' => 'string', 'pattern' => '^-?(trip_start|trip_end|created_at|driver_name)(,-?(trip_start|trip_end|created_at|driver_name))*$'],
        'reason' => 'Spatie QueryBuilder allowedSorts (src/CarJourneys/Http/Controllers/CarJourneyController.php:43) accepts a comma-separated list of sorts, e.g. sort=-trip_start,driver_name; the spec enum allows only one.',
    ],

    // ── Leave ────────────────────────────────────────────────────────────────
    ['operation' => 'GET /leave/admin/entitlements', 'in' => 200, 'pointer' => '/data/*/attributes/working_pattern_id', 'nullable' => true, 'reason' => $workingPatternReason],
    ['operation' => 'POST /leave/admin/entitlements', 'in' => 201, 'pointer' => '/data/attributes/working_pattern_id', 'nullable' => true, 'reason' => $workingPatternReason],
    ['operation' => 'PUT /leave/admin/entitlements/{id}', 'in' => 200, 'pointer' => '/data/attributes/working_pattern_id', 'nullable' => true, 'reason' => $workingPatternReason],

    ['operation' => 'GET /leave/admin/entitlements', 'in' => 200, 'pointer' => '/data/*/attributes/effective_until', 'nullable' => true, 'reason' => $effectiveUntilReason],
    ['operation' => 'POST /leave/admin/entitlements', 'in' => 201, 'pointer' => '/data/attributes/effective_until', 'nullable' => true, 'reason' => $effectiveUntilReason],
    ['operation' => 'PUT /leave/admin/entitlements/{id}', 'in' => 200, 'pointer' => '/data/attributes/effective_until', 'nullable' => true, 'reason' => $effectiveUntilReason],
    ['operation' => 'GET /leave/admin/balances/{userId}', 'in' => 200, 'pointer' => '/data/entitlement/effective_until', 'nullable' => true, 'reason' => $effectiveUntilReason],

    ['operation' => 'GET /leave/admin/balances/{userId}', 'in' => 200, 'pointer' => '/data/summary/hours/percentages', 'schema' => $percentages, 'reason' => $percentagesReason],
    ['operation' => 'GET /leave/admin/balances/{userId}', 'in' => 200, 'pointer' => '/data/summary/sleeps/percentages', 'schema' => $percentages, 'reason' => $percentagesReason],

    // ── Rota ─────────────────────────────────────────────────────────────────
    ['operation' => 'GET /rota/shifts', 'in' => 'query', 'pointer' => '/filter[start_date]', 'optional' => true, 'reason' => 'ShiftController::index takes start_date as an optional AllowedFilter::callback and falls back to the current week; nothing requires it (src/Rota/Http/Controllers/Api/ShiftController.php:73,102).'],
    ['operation' => 'GET /rota/shifts', 'in' => 'query', 'pointer' => '/filter[end_date]', 'optional' => true, 'reason' => 'ShiftController::index takes end_date as an optional AllowedFilter::callback; nothing requires it (src/Rota/Http/Controllers/Api/ShiftController.php:76).'],
    ['operation' => 'GET /rota/shift-applications', 'in' => 200, 'pointer' => '/data/*/attributes/is_provisional', 'schema' => ['type' => 'boolean'], 'reason' => 'ShiftApplicationResource sends is_provisional to privileged users (whether the application is an admin\'s pencilled-in allocation); the spec omits it (src/Rota/Http/Resources/ShiftApplicationResource.php).'],
    ['operation' => 'GET /rota/shift-applications', 'in' => 200, 'pointer' => '/data/*/attributes/rota_entry', 'schema' => $nestedRotaEntry, 'reason' => $nestedRotaEntryReason],
    ['operation' => 'POST /rota/shifts/{id}/apply', 'in' => 201, 'pointer' => '/data/*/attributes/rota_entry', 'schema' => $nestedRotaEntry, 'reason' => $nestedRotaEntryReason],
    ['operation' => 'POST /rota/shift-blocks/{id}/application', 'in' => 201, 'pointer' => '/data/*/attributes/rota_entry', 'schema' => $nestedRotaEntry, 'reason' => $nestedRotaEntryReason],

    // ── Household ────────────────────────────────────────────────────────────
    ['operation' => 'GET /household/chores', 'in' => 200, 'pointer' => '/data/*/attributes/planned_for', 'schema' => $plannedFor, 'reason' => $plannedForReason],
    ['operation' => 'POST /household/chores/complete', 'in' => 200, 'pointer' => '/data/*/attributes/planned_for', 'schema' => $plannedFor, 'reason' => $plannedForReason],
    ['operation' => 'POST /household/chores/not-needed', 'in' => 200, 'pointer' => '/data/*/attributes/planned_for', 'schema' => $plannedFor, 'reason' => $plannedForReason],
    ['operation' => 'PATCH /household/chores/{chore}/complete', 'in' => 200, 'pointer' => '/data/attributes/planned_for', 'schema' => $plannedFor, 'reason' => $plannedForReason],
    ['operation' => 'PATCH /household/chores/{chore}/snooze', 'in' => 200, 'pointer' => '/data/attributes/planned_for', 'schema' => $plannedFor, 'reason' => $plannedForReason],
];

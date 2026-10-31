<?php

declare(strict_types=1);

/*
 * Spec operations the SDK deliberately does not wrap, each with its reason.
 * CoverageTest fails on any other operation no SDK call reaches, so after
 * `composer sync-openapi` it lists exactly the platform's new endpoints.
 *
 *   'METHOD /template' => 'why the SDK leaves it out',
 */

return [
    'GET /oauth/dashboard' => 'Passport-only (auth:api + oauth.tenant in src/Auth/OAuth/Providers/OAuthModuleProvider.php) for third-party OAuth clients; the SDK authenticates with Sanctum and uses GET /dashboard.',

    // Route::apiResource registers PUT and PATCH on the same update() action;
    // the SDK sends PATCH.
    'PUT /car-journeys/{id}' => 'Duplicate of PATCH: apiResource in src/CarJourneys/Routes/api.php routes both to CarJourneyController@update.',
    'PUT /household/chores/{chore}' => 'Duplicate of PATCH: apiResource in src/Household/Routes/api.php routes both to ChoreController@update.',
    'PUT /household/shopping-list/{shoppingListItem}' => 'Duplicate of PATCH: apiResource in src/Household/Routes/api.php routes both to ShoppingListItemController@update.',
    'PUT /leave/adjustments/{id}' => 'Duplicate of PATCH: apiResource in src/Leave/Routes/api.php routes both to ManualAdjustmentController@update.',
    'PUT /leave/requests/{id}' => 'Duplicate of PATCH: apiResource in src/Leave/Routes/api.php routes both to LeaveRequestController@update.',
    'PUT /payroll/pay-enhancements/{id}' => 'Duplicate of PATCH: apiResource in src/Payroll/Routes/api.php routes both to PayEnhancementController@update.',

    'PATCH /payroll/automatic-pay-enhancements' => 'Duplicate of PUT: src/Payroll/Routes/api.php routes both to AutomaticPayEnhancementController@update; the SDK sends PUT.',
];

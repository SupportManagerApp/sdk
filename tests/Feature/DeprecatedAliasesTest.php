<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Resources\Household\ChoresResource;
use SupportManager\Sdk\Resources\Household\ShoppingListItemsResource;
use SupportManager\Sdk\Resources\Rota\ShiftApplicationsResource;
use SupportManager\Sdk\Resources\Rota\ShiftRequestsResource;

/**
 * Run a call and return the deprecation messages it raised.
 *
 * @return list<string>
 */
function deprecationsRaisedBy(Closure $call): array
{
    $messages = [];

    set_error_handler(function (int $level, string $message) use (&$messages): bool {
        $messages[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        $call();
    } finally {
        restore_error_handler();
    }

    return $messages;
}

dataset('renamed methods', [
    'shiftApplications()->cancel()' => [ShiftApplicationsResource::class, 'cancel', 'rota/shift-applications/12'],
    'shiftApplications()->remove()' => [ShiftApplicationsResource::class, 'remove', 'rota/shift-applications/12'],
    'shiftRequests()->cancel()' => [ShiftRequestsResource::class, 'cancel', 'rota/shift-requests/12'],
    'chores()->delete()' => [ChoresResource::class, 'delete', 'household/chores/12'],
    'shoppingList()->delete()' => [ShoppingListItemsResource::class, 'delete', 'household/shopping-list/12'],
]);

it('keeps the old name working, with a deprecation pointing at destroy()', function (string $resource, string $method, string $path) {
    Http::fake(["api.test/api/v1/{$path}" => Http::response(['message' => 'Deleted.'])]);
    bootTenantSession();

    $deprecations = deprecationsRaisedBy(fn () => app($resource)->{$method}(12));

    expect($deprecations)->toBe(["{$resource}::{$method}() is deprecated; call destroy() instead."]);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), $path));
})->with('renamed methods');

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Http\ResourceQuery;
use SupportManager\Sdk\Resources\Household\ChoresResource;
use SupportManager\Sdk\Resources\Household\ShoppingListItemsResource;
use SupportManager\Sdk\Resources\Profile\ProfileResource;
use SupportManager\Sdk\Resources\Rota\ShiftApplicationsResource;
use SupportManager\Sdk\Resources\Rota\ShiftRequestsResource;
use SupportManager\Sdk\Resources\Signing\SignatureRequestsResource;

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

it('keeps file() working, with a deprecation pointing at downloadOriginal()', function () {
    Http::fake(['api.test/api/v1/signing/requests/12/file' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf'])]);
    bootTenantSession();

    $download = null;
    $deprecations = deprecationsRaisedBy(function () use (&$download) {
        $download = app(SignatureRequestsResource::class)->file(12);
    });

    expect($deprecations)->toBe([SignatureRequestsResource::class.'::file() is deprecated; call downloadOriginal() instead.'])
        ->and($download?->body())->toBe('%PDF-1.4');
});

it('keeps employmentConfirmationLetter() working, with a deprecation pointing at downloadEmploymentConfirmationLetter()', function () {
    Http::fake(['api.test/api/v1/user/employment/confirmation-letter*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf'])]);
    bootTenantSession();

    $download = null;
    $deprecations = deprecationsRaisedBy(function () use (&$download) {
        $download = app(ProfileResource::class)->employmentConfirmationLetter('ABC Mortgages Ltd', includeGrossPay: true);
    });

    expect($deprecations)->toBe([ProfileResource::class.'::employmentConfirmationLetter() is deprecated; call downloadEmploymentConfirmationLetter() instead.'])
        ->and($download?->body())->toBe('%PDF-1.4');
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'recipient_name=ABC Mortgages Ltd')
        && str_contains($request->url(), 'include_gross_pay=1'));
});

it('keeps include() callable as a no-op that sends nothing, with a deprecation', function () {
    fakeApi(['documents/documents*' => Http::response([
        'data' => [],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
        'links' => ['next' => null],
    ])]);

    $base = sdk()->documents()->query()->filter('name', 'contract');
    $query = null;

    $deprecations = deprecationsRaisedBy(function () use ($base, &$query) {
        $query = $base->include('user', 'document_folder');
    });

    expect($deprecations)->toBe([ResourceQuery::class.'::include() is deprecated and does nothing: no SupportManager endpoint supports includes.'])
        ->and($query)->toBeInstanceOf(ResourceQuery::class)->not->toBe($base)
        ->and($query->params())->toBe(['filter' => ['name' => 'contract']])
        ->and($query->get()->items())->toBe([]);

    // The platform ignored `include` and the spec does not document it, so it is not sent.
    Http::assertSent(fn ($request) => ! str_contains(urldecode($request->url()), 'include'));
});

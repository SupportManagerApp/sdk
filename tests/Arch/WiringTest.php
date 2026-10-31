<?php

declare(strict_types=1);

use SupportManager\Sdk\Facades\SupportManager as SupportManagerFacade;
use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\SupportManager;
use SupportManager\Sdk\Tests\Arch\Support\Codebase;

/*
 * Every resource is reachable from host apps: one typed accessor on
 * SupportManager and a matching @method tag on the facade, with the same name
 * and return type. Replaces the check reviewers used to do by hand.
 */

/**
 * The accessors on SupportManager that return a concrete resource.
 *
 * @return array<string, class-string> Accessor name => resource class.
 */
function resourceAccessors(): array
{
    $accessors = [];

    foreach (Codebase::ownPublicMethods(SupportManager::class) as $method) {
        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Resource::class)) {
            $accessors[$method->getName()] = $type->getName();
        }
    }

    return $accessors;
}

/**
 * The facade's `@method static <Resource> name()` tags, with short names
 * resolved through the facade's imports.
 *
 * @return array<string, string> Method name => resource class.
 */
function facadeResourceMethods(): array
{
    $file = (string) (new ReflectionClass(SupportManagerFacade::class))->getFileName();
    $source = (string) file_get_contents($file);

    preg_match_all('/^use ([\w\\\\]+?)(?: as (\w+))?;$/m', $source, $imports, PREG_SET_ORDER);
    $aliases = [];

    foreach ($imports as $import) {
        $aliases[$import[2] ?? mb_substr((string) mb_strrchr('\\'.$import[1], '\\'), 1)] = $import[1];
    }

    preg_match_all('/@method\s+static\s+(\S+)\s+(\w+)\(/', (string) (new ReflectionClass(SupportManagerFacade::class))->getDocComment(), $tags, PREG_SET_ORDER);
    $methods = [];

    foreach ($tags as [, $type, $name]) {
        $class = str_starts_with($type, '\\') ? mb_substr($type, 1) : ($aliases[$type] ?? $type);

        if (is_subclass_of($class, Resource::class)) {
            $methods[$name] = $class;
        }
    }

    return $methods;
}

it('gives every resource exactly one accessor on SupportManager', function (string $resource) {
    $accessors = array_keys(resourceAccessors(), $resource, true);

    expect($accessors)->toHaveCount(1, $accessors === []
        ? "{$resource} has no accessor on SupportManager; add one that returns \$this->resource({$resource}::class)."
        : "{$resource} has more than one accessor on SupportManager: ".implode(', ', $accessors).'.');
})->with(fn () => Codebase::resources());

it('only has accessors for resources that exist under src/Resources', function () {
    expect(array_diff(resourceAccessors(), Codebase::resources()))->toBe([]);
});

it('mirrors every accessor as a facade @method with the same name and return type', function () {
    $accessors = resourceAccessors();
    $tags = facadeResourceMethods();

    expect(array_diff_assoc($accessors, $tags))->toBe([], 'These accessors have no matching @method on the facade.')
        ->and(array_diff_assoc($tags, $accessors))->toBe([], 'These facade @method tags have no matching accessor on SupportManager.');
});

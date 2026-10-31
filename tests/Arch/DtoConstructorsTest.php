<?php

declare(strict_types=1);

use SupportManager\Sdk\Tests\Arch\Support\Codebase;

/*
 * Host apps build DTOs positionally in their own tests, so between releases a
 * DTO constructor may only grow at the end, and only with defaulted
 * parameters. This compares every DTO constructor in src/Data against the
 * snapshot in tests/Arch/snapshots/dto-constructors.json, and fails when a
 * parameter is removed, renamed, moved, or loses its default, or when one is
 * added without a default.
 *
 * After a change that keeps the rule (or a deliberate break, which bumps the
 * minor version), refresh the snapshot and commit it with the change:
 *
 *     UPDATE_DTO_CONSTRUCTORS=1 vendor/bin/pest tests/Arch/DtoConstructorsTest.php
 */

const DTO_CONSTRUCTORS_SNAPSHOT = __DIR__.'/snapshots/dto-constructors.json';

/**
 * Each DTO's constructor parameters, as `type $name` with ` = default` when
 * the parameter has one.
 *
 * @return array<class-string, list<string>>
 */
function dtoConstructors(): array
{
    $constructors = [];

    foreach (Codebase::classes('Data') as $class) {
        if (str_starts_with($class, 'SupportManager\\Sdk\\Data\\Hydration\\')) {
            continue;
        }

        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            continue;
        }

        $constructors[$class] = array_map(static function (ReflectionParameter $parameter): string {
            $signature = mb_trim("{$parameter->getType()} \${$parameter->getName()}");

            if (! $parameter->isDefaultValueAvailable()) {
                return $signature;
            }

            return $signature.' = '.($parameter->getDefaultValueConstantName() ?? json_encode($parameter->getDefaultValue()));
        }, $constructor->getParameters());
    }

    ksort($constructors);

    return $constructors;
}

function dtoParameterName(string $signature): string
{
    preg_match('/\$(\w+)/', $signature, $match);

    return $match[1];
}

function dtoParameterHasDefault(string $signature): bool
{
    return str_contains($signature, ' = ');
}

it('only grows DTO constructors at the end, with defaults', function () {
    $current = dtoConstructors();

    if (getenv('UPDATE_DTO_CONSTRUCTORS')) {
        file_put_contents(DTO_CONSTRUCTORS_SNAPSHOT, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /** @var array<class-string, list<string>> $snapshot */
    $snapshot = json_decode((string) file_get_contents(DTO_CONSTRUCTORS_SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);
    $problems = [];

    foreach ($snapshot as $dto => $released) {
        if (! isset($current[$dto])) {
            $problems[] = "{$dto} is gone (or lost its constructor).";

            continue;
        }

        foreach ($released as $position => $signature) {
            $now = $current[$dto][$position] ?? null;

            if ($now === null || dtoParameterName($now) !== dtoParameterName($signature)) {
                $problems[] = "{$dto}: parameter ".($position + 1).' was $'.dtoParameterName($signature).', now '.($now === null ? 'removed' : '$'.dtoParameterName($now)).'. Append new parameters instead.';

                break;
            }

            if (dtoParameterHasDefault($signature) && ! dtoParameterHasDefault($now)) {
                $problems[] = "{$dto}: \$".dtoParameterName($now).' lost its default.';
            }
        }

        foreach (array_slice($current[$dto], count($released)) as $added) {
            if (! dtoParameterHasDefault($added)) {
                $problems[] = "{$dto}: the new parameter \$".dtoParameterName($added).' needs a default.';
            }
        }
    }

    foreach (array_keys(array_diff_key($current, $snapshot)) as $dto) {
        $problems[] = "{$dto} is new; record it with UPDATE_DTO_CONSTRUCTORS=1.";
    }

    $outOfDate = $problems === [] && $current !== $snapshot;

    expect($problems)->toBe([])
        ->and($outOfDate)->toBeFalse('The snapshot is behind src/Data; refresh it with UPDATE_DTO_CONSTRUCTORS=1.');
});

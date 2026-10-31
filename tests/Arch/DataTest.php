<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Data\Leave\ManualAdjustmentSummary;
use SupportManager\Sdk\Data\Rota\NestedResource;
use SupportManager\Sdk\Tests\Arch\Support\Codebase;

/*
 * Every DTO in src/Data (the Hydration machinery aside) is a final readonly
 * class with `public static function fromArray(array $data): self` that
 * returns `Hydrator::hydrate(self::class, $data)`, which is what the Resource
 * readers call. Each allowlist below needs a reason; keep them short.
 */

/**
 * Classes in src/Data that are not DTOs at all.
 *
 * @var array<class-string, string>
 */
const NOT_DTOS = [
    NestedResource::class => 'A static helper that flattens nested JSON:API objects for the Rota DTOs; it is never hydrated.',
];

/**
 * DTOs whose fromArray() takes more than the one `$data` array.
 *
 * @var array<class-string, string>
 */
const WIDER_FROM_ARRAY = [
    AuthTokens::class => 'Login responses carry the trusted-device token beside `data`, so SupportManagerClient passes it as an optional second argument.',
    ManualAdjustmentSummary::class => 'Built from a JSON:API page, the adjustment list plus the response `meta`, rather than from one `data` object.',
];

/**
 * DTOs whose fromArray() builds the object by hand instead of through the
 * {@see Hydrator}.
 *
 * @var array<class-string, string>
 */
const HAND_HYDRATED_DTOS = [];

/**
 * @return list<class-string>
 */
function sdkDtos(): array
{
    return array_values(array_filter(
        Codebase::classes('Data'),
        static fn (string $class): bool => ! str_starts_with($class, 'SupportManager\\Sdk\\Data\\Hydration\\') && ! isset(NOT_DTOS[$class]),
    ));
}

arch('DTOs are final readonly classes')
    ->expect('SupportManager\Sdk\Data')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly()
    ->ignoring('SupportManager\Sdk\Data\Hydration');

it('gives each DTO a public static fromArray(array $data): self', function (string $dto) {
    $reflection = new ReflectionClass($dto);

    expect($reflection->hasMethod('fromArray'))->toBeTrue("{$dto} has no fromArray().");

    $method = $reflection->getMethod('fromArray');
    $parameters = $method->getParameters();

    expect($method->isPublic() && $method->isStatic())->toBeTrue("{$dto}::fromArray() should be public static.")
        ->and(in_array((string) $method->getReturnType(), ['self', $dto], true))->toBeTrue("{$dto}::fromArray() should return self.");

    if (isset(WIDER_FROM_ARRAY[$dto])) {
        expect(count($parameters))->toBeGreaterThan(1, "{$dto}::fromArray() takes one parameter now; drop it from WIDER_FROM_ARRAY.");

        return;
    }

    expect(count($parameters))->toBe(1, "{$dto}::fromArray() should take only `array \$data`, or be listed in WIDER_FROM_ARRAY with a reason.")
        ->and($parameters[0]->getName())->toBe('data')
        ->and((string) $parameters[0]->getType())->toBe('array');
})->with(fn () => sdkDtos());

it('hydrates each DTO through the Hydrator', function (string $dto) {
    $hydrated = str_contains(Codebase::source(new ReflectionMethod($dto, 'fromArray')), 'Hydrator::hydrate(self::class, ');

    expect($hydrated)->toBe(! isset(HAND_HYDRATED_DTOS[$dto]), isset(HAND_HYDRATED_DTOS[$dto])
        ? "{$dto} now uses the Hydrator; drop it from HAND_HYDRATED_DTOS."
        : "{$dto}::fromArray() should be `return Hydrator::hydrate(self::class, \$data);`, or be listed in HAND_HYDRATED_DTOS with a reason.");
})->with(fn () => sdkDtos());

it('backs every enum with a string or int', function (string $enum) {
    expect((new ReflectionEnum($enum))->isBacked())->toBeTrue("{$enum} should be a backed enum.");
})->with(fn () => array_values(array_filter(Codebase::classes(), static fn (string $class): bool => enum_exists($class))));

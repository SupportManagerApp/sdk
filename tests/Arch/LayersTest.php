<?php

declare(strict_types=1);

use SupportManager\Sdk\SupportManager;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Tests\Arch\Support\Codebase;

arch('src/ never depends on tests/')
    ->expect(Codebase::classes())
    ->not->toUse('SupportManager\Sdk\Tests');

/*
 * The fake ships in src/Testing so host apps can use it, but runtime code
 * reaches it only through the SupportManager::fake() entry point.
 */
arch('runtime src/ code uses src/Testing only through SupportManager::fake()')
    ->expect(array_values(array_filter(
        Codebase::classes(),
        static fn (string $class): bool => ! str_starts_with($class, 'SupportManager\\Sdk\\Testing\\') && $class !== SupportManager::class,
    )))
    ->not->toUse('SupportManager\Sdk\Testing');

arch('fake factories are final and extend Factory')
    ->expect('SupportManager\Sdk\Testing\Factories')
    ->classes()
    ->toBeFinal()
    ->toExtend(Factory::class)
    ->toHaveSuffix('Factory');

it('declares strict types in every PHP file', function (string $file) {
    expect(file_get_contents(Codebase::root().'/'.$file))
        ->toMatch('/\A<\?php\s+(?:\/\*.*?\*\/\s*)?declare\(strict_types=1\);/s', "{$file} should start with declare(strict_types=1).");
})->with(fn () => [...Codebase::files('src'), ...Codebase::files('tests')]);

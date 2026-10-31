<?php

declare(strict_types=1);

use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\Tests\Arch\Support\Codebase;

arch('resources are final, extend Resource and are named *Resource')
    ->expect(Codebase::resources())
    ->toBeFinal()
    ->toExtend(Resource::class)
    ->toHaveSuffix('Resource');

it('keeps each resource in src/Resources/<Module>/', function (string $resource) {
    expect(mb_substr_count($resource, '\\'))->toBe(4, "{$resource} should live one level down, in src/Resources/<Module>/.");
})->with(fn () => Codebase::resources());

it('declares the DTO type with an @extends Resource<…> tag', function (string $resource) {
    // Pint's phpdoc_types fixer once lowercased this to `resource<…>`, the
    // pseudo-type, which IDEs and other analysers read as "no generic".
    expect((string) (new ReflectionClass($resource))->getDocComment())
        ->toMatch('/@extends Resource<[\w\\\\]+>/', "{$resource} should declare `@extends Resource<TheDto>`.");
})->with(fn () => Codebase::resources());

/*
 * A public method that issues `DELETE {path}/{id}`, the record itself, is
 * named destroy(), and a method named destroy() does exactly that. Deprecated
 * aliases are exempt: they forward to destroy() under their old names.
 */
it('names the DELETE of one record destroy()', function (string $resource) {
    $instance = (new ReflectionClass($resource))->newInstanceWithoutConstructor();
    $path = (new ReflectionMethod($resource, 'path'))->invoke($instance);
    $deletesTheRecord = '/(?:\$this->|parent::)delete\(\s*"(?:\{\$this->path\(\)\}|'.preg_quote($path, '/').')\/\{\$id\}"\s*[,)]/';

    $misnamed = [];

    foreach (Codebase::ownPublicMethods($resource) as $method) {
        if (Codebase::isDeprecated($method)) {
            continue;
        }

        $deletesOneRecord = preg_match($deletesTheRecord, Codebase::source($method)) === 1;

        if ($deletesOneRecord !== ($method->getName() === 'destroy')) {
            $misnamed[] = $deletesOneRecord
                ? "{$method->getName()}() issues DELETE {$path}/{id}, so name it destroy()"
                : "destroy() should issue DELETE {$path}/{id}";
        }
    }

    expect($misnamed)->toBe([]);
})->with(fn () => Codebase::resources());

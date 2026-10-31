<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Hydration\Attributes\CastWith;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Attributes\Meta;
use SupportManager\Sdk\Data\Hydration\HydrationException;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Attributed;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Child;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Colour;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Enums;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Nested;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\PlainChild;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Priority;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Scalars;
use SupportManager\Sdk\Tests\Unit\Hydration\Fixtures\Suit;

describe('scalars', function () {
    it('gives each type its zero, or null when nullable, for missing keys', function () {
        $dto = Hydrator::hydrate(Scalars::class, []);

        expect($dto->id)->toBe('')
            ->and($dto->name)->toBeNull()
            ->and($dto->count)->toBe(0)
            ->and($dto->teamId)->toBeNull()
            ->and($dto->amount)->toBe(0.0)
            ->and($dto->rate)->toBeNull()
            ->and($dto->active)->toBeFalse()
            ->and($dto->flag)->toBeNull()
            ->and($dto->data)->toBe([])
            ->and($dto->extra)->toBeNull()
            ->and($dto->anything)->toBeNull()
            ->and($dto->week1Month1)->toBeFalse();
    });

    it('treats null exactly like a missing key', function () {
        $keys = ['id', 'name', 'count', 'team_id', 'amount', 'rate', 'active', 'flag', 'data', 'extra', 'anything'];

        expect(Hydrator::hydrate(Scalars::class, array_fill_keys($keys, null)))
            ->toEqual(Hydrator::hydrate(Scalars::class, []));
    });

    it('casts present values by the declared type', function () {
        $dto = Hydrator::hydrate(Scalars::class, [
            'id' => 42,
            'name' => 7,
            'count' => '12',
            'team_id' => '5',
            'amount' => '1.5',
            'rate' => 2,
            'active' => 1,
            'flag' => 0,
            'data' => ['a' => 1],
            'extra' => [1, 2],
            'anything' => 3.5,
            'week1_month1' => 'yes',
        ]);

        expect($dto->id)->toBe('42')
            ->and($dto->name)->toBe('7')
            ->and($dto->count)->toBe(12)
            ->and($dto->teamId)->toBe(5)
            ->and($dto->amount)->toBe(1.5)
            ->and($dto->rate)->toBe(2.0)
            ->and($dto->active)->toBeTrue()
            ->and($dto->flag)->toBeFalse()
            ->and($dto->data)->toBe(['a' => 1])
            ->and($dto->extra)->toBe([1, 2])
            ->and($dto->anything)->toBe(3.5)
            ->and($dto->week1Month1)->toBeTrue();
    });

    it('keeps falsy present values rather than treating them as missing', function () {
        $dto = Hydrator::hydrate(Scalars::class, ['name' => '', 'team_id' => 0, 'rate' => 0, 'flag' => false]);

        expect($dto->name)->toBe('')
            ->and($dto->teamId)->toBe(0)
            ->and($dto->rate)->toBe(0.0)
            ->and($dto->flag)->toBeFalse();
    });

    it('reads snake_case keys only', function () {
        expect(Hydrator::hydrate(Scalars::class, ['teamId' => 5])->teamId)->toBeNull();
    });

    it('treats a non-array value for an array parameter as missing', function () {
        $dto = Hydrator::hydrate(Scalars::class, ['data' => 'nope', 'extra' => 5]);

        expect($dto->data)->toBe([])
            ->and($dto->extra)->toBeNull();
    });
});

describe('enums', function () {
    it('maps known values and gives null for unknown or missing nullable ones', function () {
        expect(Hydrator::hydrate(Enums::class, ['colour' => 'red'])->colour)->toBe(Colour::Red)
            ->and(Hydrator::hydrate(Enums::class, ['colour' => 'green'])->colour)->toBeNull()
            ->and(Hydrator::hydrate(Enums::class, ['colour' => ''])->colour)->toBeNull()
            ->and(Hydrator::hydrate(Enums::class, [])->colour)->toBeNull();
    });

    it('passes an enum case through unchanged', function () {
        expect(Hydrator::hydrate(Enums::class, ['colour' => Colour::Blue])->colour)->toBe(Colour::Blue);
    });

    it('casts int-backed enums from ints and numeric strings', function () {
        expect(Hydrator::hydrate(Enums::class, ['priority' => 2])->priority)->toBe(Priority::High)
            ->and(Hydrator::hydrate(Enums::class, ['priority' => '1'])->priority)->toBe(Priority::Low)
            ->and(Hydrator::hydrate(Enums::class, ['priority' => 9])->priority)->toBeNull();
    });

    it('uses #[Defaults] — as a case or a backing value — when a non-nullable enum is missing', function () {
        $dto = Hydrator::hydrate(Enums::class, []);

        expect($dto->withCaseDefault)->toBe(Colour::Red)
            ->and($dto->withValueDefault)->toBe(Colour::Blue);
    });

    it('uses the constructor default when a non-nullable enum is missing', function () {
        expect(Hydrator::hydrate(Enums::class, [])->strict)->toBe(Colour::Blue)
            ->and(Hydrator::hydrate(Enums::class, ['strict' => 'red'])->strict)->toBe(Colour::Red);
    });

    it('throws for an unknown value of a non-nullable enum, as from() does', function () {
        Hydrator::hydrate(Enums::class, ['with_case_default' => 'green']);
    })->throws(ValueError::class);

    it('throws for a missing non-nullable enum with no default', function () {
        $class = get_class(new readonly class(Colour::Red)
        {
            public function __construct(public Colour $colour) {}
        });

        Hydrator::hydrate($class, []);
    })->throws(ValueError::class);
});

describe('nested DTOs', function () {
    it('hydrates a nested DTO through its own fromArray()', function () {
        $dto = Hydrator::hydrate(Nested::class, ['child' => ['name' => 'Ann']]);

        expect($dto->child)->toEqual(new Child('Ann', true));
    });

    it('hydrates a nested DTO without fromArray() recursively', function () {
        $dto = Hydrator::hydrate(Nested::class, ['plain' => ['size' => '3', 'label' => 'L']]);

        expect($dto->plain)->toEqual(new PlainChild(3, 'L'));
    });

    it('gives null for a missing or non-array nullable nested DTO', function () {
        expect(Hydrator::hydrate(Nested::class, [])->child)->toBeNull()
            ->and(Hydrator::hydrate(Nested::class, ['child' => 'Ann'])->child)->toBeNull()
            ->and(Hydrator::hydrate(Nested::class, ['plain' => 5])->plain)->toBeNull();
    });

    it('builds a non-nullable nested DTO from an empty array when missing', function () {
        $dto = Hydrator::hydrate(Nested::class, ['required_plain' => 'junk']);

        expect($dto->requiredChild)->toEqual(new Child('', true))
            ->and($dto->requiredPlain)->toEqual(new PlainChild(0, null));
    });
});

describe('#[ListOf]', function () {
    it('hydrates a list of DTOs, dropping non-array elements and re-indexing', function () {
        $dto = Hydrator::hydrate(Nested::class, [
            'children' => ['x' => ['name' => 'a'], 'y' => 'junk', 'z' => null, 'w' => ['name' => 'b']],
            'plain_children' => [['size' => 1], ['size' => '2', 'label' => 'two']],
        ]);

        expect($dto->children)->toEqual([new Child('a', true), new Child('b', true)])
            ->and($dto->plainChildren)->toEqual([new PlainChild(1, null), new PlainChild(2, 'two')]);
    });

    it('gives an empty list when missing or not an array, or null when nullable', function () {
        $dto = Hydrator::hydrate(Nested::class, ['children' => 'nope']);

        expect($dto->children)->toBe([])
            ->and($dto->tags)->toBe([])
            ->and($dto->ids)->toBeNull();
    });

    it('casts scalar elements and drops non-scalar ones', function () {
        $dto = Hydrator::hydrate(Nested::class, [
            'tags' => ['a', 5, null, ['x'], 'b'],
            'ids' => ['1', 2, 3.9, [4]],
            'scores' => ['1.5', 2],
            'switches' => [1, 0, 'yes'],
        ]);

        expect($dto->tags)->toBe(['a', '5', 'b'])
            ->and($dto->ids)->toBe([1, 2, 3])
            ->and($dto->scores)->toBe([1.5, 2.0])
            ->and($dto->switches)->toBe([true, false, true]);
    });

    it('keeps only array elements of a list of arrays', function () {
        expect(Hydrator::hydrate(Nested::class, ['rows' => [['a' => 1], 'b', ['c' => 2]]])->rows)
            ->toBe([['a' => 1], ['c' => 2]]);
    });

    it('keeps only known values of a list of enums', function () {
        $dto = Hydrator::hydrate(Nested::class, [
            'colours' => ['red', 'green', 'blue', null, ['red']],
            'priorities' => [1, '2', 3],
        ]);

        expect($dto->colours)->toBe([Colour::Red, Colour::Blue])
            ->and($dto->priorities)->toBe([Priority::Low, Priority::High]);
    });
});

describe('attributes', function () {
    it('reads dot-paths into nested objects with #[From]', function () {
        $dto = Hydrator::hydrate(Attributed::class, ['user' => ['name' => 'Ann', 'profile' => ['age' => '41']]]);

        expect($dto->userName)->toBe('Ann')
            ->and($dto->userAge)->toBe(41);
    });

    it('gives null when a dot-path segment is missing, null or not an array', function () {
        expect(Hydrator::hydrate(Attributed::class, [])->userName)->toBeNull()
            ->and(Hydrator::hydrate(Attributed::class, ['user' => null])->userName)->toBeNull()
            ->and(Hydrator::hydrate(Attributed::class, ['user' => 'Ann'])->userName)->toBeNull()
            ->and(Hydrator::hydrate(Attributed::class, ['user' => ['profile' => 'old']])->userAge)->toBeNull();
    });

    it('reads a renamed key with #[From] and ignores the default one', function () {
        expect(Hydrator::hydrate(Attributed::class, ['legacy_title' => 'Old', 'title' => 'New'])->title)->toBe('Old')
            ->and(Hydrator::hydrate(Attributed::class, ['title' => 'New'])->title)->toBe('');
    });

    it('falls back through several #[From] paths to the first non-null one', function () {
        expect(Hydrator::hydrate(Attributed::class, ['total' => '3', 'amount' => 9])->total)->toBe(3.0)
            ->and(Hydrator::hydrate(Attributed::class, ['total' => null, 'amount' => '9.5'])->total)->toBe(9.5)
            ->and(Hydrator::hydrate(Attributed::class, [])->total)->toBe(0.0);
    });

    it('reads meta.can flags with #[Can]', function () {
        $granted = Hydrator::hydrate(Attributed::class, ['meta' => ['can' => ['withdraw' => true, 'delete' => '1']]]);
        $denied = Hydrator::hydrate(Attributed::class, ['meta' => ['can' => ['withdraw' => false, 'delete' => 0]]]);
        $absent = Hydrator::hydrate(Attributed::class, ['meta' => ['other' => 1]]);

        expect($granted->canWithdraw)->toBeTrue()->and($granted->canDelete)->toBeTrue()
            ->and($denied->canWithdraw)->toBeFalse()->and($denied->canDelete)->toBeFalse()
            ->and($absent->canWithdraw)->toBeNull()->and($absent->canDelete)->toBeFalse();
    });

    it('reads #[Can] flags from a flattened JSON:API resource object', function () {
        $flat = ResourceObject::fromArray([
            'type' => 'leave-requests',
            'id' => '1',
            'attributes' => ['legacy_title' => 'Holiday'],
            'meta' => ['can' => ['withdraw' => true], 'reason' => 'Locked'],
        ])->flat();

        $dto = Hydrator::hydrate(Attributed::class, $flat);

        expect($dto->canWithdraw)->toBeTrue()
            ->and($dto->reason)->toBe('Locked')
            ->and($dto->title)->toBe('Holiday');
    });

    it('reads other meta keys with #[Meta]', function () {
        expect(Hydrator::hydrate(Attributed::class, ['meta' => ['reason' => 'Full']])->reason)->toBe('Full')
            ->and(Hydrator::hydrate(Attributed::class, ['reason' => 'Full'])->reason)->toBeNull();
    });

    it('uses #[Defaults] for a missing or null key, cast like input', function () {
        expect(Hydrator::hydrate(Attributed::class, [])->width)->toBe('full')
            ->and(Hydrator::hydrate(Attributed::class, ['width' => null])->width)->toBe('full')
            ->and(Hydrator::hydrate(Attributed::class, ['width' => 'half'])->width)->toBe('half')
            ->and(Hydrator::hydrate(Attributed::class, [])->page)->toBe(1)
            ->and(Hydrator::hydrate(Attributed::class, ['page' => '0'])->page)->toBe(0);
    });

    it('honours constructor defaults for a missing or null key', function () {
        expect(Hydrator::hydrate(Attributed::class, [])->mode)->toBe('auto')
            ->and(Hydrator::hydrate(Attributed::class, ['mode' => 'manual'])->mode)->toBe('manual')
            ->and(Hydrator::hydrate(Attributed::class, ['limit' => null])->limit)->toBe(25)
            ->and(Hydrator::hydrate(Attributed::class, ['limit' => '5'])->limit)->toBe(5);
    });

    it('hands the raw value and the whole payload to a #[CastWith] caster', function () {
        expect(Hydrator::hydrate(Attributed::class, ['county' => ''])->county)->toBeNull()
            ->and(Hydrator::hydrate(Attributed::class, [])->county)->toBeNull()
            ->and(Hydrator::hydrate(Attributed::class, ['county' => 'Kent'])->county)->toBe('Kent')
            ->and(Hydrator::hydrate(Attributed::class, ['shout' => 'hi', 'prefix' => '> '])->shout)->toBe('> HI');
    });
});

describe('overrides', function () {
    it('uses an override as-is instead of hydrating the parameter', function () {
        $dto = Hydrator::hydrate(Scalars::class, ['id' => 1, 'count' => 5], ['count' => 99, 'anything' => [1]]);

        expect($dto->id)->toBe('1')
            ->and($dto->count)->toBe(99)
            ->and($dto->anything)->toBe([1]);
    });

    it('uses a null override rather than the hydrated value', function () {
        expect(Hydrator::hydrate(Scalars::class, ['name' => 'Ann'], ['name' => null])->name)->toBeNull();
    });

    it('rejects an override for a parameter that does not exist', function () {
        Hydrator::hydrate(Scalars::class, [], ['nope' => 1]);
    })->throws(HydrationException::class, 'has no constructor parameter $nope');
});

describe('invalid DTOs', function () {
    it('rejects a parameter it cannot hydrate', function (object $dto, string $message) {
        expect(fn () => Hydrator::hydrate($dto::class, []))->toThrow(HydrationException::class, $message);
    })->with([
        'untyped' => [new class(1)
        {
            public function __construct(public $value) {}
        }, 'declare a single'],
        'union' => [new readonly class(1)
        {
            public function __construct(public int|string $value) {}
        }, 'declare a single'],
        'pure enum' => [new readonly class(Suit::Hearts)
        {
            public function __construct(public Suit $suit) {}
        }, 'is not backed'],
        'unknown class' => [new readonly class(new stdClass)
        {
            public function __construct(public object $value) {}
        }, 'type object is not supported'],
        '#[ListOf] on a non-array' => [new readonly class('a')
        {
            public function __construct(#[ListOf('string')] public string $value) {}
        }, '#[ListOf] needs an array'],
        '#[ListOf] of an unsupported type' => [new readonly class([])
        {
            /** @param  list<mixed>  $value */
            public function __construct(#[ListOf('callable')] public array $value) {}
        }, 'type callable is not supported'],
        'two sources' => [new readonly class('a')
        {
            public function __construct(#[From('a')] #[Meta('b')] public string $value) {}
        }, 'use only one of'],
        'a caster that is not a Caster' => [new readonly class('a')
        {
            public function __construct(#[CastWith(stdClass::class)] public string $value) {}
        }, 'does not implement'],
    ]);
});

it('caches the reflected plan per class', function () {
    $plans = new ReflectionProperty(Hydrator::class, 'plans');

    Hydrator::hydrate(PlainChild::class, ['size' => 1]);
    $first = $plans->getValue()[PlainChild::class];

    Hydrator::hydrate(PlainChild::class, ['size' => 2]);

    expect($plans->getValue()[PlainChild::class])->toBe($first);
});

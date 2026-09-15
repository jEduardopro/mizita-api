<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

function expectedMorphAlias(string $class): string
{
    return Str::snake(Str::replaceLast('Model', '', class_basename($class)));
}

it('registers a morph map, so the alias rule never passes by vacuity', function () {
    expect(Relation::morphMap())->not->toBeEmpty();
});

it('names every morph map alias after the model it points at', function () {
    $mismatched = [];

    foreach (Relation::morphMap() as $alias => $class) {
        $expected = expectedMorphAlias($class);

        if ($alias !== $expected) {
            $mismatched[$alias] = $expected;
        }
    }

    expect($mismatched)->toBe([]);
});

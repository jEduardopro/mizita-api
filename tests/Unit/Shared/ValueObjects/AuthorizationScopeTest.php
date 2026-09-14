<?php

declare(strict_types=1);

use App\Shared\ValueObjects\AuthorizationScope;

/*
| A backed enum whose values are a storage contract: the scope column on both
| permissions and roles carries a check constraint written from these strings, so
| renaming one here without a migration fails at the database rather than here.
| The values are pinned as literals for that reason - reading them back off the
| enum would assert nothing.
*/

it('spells the two planes exactly as the check constraint does', function () {
    expect(AuthorizationScope::Platform->value)->toBe('platform')
        ->and(AuthorizationScope::Business->value)->toBe('business');
});

it('has exactly two planes, so a third cannot arrive without a migration', function () {
    expect(AuthorizationScope::cases())->toBe([
        AuthorizationScope::Platform,
        AuthorizationScope::Business,
    ]);
});

it('refuses a scope the column would reject', function (string $value) {
    expect(AuthorizationScope::tryFrom($value))->toBeNull();
})->with([
    'unknown' => 'tenant',
    'the case name rather than the value' => 'Business',
    'empty' => '',
    'padded' => ' business ',
    'uppercase' => 'BUSINESS',
]);

<?php

declare(strict_types=1);

use App\Shared\ValueObjects\AuthorizationScope;

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

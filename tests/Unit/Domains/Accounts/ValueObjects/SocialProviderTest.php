<?php

declare(strict_types=1);

use App\Domains\Accounts\ValueObjects\SocialProvider;

it('backs google with the string that is stored and sent on the wire', function () {
    // The backing value is a persisted contract: changing it orphans every
    // social_identities row already written.
    expect(SocialProvider::Google->value)->toBe('google');
});

it('resolves a stored value back to its case', function () {
    expect(SocialProvider::from('google'))->toBe(SocialProvider::Google);
});

it('refuses a value it does not know', function (string $value) {
    expect(SocialProvider::tryFrom($value))->toBeNull();
})->with([
    'unknown provider' => 'facebook',
    'wrong case' => 'Google',
    'padded' => ' google ',
    'empty' => '',
]);

it('exposes exactly the providers the platform supports', function () {
    expect(array_column(SocialProvider::cases(), 'value'))->toBe(['google']);
});

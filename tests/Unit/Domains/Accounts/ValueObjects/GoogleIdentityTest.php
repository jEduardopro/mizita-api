<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;

it('carries the identity Google asserted', function () {
    $identity = new GoogleIdentity(
        sub: '104729183746501928374',
        email: 'ada@example.com',
        emailVerified: true,
        name: 'Ada Lovelace',
        avatarUrl: 'https://lh3.googleusercontent.com/a/ada',
    );

    expect($identity->sub)->toBe('104729183746501928374')
        ->and($identity->email)->toBe('ada@example.com')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->name)->toBe('Ada Lovelace')
        ->and($identity->avatarUrl)->toBe('https://lh3.googleusercontent.com/a/ada');
});

it('defaults the avatar url to null when Google supplies no picture', function () {
    $identity = new GoogleIdentity('104729183746501928374', 'ada@example.com', true, 'Ada Lovelace');

    expect($identity->avatarUrl)->toBeNull();
});

it('keeps an unverified flag as it was given', function () {
    // Downgrading this to true anywhere would defeat the takeover guard, so the
    // value object stores what Google said and nothing else.
    $identity = new GoogleIdentity('104729183746501928374', 'ada@example.com', false, 'Ada Lovelace');

    expect($identity->emailVerified)->toBeFalse();
});

it('trims the subject', function () {
    $identity = new GoogleIdentity("  104729183746501928374\n", 'ada@example.com', true, 'Ada');

    expect($identity->sub)->toBe('104729183746501928374');
});

it('rejects a blank subject, which would collide with every other blank subject', function (string $sub) {
    expect(fn () => new GoogleIdentity($sub, 'ada@example.com', true, 'Ada'))
        ->toThrow(InvalidGoogleIdToken::class, 'The Google ID token carries no subject claim.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'tab' => "\t",
    'newline' => "\n",
]);

it('normalizes the email to lowercase', function () {
    $identity = new GoogleIdentity('104729183746501928374', '  Ada@Example.COM  ', true, 'Ada');

    expect($identity->email)->toBe('ada@example.com');
});

it('rejects a blank email', function (string $email) {
    expect(fn () => new GoogleIdentity('104729183746501928374', $email, true, 'Ada'))
        ->toThrow(InvalidAccountEmail::class, 'An account email cannot be empty.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'tab' => "\t",
]);

it('checks the subject before the email, so a tokenless credential is named as such', function () {
    // Both claims are missing here. The subject is the one that decides which
    // account a caller reaches, so it is the failure worth reporting.
    expect(fn () => new GoogleIdentity('', '', true, 'Ada'))
        ->toThrow(InvalidGoogleIdToken::class);
});

it('is immutable once created', function () {
    $identity = new GoogleIdentity('104729183746501928374', 'ada@example.com', true, 'Ada');

    expect(fn () => $identity->email = 'someone-else@example.com')->toThrow(Error::class);
});

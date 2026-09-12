<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;

/*
| This DTO has a file of its own because it carries a rule rather than just
| fields: its constructor is sealed so a GoogleIdentity is the only way in.
| That is what makes "the subject is present" a structural guarantee for every
| caller of the use case instead of a check somebody has to remember.
*/

it('maps every field across from the verified identity', function () {
    $input = AuthenticateWithGoogleInput::fromGoogleIdentity(new GoogleIdentity(
        sub: '104729183746501928374',
        email: 'ada@example.com',
        emailVerified: true,
        name: 'Ada Lovelace',
        avatarUrl: 'https://lh3.googleusercontent.com/a/ada',
    ));

    expect($input->googleUserId)->toBe('104729183746501928374')
        ->and($input->email)->toBe('ada@example.com')
        ->and($input->emailVerified)->toBeTrue()
        ->and($input->name)->toBe('Ada Lovelace');
});

it('carries the normalized subject and email, not the raw ones', function () {
    $input = AuthenticateWithGoogleInput::fromGoogleIdentity(
        new GoogleIdentity('  104729183746501928374  ', '  Ada@Example.COM ', true, 'Ada'),
    );

    expect($input->googleUserId)->toBe('104729183746501928374')
        ->and($input->email)->toBe('ada@example.com');
});

it('carries an unverified flag through untouched, for the guard to act on', function () {
    $input = AuthenticateWithGoogleInput::fromGoogleIdentity(
        new GoogleIdentity('104729183746501928374', 'ada@example.com', false, 'Ada'),
    );

    expect($input->emailVerified)->toBeFalse();
});

it('cannot be built without an identity', function () {
    // The seal is the point: a second constructor would be a second set of
    // checks to keep in sync, and the one that got forgotten would be the one
    // that let an empty subject through.
    expect((new ReflectionClass(AuthenticateWithGoogleInput::class))->getConstructor()?->isPrivate())
        ->toBeTrue();
});

it('is unreachable for a blank subject, because the identity refuses first', function (string $sub) {
    expect(fn () => AuthenticateWithGoogleInput::fromGoogleIdentity(
        new GoogleIdentity($sub, 'ada@example.com', true, 'Ada'),
    ))->toThrow(InvalidGoogleIdToken::class, 'The Google ID token carries no subject claim.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'tab' => "\t",
]);

it('is immutable once built', function () {
    $input = AuthenticateWithGoogleInput::fromGoogleIdentity(
        new GoogleIdentity('104729183746501928374', 'ada@example.com', true, 'Ada'),
    );

    expect(fn () => $input->email = 'someone-else@example.com')->toThrow(Error::class);
});

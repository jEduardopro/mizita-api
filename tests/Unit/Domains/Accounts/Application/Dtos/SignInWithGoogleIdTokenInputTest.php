<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Shared\Contracts\DomainFailure;

it('resolves the credential the client posted', function () {
    expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature'])->idToken)
        ->toBe('eyJhbGciOiJSUzI1NiJ9.payload.signature');
});

it('carries the credential unverified and unaltered', function () {
    expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => ' not.a.token '])->idToken)
        ->toBe(' not.a.token ');
});

it('ignores the identity the client claimed alongside it', function () {
    $input = SignInWithGoogleIdTokenInput::fromRequest([
        'id_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature',
        'email' => 'ada@example.com',
        'email_verified' => true,
        'sub' => '104729183746501928374',
    ]);

    expect($input)->toEqual(SignInWithGoogleIdTokenInput::fromRequest([
        'id_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature',
    ]));
});

describe('validating the credential', function () {
    it('accepts any non blank credential, because checking it is the verifier job', function (string $idToken) {
        expect(fn () => (new SignInWithGoogleIdTokenInput($idToken))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'a well formed token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature',
        'a token the verifier will reject' => 'nonsense',
        'a single character' => 'a',
        'padded' => '  a.valid.token  ',
    ]);

    it('refuses a blank credential', function (string $idToken) {
        expect(fn () => (new SignInWithGoogleIdTokenInput($idToken))->validate())
            ->toThrow(InvalidGoogleIdToken::class, 'The supplied credential is not a Google ID token.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'a newline' => "\n",
    ]);

    it('refuses a payload carrying no credential as a domain failure, not a PHP error', function () {
        $thrown = null;

        try {
            SignInWithGoogleIdTokenInput::fromRequest([])->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(InvalidGoogleIdToken::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class);
    });
});

it('reads a credential that is not a string as absent, and refuses it as a credential', function (mixed $idToken) {
    expect(fn () => SignInWithGoogleIdTokenInput::fromRequest(['id_token' => $idToken])->validate())
        ->toThrow(InvalidGoogleIdToken::class, 'The supplied credential is not a Google ID token.');
})->with([
    'an array' => [['eyJhbGciOiJSUzI1NiJ9.payload.signature']],
    'the decoded claims the client should not be sending' => [['sub' => '104729183746501928374']],
    'a number' => 42,
    'a boolean' => true,
    'null' => null,
]);

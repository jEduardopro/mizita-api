<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;

it('resolves the credential the client posted', function () {
    expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature'])->idToken)
        ->toBe('eyJhbGciOiJSUzI1NiJ9.payload.signature');
});

it('carries the credential unverified and unaltered', function () {
    // Verifying it is the use case's first act. A DTO that trimmed, decoded or
    // otherwise touched the token would be the second place its shape is known,
    // and the one that could disagree with the verifier.
    expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => ' not.a.token '])->idToken)
        ->toBe(' not.a.token ');
});

it('ignores the identity the client claimed alongside it', function () {
    // The subject, the email and whether it is verified all come from the token
    // once it has been checked. Reading any of them from the body would let an
    // unauthenticated caller name whoever they liked.
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

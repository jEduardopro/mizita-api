<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;

/**
 * The native entry point: turns a Google ID token into a signed-in account.
 *
 * Verification and authentication stay in separate use cases on purpose. This
 * one owns only the credential rule, so it can be exercised with a fake
 * verifier, while AuthenticateWithGoogle keeps its single responsibility and
 * stays reachable from the browser flow, which never sees an ID token.
 */
final class SignInWithGoogleIdToken
{
    public function __construct(
        private readonly GoogleIdentityVerifier $verifier,
        private readonly AuthenticateWithGoogle $authenticate,
    ) {}

    public function handle(SignInWithGoogleIdTokenInput $input): AuthenticatedAccountData
    {
        $identity = $this->verifier->verify($input->idToken);

        return $this->authenticate->handle(
            AuthenticateWithGoogleInput::fromGoogleIdentity($identity),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;

final class SignInWithGoogleIdToken
{
    public function __construct(
        private readonly GoogleIdentityVerifier $verifier,
        private readonly AuthenticateWithGoogle $authenticate,
    ) {}

    public function handle(SignInWithGoogleIdTokenInput $input): AuthenticatedAccountData
    {
        $input->validate();

        $identity = $this->verifier->verify($input->idToken);

        return $this->authenticate->handle(
            AuthenticateWithGoogleInput::fromGoogleIdentity($identity),
        );
    }
}

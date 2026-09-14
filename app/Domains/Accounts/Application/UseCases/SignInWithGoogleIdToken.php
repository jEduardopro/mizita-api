<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class SignInWithGoogleIdToken
{
    public function __construct(
        private readonly GoogleIdentityVerifier $verifier,
        private readonly AuthenticateWithGoogle $authenticate,
    ) {}

    /**
     * @return UseCaseResponse<AuthenticatedAccountData>
     */
    public function handle(SignInWithGoogleIdTokenInput $input): UseCaseResponse
    {
        try {
            $input->validate();
            $identity = $this->verifier->verify($input->idToken);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return $this->authenticate->handle(
            AuthenticateWithGoogleInput::fromGoogleIdentity($identity),
        );
    }
}

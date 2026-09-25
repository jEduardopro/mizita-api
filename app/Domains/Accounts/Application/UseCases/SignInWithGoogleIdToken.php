<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Domains\Accounts\Contracts\SecondFactorVerifier;
use App\Domains\Accounts\Exceptions\InvalidRecoveryCode;
use App\Domains\Accounts\Exceptions\InvalidTwoFactorCode;
use App\Domains\Accounts\Exceptions\TwoFactorRequired;
use App\Domains\Accounts\ValueObjects\SecondFactorProof;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class SignInWithGoogleIdToken
{
    public function __construct(
        private readonly GoogleIdentityVerifier $verifier,
        private readonly AuthenticateWithGoogle $authenticate,
        private readonly SecondFactorVerifier $secondFactors,
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

        $authentication = $this->authenticate->handle(
            AuthenticateWithGoogleInput::fromGoogleIdentity($identity),
        );

        if ($authentication->failed() || ! $authentication->value()->requiresSecondFactor) {
            return $authentication;
        }

        try {
            $this->passSecondFactor($authentication->value()->id, $input->secondFactorProof());
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return $authentication;
    }

    /**
     * @throws TwoFactorRequired
     * @throws InvalidTwoFactorCode
     * @throws InvalidRecoveryCode
     */
    private function passSecondFactor(string $accountId, ?SecondFactorProof $proof): void
    {
        if ($proof === null) {
            throw TwoFactorRequired::forAccount($accountId);
        }

        $this->secondFactors->verify($accountId, $proof);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\PasskeyData;
use App\Domains\Accounts\Application\Dtos\ShowSignInSecurityInput;
use App\Domains\Accounts\Application\Dtos\SignInSecurityData;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\PasskeyDirectory;
use App\Domains\Accounts\Entities\Account;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ShowSignInSecurity
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PasskeyDirectory $passkeys,
    ) {}

    /**
     * @return UseCaseResponse<SignInSecurityData>
     */
    public function handle(ShowSignInSecurityInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $account = $this->accounts->findById($input->accountId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($this->describe($account));
    }

    private function describe(Account $account): SignInSecurityData
    {
        return new SignInSecurityData(
            hasPassword: $account->holdsPassword(),
            twoFactor: $account->twoFactorStatus(),
            passkeys: array_map(
                PasskeyData::fromRegisteredPasskey(...),
                $this->passkeys->forAccount($account->id),
            ),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AccountReactivationData;
use App\Domains\Accounts\Application\Dtos\ShowAccountReactivationInput;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ShowAccountReactivation
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly OwnedBusinesses $ownedBusinesses,
    ) {}

    /**
     * @return UseCaseResponse<AccountReactivationData>
     */
    public function handle(ShowAccountReactivationInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $account = $this->accounts->findById($input->accountId);

            $account->ensureScheduledForDeletion();

            $reactivation = AccountReactivationData::of(
                $account,
                $this->ownedBusinesses->closedBusinessOf($account->id),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($reactivation);
    }
}

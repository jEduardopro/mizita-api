<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AccountReactivatedData;
use App\Domains\Accounts\Application\Dtos\ReactivateAccountInput;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class ReactivateAccount
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly OwnedBusinesses $ownedBusinesses,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<AccountReactivatedData>
     */
    public function handle(ReactivateAccountInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $reactivated = $this->transactions->run(
                fn (): AccountReactivatedData => $this->reactivate($input->accountId),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($reactivated);
    }

    private function reactivate(string $accountId): AccountReactivatedData
    {
        $account = $this->accounts->findById($accountId);

        $account->reactivate();

        $this->accounts->save($account);

        $closedBusiness = $this->ownedBusinesses->closedBusinessOf($account->id);

        if ($closedBusiness !== null) {
            $this->ownedBusinesses->reopen($closedBusiness->id, $account->id);
        }

        return AccountReactivatedData::of($account);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\DeleteAccountInput;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Domains\Accounts\Contracts\PasswordVerifier;
use App\Domains\Accounts\Contracts\TeamMemberships;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountDeletionEmailMismatch;
use App\Domains\Accounts\Exceptions\IncorrectAccountPassword;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\AccountSessions;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class DeleteAccount
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PasswordVerifier $passwords,
        private readonly TeamMemberships $memberships,
        private readonly OwnedBusinesses $ownedBusinesses,
        private readonly AccountSessions $sessions,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(DeleteAccountInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $account = $this->accounts->findById($input->accountId);

            $account->ensureActive();

            $this->confirm($account, $input);

            $this->transactions->run(fn () => $this->scheduleDeletionOf($account));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->sessions->endAll([$account->id]);

        return UseCaseResponse::success();
    }

    /**
     * @throws IncorrectAccountPassword
     * @throws AccountDeletionEmailMismatch
     */
    private function confirm(Account $account, DeleteAccountInput $input): void
    {
        if (! $account->holdsPassword()) {
            $account->ensureDeletionConfirmedBy($input->email ?? '');

            return;
        }

        if (! $this->passwords->matches($account->id, $input->password ?? '')) {
            throw IncorrectAccountPassword::forAccount($account->id);
        }
    }

    private function scheduleDeletionOf(Account $account): void
    {
        $this->memberships->leaveTeamsNotOwned($account->id);

        $ownedBusinessId = $this->memberships->ownedBusinessIdOf($account->id);

        if ($ownedBusinessId !== null) {
            $this->ownedBusinesses->close($ownedBusinessId, $account->id);
        }

        $account->scheduleDeletion($this->clock->now());

        $this->accounts->save($account);
    }
}

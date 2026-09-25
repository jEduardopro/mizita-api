<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\InvitedAccountData;
use App\Domains\Accounts\Application\Dtos\ProvisionInvitedAccountInput;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\PasswordHasher;
use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class ProvisionInvitedAccount
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly TemporaryPasswordGenerator $temporaryPasswords,
        private readonly TemporaryPasswordVault $vault,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<InvitedAccountData>
     */
    public function handle(ProvisionInvitedAccountInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $invited = $this->provision($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($invited);
    }

    private function provision(ProvisionInvitedAccountInput $input): InvitedAccountData
    {
        $existing = $this->accounts->findByEmail($input->email);

        if ($existing !== null) {
            return InvitedAccountData::forExistingAccount($existing->id);
        }

        return $this->register($input);
    }

    private function register(ProvisionInvitedAccountInput $input): InvitedAccountData
    {
        $temporaryPassword = $this->temporaryPasswords->generate();

        $account = Account::inviteWithTemporaryPassword(
            id: $this->ids->next(),
            name: $input->name,
            email: $input->email,
            temporaryPasswordHash: $this->hasher->hash($temporaryPassword->value),
            now: $this->clock->now(),
        );

        try {
            $this->transactions->run(function () use ($account, $temporaryPassword): void {
                $this->accounts->save($account);
                $this->vault->keep($account->id, $temporaryPassword->value);
            });
        } catch (AccountAlreadyRegistered $conflict) {
            return $this->adoptConcurrentRegistration($input->email, $conflict);
        }

        return InvitedAccountData::forNewAccount($account->id, $temporaryPassword->value);
    }

    /**
     * @throws AccountAlreadyRegistered
     */
    private function adoptConcurrentRegistration(string $email, AccountAlreadyRegistered $conflict): InvitedAccountData
    {
        $existing = $this->accounts->findByEmail($email);

        if ($existing === null) {
            throw $conflict;
        }

        return InvitedAccountData::forExistingAccount($existing->id);
    }
}

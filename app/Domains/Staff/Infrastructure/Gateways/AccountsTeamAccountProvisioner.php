<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Accounts\Application\Dtos\InvitedAccountData;
use App\Domains\Accounts\Application\Dtos\IssueTemporaryPasswordInput;
use App\Domains\Accounts\Application\Dtos\ProvisionInvitedAccountInput;
use App\Domains\Accounts\Application\UseCases\IssueTemporaryPassword;
use App\Domains\Accounts\Application\UseCases\ProvisionAccessLessAccount;
use App\Domains\Accounts\Application\UseCases\ProvisionInvitedAccount;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Staff\Contracts\TeamAccountProvisioner;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\ProvisionedAccount;
use App\Domains\Staff\ValueObjects\StaffRole;

final class AccountsTeamAccountProvisioner implements TeamAccountProvisioner
{
    public function __construct(
        private readonly ProvisionInvitedAccount $provisionInvitedAccount,
        private readonly ProvisionAccessLessAccount $provisionAccessLessAccount,
        private readonly IssueTemporaryPassword $issueTemporaryPassword,
    ) {}

    /**
     * @throws InvalidProfileName
     * @throws InvalidTeamMemberEmail
     */
    public function provision(StaffRole $level, string $name, string $email): ProvisionedAccount
    {
        try {
            $account = $this->provisionFor($level, new ProvisionInvitedAccountInput(name: $name, email: $email));
        } catch (InvalidAccountName $invalid) {
            throw InvalidProfileName::rejectedByAccount($invalid);
        } catch (InvalidAccountEmail $invalid) {
            throw InvalidTeamMemberEmail::rejectedByAccount($invalid);
        }

        return new ProvisionedAccount(
            accountId: $account->accountId,
            temporaryPassword: $this->credentialsFor($level, $account),
        );
    }

    /**
     * @throws StaffMemberNotFound
     */
    public function issueTemporaryPassword(string $accountId): ?string
    {
        try {
            return $this->issueTemporaryPassword
                ->handle(new IssueTemporaryPasswordInput(accountId: $accountId))
                ->value()
                ->temporaryPassword;
        } catch (AccountNotFound $missing) {
            throw StaffMemberNotFound::forAccount($accountId, $missing);
        }
    }

    private function provisionFor(StaffRole $level, ProvisionInvitedAccountInput $input): InvitedAccountData
    {
        if ($level->grantsAccess()) {
            return $this->provisionInvitedAccount->handle($input)->value();
        }

        return $this->provisionAccessLessAccount->handle($input)->value();
    }

    private function credentialsFor(StaffRole $level, InvitedAccountData $account): ?string
    {
        if ($account->created || ! $level->grantsAccess()) {
            return $account->temporaryPassword;
        }

        return $this->issueTemporaryPassword($account->accountId);
    }
}

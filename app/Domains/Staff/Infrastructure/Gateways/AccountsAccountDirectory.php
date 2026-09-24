<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Accounts\Application\Dtos\RenameAccountInput;
use App\Domains\Accounts\Application\UseCases\RenameAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\AccountSnapshot;

final class AccountsAccountDirectory implements AccountDirectory
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly RenameAccount $renameAccount,
    ) {}

    /**
     * @param  list<string>  $accountIds
     * @return list<AccountSnapshot>
     */
    public function describe(array $accountIds): array
    {
        $accounts = $this->accounts->findManyByIds($accountIds);

        if ($accounts === []) {
            return [];
        }

        $holdingPassword = array_flip($this->accounts->idsHoldingPassword($accountIds));

        return array_map(
            static fn (Account $account): AccountSnapshot => new AccountSnapshot(
                id: $account->id,
                name: $account->name(),
                email: $account->email(),
                hasPassword: isset($holdingPassword[$account->id]),
            ),
            $accounts,
        );
    }

    /**
     * @throws StaffMemberNotFound
     * @throws InvalidProfileName
     */
    public function rename(string $accountId, string $name): void
    {
        try {
            $this->renameAccount->handle(new RenameAccountInput($accountId, $name))->value();
        } catch (AccountNotFound $missing) {
            throw StaffMemberNotFound::forAccount($accountId, $missing);
        } catch (InvalidAccountName $invalid) {
            throw InvalidProfileName::rejectedByAccount($invalid);
        }
    }
}

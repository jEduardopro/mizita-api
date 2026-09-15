<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\ValueObjects\AccountSnapshot;

final class AccountsAccountDirectory implements AccountDirectory
{
    public function __construct(
        private readonly AccountRepository $accounts,
    ) {}

    /**
     * @param  list<string>  $accountIds
     * @return list<AccountSnapshot>
     */
    public function describe(array $accountIds): array
    {
        return array_map(
            static fn (Account $account): AccountSnapshot => new AccountSnapshot(
                id: $account->id,
                name: $account->name(),
                email: $account->email(),
            ),
            $this->accounts->findManyByIds($accountIds),
        );
    }
}

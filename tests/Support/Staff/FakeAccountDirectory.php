<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\ValueObjects\AccountSnapshot;

final class FakeAccountDirectory implements AccountDirectory
{
    /**
     * @var array<string, AccountSnapshot>
     */
    private array $accounts = [];

    /**
     * @var list<list<string>>
     */
    public array $calls = [];

    public function __construct(AccountSnapshot ...$accounts)
    {
        foreach ($accounts as $account) {
            $this->accounts[$account->id] = $account;
        }
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<AccountSnapshot>
     */
    public function describe(array $accountIds): array
    {
        $this->calls[] = array_values($accountIds);

        $snapshots = [];

        foreach ($accountIds as $accountId) {
            if (isset($this->accounts[$accountId])) {
                $snapshots[] = $this->accounts[$accountId];
            }
        }

        return $snapshots;
    }

    public function callCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<string>
     */
    public function lastCall(): array
    {
        return $this->calls[count($this->calls) - 1];
    }
}
